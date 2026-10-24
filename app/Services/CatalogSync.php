<?php

namespace App\Services;

use App\Models\CatalogItem;
use App\Models\ListRow;
use App\Models\StudyList;
use App\Support\FieldParser;
use App\Support\NameNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Keeps the public catalog in step with PUBLIC lists. Each public row is linked to one
 * de-duplicated CatalogItem (same category + institution + program); private rows are unlinked.
 * An item's fields are rebuilt from all of its public rows, the most recently edited value winning.
 */
class CatalogSync
{
    private const PROGRAM_FUZZY_THRESHOLD = 88.0;

    public function __construct(private InstitutionResolver $institutions) {}

    public function syncList(StudyList $list): void
    {
        $list->loadMissing('columns');

        $list->rows()->chunkById(200, function (Collection $rows) use ($list): void {
            foreach ($rows as $row) {
                $row->setRelation('studyList', $list);
                $this->syncRow($row);
            }
        });
    }

    public function syncRow(ListRow $row): void
    {
        $list = $row->studyList;
        $previousItemId = $row->catalog_item_id;
        $item = $list->isPublic() ? $this->itemFor($row, $list) : null;

        if ($row->catalog_item_id !== $item?->id) {
            $row->catalog_item_id = $item?->id;
            $row->saveQuietly();
        }

        if ($item) {
            $this->refresh($item);
        }

        if ($previousItemId && $previousItemId !== $item?->id) {
            $this->refreshById($previousItemId);
        }
    }

    /**
     * Delete a list (and its rows) and update every catalog item it fed.
     */
    public function deleteList(StudyList $list): void
    {
        $itemIds = $list->rows()->whereNotNull('catalog_item_id')->distinct()->pluck('catalog_item_id');

        $list->delete();

        foreach ($itemIds as $itemId) {
            $this->refreshById($itemId);
        }
    }

    public function forgetRow(ListRow $row): void
    {
        if ($row->catalog_item_id) {
            $this->refreshById($row->catalog_item_id, ignoreRowId: $row->id);
        }
    }

    public function refreshById(int $itemId, ?int $ignoreRowId = null): void
    {
        if ($item = CatalogItem::find($itemId)) {
            $this->refresh($item, $ignoreRowId);
        }
    }

    /**
     * Recompute the item's merged fields from every public row that points at it.
     */
    public function refresh(CatalogItem $item, ?int $ignoreRowId = null): void
    {
        $rows = ListRow::query()
            ->with('studyList.columns')
            ->where('catalog_item_id', $item->id)
            ->when($ignoreRowId, fn ($query) => $query->whereKeyNot($ignoreRowId))
            ->whereHas('studyList', fn ($query) => $query->where('visibility', StudyList::VISIBILITY_PUBLIC))
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get();

        $merged = array_fill_keys(CatalogItem::MERGED_FIELDS, null);
        $merged['nc_status'] = 'unknown';
        $merged['gre'] = 'unknown';
        $category = $item->category;

        foreach ($rows as $row) {
            $attributes = FieldParser::toCatalogAttributes($this->canonicalValues($row, $row->studyList));

            foreach ($attributes as $field => $value) {
                if ($value !== null && ! in_array($value, ['unknown', ''], true)) {
                    $merged[$field] = $value;
                }
            }
        }

        $merged['country'] ??= $item->institution?->country ?? config("studyhub.categories.{$category}.country");
        $merged['sources_count'] = $rows->count();

        $item->fill($merged)->save();
    }

    private function itemFor(ListRow $row, StudyList $list): ?CatalogItem
    {
        $values = $this->canonicalValues($row, $list);
        $title = trim($values['program'] ?? '');
        $universityName = trim($values['university'] ?? '');

        if ($title === '' && $universityName === '') {
            return null;
        }

        $country = trim($values['country'] ?? '') ?: config("studyhub.categories.{$list->category}.country");
        $institution = $universityName !== ''
            ? $this->institutions->resolve($universityName, $country, trim($values['city'] ?? '') ?: null)
            : null;

        $title = $title !== '' ? $title : 'General admission';
        $programKey = NameNormalizer::programKey($title);
        $acronym = NameNormalizer::acronym($title);

        $existing = $this->matchProgram($list->category, $institution?->id, $programKey, $acronym);

        if ($existing) {
            if (mb_strlen($title) > mb_strlen($existing->title)) {
                $existing->update(['title' => Str::limit($title, 255, '')]);
            }

            if ($acronym && ! $existing->acronym) {
                $existing->update(['acronym' => $acronym]);
            }

            return $existing;
        }

        return CatalogItem::create([
            'category' => $list->category,
            'institution_id' => $institution?->id,
            'title' => Str::limit($title, 255, ''),
            'program_key' => Str::limit($programKey, 255, ''),
            'acronym' => $acronym,
            'dedupe_key' => $this->dedupeKey($list->category, $institution?->id, $programKey),
            'country' => $country ? Str::limit($country, 80, '') : null,
        ]);
    }

    private function matchProgram(string $category, ?int $institutionId, string $programKey, ?string $acronym): ?CatalogItem
    {
        if ($exact = CatalogItem::where('dedupe_key', $this->dedupeKey($category, $institutionId, $programKey))->first()) {
            return $exact;
        }

        $candidates = CatalogItem::query()
            ->where('category', $category)
            ->where('institution_id', $institutionId)
            ->get();

        if ($acronym && strlen($acronym) >= 3) {
            $byAcronym = $candidates->first(fn (CatalogItem $item) => $item->acronym === $acronym
                || ($item->acronym === null && NameNormalizer::initials($item->title) === $acronym));

            if ($byAcronym) {
                return $byAcronym;
            }
        }

        $best = $candidates
            ->map(fn (CatalogItem $item) => [$item, NameNormalizer::similarity($programKey, $item->program_key)])
            ->sortByDesc(1)
            ->first();

        return $best && $best[1] >= self::PROGRAM_FUZZY_THRESHOLD ? $best[0] : null;
    }

    private function dedupeKey(string $category, ?int $institutionId, string $programKey): string
    {
        return $category.'|'.($institutionId ?? 0).'|'.sha1($programKey);
    }

    /**
     * @return array<string, string> canonical field => cell text
     */
    private function canonicalValues(ListRow $row, StudyList $list): array
    {
        $values = [];

        foreach ($list->columns as $column) {
            if ($column->field && ($value = $row->value($column->key)) !== '') {
                $values[$column->field] ??= $value;
            }
        }

        return $values;
    }
}

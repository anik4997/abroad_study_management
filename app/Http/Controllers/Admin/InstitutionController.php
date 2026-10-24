<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatalogItem;
use App\Models\Institution;
use App\Models\ListRow;
use App\Services\CatalogSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Lets an admin fix duplicates the automatic matcher missed by merging two institutions.
 */
class InstitutionController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));

        $institutions = Institution::query()
            ->withCount('catalogItems', 'aliases')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('admin.institutions', ['institutions' => $institutions]);
    }

    public function update(Request $request, Institution $institution): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url', 'max:255'],
        ]);

        $institution->update($data);

        return back()->with('status', 'Institution updated.');
    }

    public function merge(Request $request, CatalogSync $catalog): RedirectResponse
    {
        $data = $request->validate([
            'source_id' => ['required', 'integer', Rule::exists('institutions', 'id')],
            'target_id' => ['required', 'integer', 'different:source_id', Rule::exists('institutions', 'id')],
        ]);

        $source = Institution::findOrFail($data['source_id']);
        $target = Institution::findOrFail($data['target_id']);

        DB::transaction(function () use ($source, $target, $catalog): void {
            $source->aliases()->update(['institution_id' => $target->id]);

            foreach ($source->catalogItems as $item) {
                $duplicate = CatalogItem::where('category', $item->category)
                    ->where('institution_id', $target->id)
                    ->where('program_key', $item->program_key)
                    ->first();

                if ($duplicate) {
                    ListRow::where('catalog_item_id', $item->id)->update(['catalog_item_id' => $duplicate->id]);
                    $item->delete();
                    $catalog->refresh($duplicate);
                } else {
                    $item->update([
                        'institution_id' => $target->id,
                        'dedupe_key' => $item->category.'|'.$target->id.'|'.sha1($item->program_key),
                    ]);
                }
            }

            $target->fill([
                'country' => $target->country ?? $source->country,
                'city' => $target->city ?? $source->city,
                'website' => $target->website ?? $source->website,
                'acronym' => $target->acronym ?? $source->acronym,
            ])->save();

            $source->delete();
        });

        return back()->with('status', "Merged “{$source->name}” into “{$target->name}”.");
    }
}

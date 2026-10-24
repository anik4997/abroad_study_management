<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['data', 'position', 'catalog_item_id'])]
class ListRow extends Model
{
    /**
     * Stored with unescaped unicode so LIKE searches work for Bengali/German text on MariaDB too.
     *
     * @return Attribute<array<string, string>, array<string, string>>
     */
    protected function data(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): array => $value ? (array) json_decode($value, true) : [],
            set: fn (array $value): string => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return BelongsTo<StudyList, $this>
     */
    public function studyList(): BelongsTo
    {
        return $this->belongsTo(StudyList::class);
    }

    /**
     * @return BelongsTo<CatalogItem, $this>
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function value(string $key): string
    {
        return trim((string) ($this->data[$key] ?? ''));
    }
}

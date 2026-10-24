<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogItem extends Model
{
    protected $guarded = ['id'];

    /**
     * Canonical fields that are recomputed from contributing rows.
     *
     * @var list<string>
     */
    public const MERGED_FIELDS = [
        'country', 'city', 'degree', 'language', 'nc_status', 'intake', 'deadline', 'deadline_date',
        'tuition', 'tuition_amount', 'application_fee', 'ielts', 'toefl', 'gre', 'portal', 'entrance',
        'ects', 'gpa', 'requirements', 'documents', 'funding', 'website',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deadline_date' => 'date',
            'tuition_amount' => 'float',
            'ielts' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @return HasMany<ListRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ListRow::class);
    }

    /**
     * @param  Builder<CatalogItem>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('sources_count', '>', 0);
    }

    public function categoryLabel(): string
    {
        return config("studyhub.categories.{$this->category}.label", 'Other');
    }

    public function ncLabel(): string
    {
        return match ($this->nc_status) {
            'nc' => 'NC',
            'nc_free' => 'NC-free',
            default => 'NC unknown',
        };
    }

    public function greLabel(): ?string
    {
        return match ($this->gre) {
            'required' => 'GRE/GMAT required',
            'optional' => 'GRE optional',
            'no' => 'No GRE',
            default => null,
        };
    }
}

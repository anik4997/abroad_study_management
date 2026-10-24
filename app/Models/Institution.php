<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'key', 'acronym', 'country', 'city', 'website'])]
class Institution extends Model
{
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<InstitutionAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(InstitutionAlias::class);
    }

    /**
     * @return HasMany<CatalogItem, $this>
     */
    public function catalogItems(): HasMany
    {
        return $this->hasMany(CatalogItem::class);
    }
}

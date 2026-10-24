<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['name', 'username', 'password', 'recovery_code'])]
#[Hidden(['password', 'remember_token', 'recovery_code'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'recovery_code' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * @return HasMany<StudyList, $this>
     */
    public function studyLists(): HasMany
    {
        return $this->hasMany(StudyList::class);
    }

    /**
     * @return BelongsToMany<StudyList, $this>
     */
    public function sharedLists(): BelongsToMany
    {
        return $this->belongsToMany(StudyList::class, 'list_shares')->withPivot('can_edit')->withTimestamps();
    }

    public function displayName(): string
    {
        return $this->name ?: $this->username;
    }
}

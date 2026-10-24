<?php

namespace App\Models;

use Database\Factories\StudyListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['title', 'description', 'category', 'visibility'])]
class StudyList extends Model
{
    /** @use HasFactory<StudyListFactory> */
    use HasFactory;

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_PUBLIC = 'public';

    protected static function booted(): void
    {
        static::creating(function (StudyList $list): void {
            $list->slug ??= (Str::slug(Str::limit($list->title, 60, '')) ?: 'list').'-'.Str::lower(Str::random(8));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ListColumn, $this>
     */
    public function columns(): HasMany
    {
        return $this->hasMany(ListColumn::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<ListRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ListRow::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function sharedWith(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'list_shares')->withPivot('can_edit')->withTimestamps();
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }

    public function categoryLabel(): string
    {
        return config("studyhub.categories.{$this->category}.label", 'Other');
    }

    public function categoryIcon(): string
    {
        return config("studyhub.categories.{$this->category}.icon", '📁');
    }

    /**
     * Lists the given user may open: own, public, or shared with them.
     *
     * @param  Builder<StudyList>  $query
     */
    public function scopeVisibleTo(Builder $query, ?User $user): void
    {
        $query->where(function (Builder $query) use ($user): void {
            $query->where('visibility', self::VISIBILITY_PUBLIC);

            if ($user) {
                $query->orWhere('user_id', $user->id)
                    ->orWhereHas('sharedWith', fn (Builder $shared) => $shared->whereKey($user->id));
            }
        });
    }

    /**
     * Create the list together with the starter columns for its category.
     *
     * @param  array{title: string, description?: ?string, category: string, visibility: string}  $attributes
     */
    public static function createWithTemplate(User $owner, array $attributes, bool $withTemplate = true): self
    {
        $list = $owner->studyLists()->create($attributes);

        if ($withTemplate) {
            foreach (config("studyhub.templates.{$list->category}", []) as $position => $column) {
                $list->addColumn($column[0], $column[1], $column[2] ?? null, $column[3] ?? null, $position);
            }
        }

        return $list;
    }

    /**
     * @param  array<int, string>|null  $options
     */
    public function addColumn(string $label, string $type = 'text', ?string $field = null, ?array $options = null, ?int $position = null): ListColumn
    {
        return $this->columns()->create([
            'key' => $this->uniqueColumnKey($label),
            'label' => Str::limit(trim($label) ?: 'Column', 120, ''),
            'type' => array_key_exists($type, config('studyhub.column_types')) ? $type : 'text',
            'field' => array_key_exists((string) $field, config('studyhub.fields')) ? $field : null,
            'options' => $options,
            'position' => $position ?? (int) $this->columns()->max('position') + 1,
        ]);
    }

    private function uniqueColumnKey(string $label): string
    {
        $base = Str::limit(Str::slug(Str::ascii($label), '_'), 30, '') ?: 'col';
        $key = $base;
        $existing = $this->columns()->pluck('key')->all();

        for ($i = 2; in_array($key, $existing, true); $i++) {
            $key = $base.'_'.$i;
        }

        return $key;
    }
}

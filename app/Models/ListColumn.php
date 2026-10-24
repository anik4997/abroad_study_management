<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['key', 'label', 'type', 'field', 'options', 'position'])]
class ListColumn extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
        ];
    }

    /**
     * @return BelongsTo<StudyList, $this>
     */
    public function studyList(): BelongsTo
    {
        return $this->belongsTo(StudyList::class);
    }
}

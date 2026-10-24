<?php

namespace App\Policies;

use App\Models\StudyList;
use App\Models\User;

class StudyListPolicy
{
    public function view(?User $user, StudyList $list): bool
    {
        return $list->isPublic() || ($user && ($this->owns($user, $list) || $this->sharedWith($user, $list) !== null));
    }

    /**
     * Owner and users the list was shared with "can edit" may change rows and columns.
     */
    public function edit(User $user, StudyList $list): bool
    {
        return $this->owns($user, $list) || (bool) $this->sharedWith($user, $list)?->pivot->can_edit;
    }

    /**
     * Settings, visibility, sharing and deletion are owner-only.
     */
    public function manage(User $user, StudyList $list): bool
    {
        return $this->owns($user, $list);
    }

    private function owns(User $user, StudyList $list): bool
    {
        return $list->user_id === $user->id;
    }

    private function sharedWith(User $user, StudyList $list): ?User
    {
        return $list->sharedWith()->whereKey($user->id)->first();
    }
}

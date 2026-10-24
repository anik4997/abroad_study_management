<?php

namespace App\Livewire;

use App\Models\StudyList;
use App\Models\User;
use App\Services\CatalogSync;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Owner-only panel: title/category, private vs public, and sharing with specific users.
 */
class ListSettings extends Component
{
    #[Locked]
    public int $listId;

    public string $title = '';

    public string $description = '';

    public string $category = '';

    public string $visibility = '';

    public string $shareUsername = '';

    public bool $shareCanEdit = false;

    public function mount(StudyList $list): void
    {
        Gate::authorize('manage', $list);
        $this->listId = $list->id;
        $this->fill($list->only(['title', 'category', 'visibility']));
        $this->description = (string) $list->description;
    }

    public function save(): void
    {
        $list = $this->list();

        $data = $this->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', Rule::in(array_keys(config('studyhub.categories')))],
            'visibility' => ['required', Rule::in([StudyList::VISIBILITY_PRIVATE, StudyList::VISIBILITY_PUBLIC])],
        ]);

        $list->update($data);
        $this->dispatch('list-imported');
        session()->flash('settings_status', $list->isPublic()
            ? 'Saved. This list is public and feeds the public directory.'
            : 'Saved. This list is private.');
    }

    public function share(): void
    {
        $list = $this->list();
        $this->validate(['shareUsername' => ['required', 'string', 'max:32']]);

        $user = User::where('username', Str::lower(trim($this->shareUsername)))->first();

        if (! $user) {
            throw ValidationException::withMessages(['shareUsername' => 'No user with that username.']);
        }

        if ($user->id === $list->user_id) {
            throw ValidationException::withMessages(['shareUsername' => 'You already own this list.']);
        }

        $list->sharedWith()->syncWithoutDetaching([$user->id => ['can_edit' => $this->shareCanEdit]]);
        $list->sharedWith()->updateExistingPivot($user->id, ['can_edit' => $this->shareCanEdit]);
        $this->reset('shareUsername', 'shareCanEdit');
    }

    public function toggleEdit(int $userId): void
    {
        $list = $this->list();
        $share = $list->sharedWith()->whereKey($userId)->firstOrFail();
        $list->sharedWith()->updateExistingPivot($userId, ['can_edit' => ! $share->pivot->can_edit]);
    }

    public function unshare(int $userId): void
    {
        $this->list()->sharedWith()->detach($userId);
    }

    public function deleteList(CatalogSync $catalog): void
    {
        $catalog->deleteList($this->list());

        session()->flash('status', 'List deleted.');
        $this->redirectRoute('dashboard');
    }

    public function render(): View
    {
        return view('livewire.list-settings', [
            'list' => $this->list(),
            'shares' => $this->list()->sharedWith()->orderBy('username')->get(),
        ]);
    }

    private function list(): StudyList
    {
        $list = StudyList::findOrFail($this->listId);
        Gate::authorize('manage', $list);

        return $list;
    }
}

<div class="grid gap-6 lg:grid-cols-2">
    <section class="card p-6">
        <h2 class="font-semibold">List settings</h2>
        @if (session('settings_status'))
            <p class="mt-3 rounded-lg bg-brand-50 px-3 py-2 text-sm text-brand-700">{{ session('settings_status') }}</p>
        @endif
        <form wire:submit="save" class="mt-4 space-y-4">
            <div>
                <label class="label" for="set-title">Title</label>
                <input id="set-title" wire:model="title" class="input" maxlength="120">
                @error('title')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="set-description">Description</label>
                <textarea id="set-description" wire:model="description" rows="3" class="input" maxlength="2000"></textarea>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="set-category">Type</label>
                    <select id="set-category" wire:model="category" class="input">
                        @foreach (config('studyhub.categories') as $key => $cat)
                            <option value="{{ $key }}">{{ $cat['icon'] }} {{ $cat['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="set-visibility">Visibility</label>
                    <select id="set-visibility" wire:model="visibility" class="input">
                        <option value="private">🔒 Private: only me and people I share with</option>
                        <option value="public">🌍 Public: anyone, and feeds the directory</option>
                    </select>
                </div>
            </div>
            <button class="btn-primary"><span wire:loading.remove wire:target="save">Save settings</span><span wire:loading wire:target="save">Saving…</span></button>
        </form>
    </section>

    <section class="card p-6">
        <h2 class="font-semibold">Share with specific people</h2>
        <p class="mt-1 text-sm text-slate-500">They will see this list under “Shared with me”, even while it is private.</p>
        <form wire:submit="share" class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-start">
            <div class="flex-1">
                <input wire:model="shareUsername" class="input" placeholder="username" aria-label="Username to share with">
                @error('shareUsername')<p class="error">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-center gap-2 py-2 text-sm text-slate-600">
                <input type="checkbox" wire:model="shareCanEdit" class="rounded border-slate-300 text-brand-600"> can edit
            </label>
            <button class="btn-primary">Share</button>
        </form>

        <ul class="mt-4 divide-y divide-slate-100">
            @forelse ($shares as $person)
                <li class="flex items-center justify-between gap-2 py-2.5 text-sm" wire:key="share-{{ $person->id }}">
                    <span><span class="font-medium">{{ $person->displayName() }}</span> <span class="text-slate-400">{{ '@'.$person->username }}</span></span>
                    <span class="flex items-center gap-3">
                        <button wire:click="toggleEdit({{ $person->id }})" class="chip {{ $person->pivot->can_edit ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600' }}" title="Click to change">
                            {{ $person->pivot->can_edit ? 'can edit' : 'view only' }}
                        </button>
                        <button wire:click="unshare({{ $person->id }})" class="text-slate-400 hover:text-red-600" aria-label="Remove">✕</button>
                    </span>
                </li>
            @empty
                <li class="py-2.5 text-sm text-slate-500">Not shared with anyone.</li>
            @endforelse
        </ul>
    </section>

    <section class="card border-red-200 p-6 lg:col-span-2">
        <h2 class="font-semibold text-red-700">Delete list</h2>
        <p class="mt-1 text-sm text-slate-500">Removes the list, all rows, and its contributions to the public directory.</p>
        <button wire:click="deleteList" wire:confirm="Delete “{{ $list->title }}” permanently?" class="btn-danger mt-4">Delete list</button>
    </section>
</div>

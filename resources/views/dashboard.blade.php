<x-layouts.app title="My lists">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-brand-900">My lists</h1>
            <p class="text-sm text-slate-500">Universities, programs, scholarships: anything you are tracking.</p>
        </div>
        <div class="flex gap-2" x-data>
            <a href="{{ route('lists.import') }}" class="btn-ghost">📥 Import Excel / Google Sheet</a>
            <button class="btn-primary" @click="$dispatch('open-create')">+ New list</button>
        </div>
    </div>

    <div x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }" @open-create.window="open = true" x-show="open" x-cloak class="fixed inset-0 z-40 grid place-items-center bg-slate-900/40 p-4" @keydown.escape.window="open = false">
        <div class="card w-full max-w-lg p-6" @click.outside="open = false">
            <h2 class="text-lg font-semibold">New list</h2>
            <form method="POST" action="{{ route('lists.store') }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="label" for="title">Title</label>
                    <input id="title" name="title" value="{{ old('title') }}" class="input" required maxlength="120" placeholder="e.g. My German MSc shortlist 2027">
                    @error('title')<p class="error">{{ $message }}</p>@enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="category">Type</label>
                        <select id="category" name="category" class="input">
                            @foreach (config('studyhub.categories') as $key => $category)
                                <option value="{{ $key }}" @selected(old('category') === $key)>{{ $category['icon'] }} {{ $category['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="visibility">Visibility</label>
                        <select id="visibility" name="visibility" class="input">
                            <option value="private" @selected(old('visibility') !== 'public')>🔒 Private</option>
                            <option value="public" @selected(old('visibility') === 'public')>🌍 Public</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="label" for="description">Description <span class="font-normal text-slate-400">(optional)</span></label>
                    <textarea id="description" name="description" rows="2" class="input" maxlength="2000">{{ old('description') }}</textarea>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="hidden" name="template" value="0">
                    <input type="checkbox" name="template" value="1" checked class="rounded border-slate-300 text-brand-600">
                    Start with suggested columns for this type (you can change them later)
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-ghost" @click="open = false">Cancel</button>
                    <button class="btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>

    @if ($ownLists->isEmpty() && $sharedLists->isEmpty())
        <div class="card mt-8 p-10 text-center">
            <div class="text-4xl">🗂️</div>
            <h2 class="mt-3 font-semibold">No lists yet</h2>
            <p class="mt-1 text-sm text-slate-500">Create a list from a template, or import your existing Excel / Google Sheet.</p>
        </div>
    @endif

    @if ($ownLists->isNotEmpty())
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($ownLists as $list)
                @include('lists._card', ['list' => $list, 'owner' => null])
            @endforeach
        </div>
    @endif

    @if ($sharedLists->isNotEmpty())
        <h2 class="mt-10 text-lg font-semibold text-slate-900">Shared with me</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($sharedLists as $list)
                @include('lists._card', ['list' => $list, 'owner' => $list->user, 'canEdit' => $list->pivot->can_edit])
            @endforeach
        </div>
    @endif
</x-layouts.app>

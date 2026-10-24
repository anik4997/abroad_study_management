<x-layouts.app title="Public lists">
    <h1 class="text-2xl font-semibold text-brand-900">Public lists</h1>
    <p class="text-sm text-slate-500">Lists people have chosen to share with everyone.</p>

    <form class="mt-5 flex flex-col gap-2 sm:flex-row">
        <input name="q" value="{{ request('q') }}" class="input sm:max-w-xs" placeholder="Search titles…">
        <select name="category" class="input sm:max-w-xs" onchange="this.form.submit()">
            <option value="">All types</option>
            @foreach (config('studyhub.categories') as $key => $category)
                <option value="{{ $key }}" @selected(request('category') === $key)>{{ $category['label'] }}</option>
            @endforeach
        </select>
        <button class="btn-ghost">Search</button>
    </form>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($lists as $list)
            @include('lists._card', ['list' => $list, 'owner' => $list->user])
        @empty
            <p class="text-sm text-slate-500">No public lists yet.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $lists->links() }}</div>
</x-layouts.app>

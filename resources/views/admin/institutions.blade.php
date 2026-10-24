<x-layouts.app title="Institutions">
    <h1 class="text-2xl font-semibold text-brand-900">Institutions</h1>
    <p class="text-sm text-slate-500">Fix duplicates the automatic matcher missed: merge the duplicate into the correct one. Its spellings are remembered, so future imports match automatically.</p>

    <section class="card mt-5 p-5">
        <h2 class="font-semibold">Merge</h2>
        <form method="POST" action="{{ route('admin.institutions.merge') }}" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end" onsubmit="return confirm('Merge these institutions?')">
            @csrf
            <div class="flex-1">
                <label class="label" for="source_id">Duplicate (will be removed): ID</label>
                <input id="source_id" name="source_id" type="number" class="input" required>
            </div>
            <div class="flex-1">
                <label class="label" for="target_id">Keep: ID</label>
                <input id="target_id" name="target_id" type="number" class="input" required>
            </div>
            <button class="btn-primary">Merge</button>
        </form>
        @error('source_id')<p class="error">{{ $message }}</p>@enderror
        @error('target_id')<p class="error">{{ $message }}</p>@enderror
    </section>

    <form class="mt-5 flex gap-2">
        <input name="q" value="{{ request('q') }}" class="input max-w-sm" placeholder="Search…">
        <button class="btn-ghost">Search</button>
    </form>

    <div class="card mt-4 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr><th class="px-3 py-2">ID</th><th class="px-3 py-2">Name / country / city / website</th><th class="px-3 py-2">Items</th><th class="px-3 py-2">Spellings</th><th></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($institutions as $institution)
                    <tr>
                        <td class="px-3 py-2 font-mono text-slate-500">{{ $institution->id }}</td>
                        <td class="px-3 py-2" colspan="1">
                            <form method="POST" action="{{ route('admin.institutions.update', $institution) }}" id="inst-{{ $institution->id }}" class="grid gap-1 sm:grid-cols-4">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $institution->name }}" class="input py-1" required>
                                <input name="country" value="{{ $institution->country }}" class="input py-1" placeholder="Country">
                                <input name="city" value="{{ $institution->city }}" class="input py-1" placeholder="City">
                                <input name="website" value="{{ $institution->website }}" class="input py-1" placeholder="https://…">
                            </form>
                        </td>
                        <td class="px-3 py-2">{{ $institution->catalog_items_count }}</td>
                        <td class="px-3 py-2">{{ $institution->aliases_count }}</td>
                        <td class="px-3 py-2"><button form="inst-{{ $institution->id }}" class="btn-ghost py-1">Save</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $institutions->links() }}</div>
</x-layouts.app>

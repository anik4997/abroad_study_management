<x-layouts.app title="Universities">
    <h1 class="text-2xl font-semibold text-brand-900">Universities &amp; providers</h1>
    <p class="text-sm text-slate-500">Each one appears once. Short forms and spelling variants are merged.</p>
    <form class="mt-5 flex flex-col gap-2 sm:flex-row">
        <input name="q" value="{{ request('q') }}" class="input sm:max-w-sm" placeholder="Name or short form (e.g. TUM, KIT)…">
        <select name="country" class="input sm:max-w-xs" onchange="this.form.submit()">
            <option value="">All countries</option>
            @foreach ($countries as $country)
                <option value="{{ $country }}" @selected(request('country') === $country)>{{ $country }}</option>
            @endforeach
        </select>
        <button class="btn-ghost">Search</button>
    </form>
    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($institutions as $institution)
            <a href="{{ route('universities.show', $institution) }}" class="card flex items-center justify-between gap-3 p-4 hover:border-brand-200">
                <div class="min-w-0">
                    <div class="truncate font-medium text-slate-900">{{ $institution->name }}</div>
                    <div class="text-xs text-slate-500">{{ collect([$institution->acronym, $institution->city, $institution->country])->filter()->join(' · ') }}</div>
                </div>
                <span class="chip shrink-0 bg-brand-50 text-brand-700">{{ $institution->programs_count }}</span>
            </a>
        @empty
            <p class="text-sm text-slate-500">No universities yet.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $institutions->links() }}</div>
</x-layouts.app>

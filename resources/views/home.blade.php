<x-layouts.app>
    <section class="rounded-2xl bg-gradient-to-br from-brand-600 to-brand-900 px-6 py-10 text-white sm:px-10 sm:py-14">
        <h1 class="max-w-2xl text-3xl font-semibold leading-tight sm:text-4xl">Plan your study abroad in one place.</h1>
        <p class="mt-3 max-w-2xl text-brand-100">German universities, US programs, Erasmus Mundus and scholarships. Keep your own lists (import from Excel or Google Sheets), and browse a public directory built from everyone's public lists, with duplicates merged.</p>
        <form action="{{ route('explore') }}" class="mt-6 flex max-w-xl gap-2">
            <input name="q" class="input border-0 py-3" placeholder="Search a program, university or city…" aria-label="Search">
            <button class="btn bg-white px-5 text-brand-700 hover:bg-brand-50">Search</button>
        </form>
        <div class="mt-6 flex flex-wrap gap-2">
            @guest
                <a href="{{ route('register') }}" class="btn bg-brand-500/40 text-white ring-1 ring-white/40 hover:bg-brand-500/60">Create a free account</a>
            @else
                <a href="{{ route('dashboard') }}" class="btn bg-brand-500/40 text-white ring-1 ring-white/40 hover:bg-brand-500/60">Go to my lists</a>
            @endguest
        </div>
    </section>

    <section class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        @foreach (config('studyhub.categories') as $key => $category)
            <a href="{{ route('explore', ['category' => $key]) }}" class="card p-4 transition hover:border-brand-200">
                <div class="text-2xl">{{ $category['icon'] }}</div>
                <div class="mt-2 text-sm font-medium text-slate-800">{{ $category['label'] }}</div>
                <div class="text-xs text-slate-500">{{ number_format($counts[$key] ?? 0) }} listed</div>
            </a>
        @endforeach
    </section>

    <section class="mt-4 flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-500">
        <span><strong class="text-slate-800">{{ number_format($institutionCount) }}</strong> universities &amp; providers</span>
        <span><strong class="text-slate-800">{{ number_format($listCount) }}</strong> public lists</span>
    </section>

    @if ($upcoming->isNotEmpty())
        <section class="mt-10">
            <div class="flex items-end justify-between">
                <h2 class="text-lg font-semibold text-slate-900">Upcoming deadlines</h2>
                <a href="{{ route('explore', ['openOnly' => 1]) }}" class="text-sm font-medium text-brand-700">See all →</a>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($upcoming as $item)
                    <x-catalog-card :item="$item" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-10 grid gap-4 md:grid-cols-3">
        @foreach ([
            ['📥', 'Import from Excel', 'Upload .xlsx/.csv or paste a public Google Sheets link. Columns are detected automatically and you can rename or add columns any time.'],
            ['🔒', 'Private, public or shared', 'Keep a list private, publish it to the directory, or share it with specific usernames (view or edit).'],
            ['🧹', 'No duplicates', 'TUM, “TU München” and “Technical University of Munich” become one university. Same program from many lists becomes one entry.'],
        ] as [$icon, $heading, $text])
            <div class="card p-5">
                <div class="text-2xl">{{ $icon }}</div>
                <h3 class="mt-2 font-semibold">{{ $heading }}</h3>
                <p class="mt-1 text-sm text-slate-600">{{ $text }}</p>
            </div>
        @endforeach
    </section>
</x-layouts.app>

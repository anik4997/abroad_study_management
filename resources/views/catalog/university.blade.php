<x-layouts.app :title="$institution->name">
    <a href="{{ route('universities.index') }}" class="text-sm text-slate-500 hover:text-slate-800">← Universities</a>
    <h1 class="mt-3 text-2xl font-semibold text-brand-900">{{ $institution->name }}</h1>
    <p class="text-sm text-slate-500">{{ collect([$institution->city, $institution->country])->filter()->join(', ') }}</p>
    @if ($institution->aliases->count() > 1)
        <p class="mt-2 text-xs text-slate-500">Also written as: {{ $institution->aliases->pluck('alias')->reject(fn ($a) => $a === $institution->name)->unique()->join(' · ') }}</p>
    @endif
    @if ($institution->website)
        <a href="{{ $institution->website }}" target="_blank" rel="noopener noreferrer nofollow" class="mt-2 inline-block text-sm text-brand-700">Website ↗</a>
    @endif
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($items as $item)
            <x-catalog-card :item="$item->setRelation('institution', $institution)" />
        @endforeach
    </div>
</x-layouts.app>

@props(['item'])
@php
    $deadlinePassed = $item->deadline_date && $item->deadline_date->isPast();
@endphp
<a href="{{ route('catalog.item', $item) }}" {{ $attributes->merge(['class' => 'card group flex flex-col p-4 transition hover:border-brand-200 hover:shadow-md']) }}>
    <div class="flex items-start justify-between gap-2">
        <span class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $item->categoryLabel() }}</span>
        @if ($item->sources_count > 1)
            <span class="chip bg-slate-100 text-slate-600" title="Listed by {{ $item->sources_count }} public lists">×{{ $item->sources_count }}</span>
        @endif
    </div>
    <h3 class="mt-1 font-semibold leading-snug text-slate-900 group-hover:text-brand-700">{{ $item->title }}</h3>
    @if ($item->institution)
        <p class="mt-0.5 text-sm text-slate-600">{{ $item->institution->name }}</p>
    @endif
    @if ($item->city || $item->country)
        <p class="mt-0.5 text-xs text-slate-500">📍 {{ collect([$item->city, $item->country])->filter()->join(', ') }}</p>
    @endif

    <div class="mt-3 flex flex-wrap gap-1.5">
        @if ($item->category === 'german_uni' || $item->nc_status !== 'unknown')
            <span @class(['chip', 'bg-emerald-50 text-emerald-700' => $item->nc_status === 'nc_free', 'bg-amber-50 text-amber-700' => $item->nc_status === 'nc', 'bg-slate-100 text-slate-500' => $item->nc_status === 'unknown'])>{{ $item->ncLabel() }}</span>
        @endif
        @if ($item->ielts)<span class="chip bg-sky-50 text-sky-700">IELTS {{ rtrim(rtrim(number_format($item->ielts, 1), '0'), '.') }}</span>@endif
        @if ($item->toefl)<span class="chip bg-sky-50 text-sky-700">TOEFL {{ $item->toefl }}</span>@endif
        @if ($item->greLabel())<span class="chip bg-violet-50 text-violet-700">{{ $item->greLabel() }}</span>@endif
        @if ($item->portal)<span class="chip bg-slate-100 text-slate-700">{{ $item->portal === 'vpd' ? 'VPD' : ucfirst($item->portal) }}</span>@endif
        @if ($item->tuition_amount === 0.0)<span class="chip bg-emerald-50 text-emerald-700">No tuition</span>@elseif ($item->tuition)<span class="chip bg-slate-100 text-slate-700">{{ \Illuminate\Support\Str::limit($item->tuition, 24) }}</span>@endif
        @if ($item->language)<span class="chip bg-slate-100 text-slate-700">{{ $item->language }}</span>@endif
    </div>

    @if ($item->deadline)
        <p @class(['mt-auto pt-3 text-xs', 'text-slate-400 line-through' => $deadlinePassed, 'text-slate-600' => ! $deadlinePassed])>
            ⏰ {{ \Illuminate\Support\Str::limit($item->deadline, 60) }}
            @if ($item->deadline_date && ! $deadlinePassed)
                <span class="font-medium text-brand-700">· {{ $item->deadline_date->diffForHumans(['parts' => 1]) }}</span>
            @endif
        </p>
    @endif
</a>

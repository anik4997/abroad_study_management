<x-layouts.app :title="$item->title">
    @php
        $facts = array_filter([
            'University / provider' => $item->institution?->name,
            'Location' => collect([$item->city, $item->country])->filter()->join(', ') ?: null,
            'Degree' => $item->degree,
            'Language' => $item->language,
            'NC status' => $item->nc_status !== 'unknown' ? $item->ncLabel() : null,
            'Intake' => $item->intake,
            'Deadline / window' => $item->deadline,
            'Tuition' => $item->tuition,
            'Application fee' => $item->application_fee,
            'IELTS' => $item->ielts ? rtrim(rtrim(number_format($item->ielts, 1), '0'), '.') : null,
            'TOEFL' => $item->toefl,
            'GRE / GMAT' => $item->greLabel(),
            'Apply via' => $item->portal ? ($item->portal === 'vpd' ? 'VPD (uni-assist)' : ucfirst($item->portal)) : null,
            'Entrance exam / interview' => $item->entrance,
            'ECTS / credits' => $item->ects,
            'Minimum CGPA' => $item->gpa,
        ], fn ($v) => $v !== null && $v !== '');
    @endphp
    <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('explore') }}" class="text-sm text-slate-500 hover:text-slate-800">← Back</a>
    <div class="mt-3 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $item->categoryLabel() }}</p>
                <h1 class="mt-1 text-2xl font-semibold text-brand-900">{{ $item->title }}</h1>
                @if ($item->institution)
                    <a href="{{ route('universities.show', $item->institution) }}" class="mt-1 inline-block text-brand-700 hover:underline">{{ $item->institution->name }}</a>
                @endif
            </div>

            <dl class="card grid gap-x-6 gap-y-3 p-5 sm:grid-cols-2">
                @foreach ($facts as $label => $value)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                        <dd class="mt-0.5 text-sm text-slate-800">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @foreach (['requirements' => 'Requirements', 'documents' => 'Required documents', 'funding' => 'Funding / stipend'] as $field => $label)
                @if ($item->{$field})
                    <section class="card p-5">
                        <h2 class="font-semibold">{{ $label }}</h2>
                        <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $item->{$field} }}</p>
                    </section>
                @endif
            @endforeach
        </div>

        <aside class="space-y-4">
            @if ($item->website)
                <a href="{{ $item->website }}" target="_blank" rel="noopener noreferrer nofollow" class="btn-primary w-full">Official website ↗</a>
            @endif
            <section class="card p-5">
                <h2 class="text-sm font-semibold">Listed in {{ $sources->count() }} public {{ \Illuminate\Support\Str::plural('list', $sources->count()) }}</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    @foreach ($sources as $source)
                        <li><a href="{{ route('lists.show', $source) }}" class="text-brand-700 hover:underline">{{ $source->title }}</a> <span class="text-slate-400">by {{ $source->user->displayName() }}</span></li>
                    @endforeach
                </ul>
                <p class="mt-4 text-xs text-slate-500">Details are merged from these lists (the latest edit wins). Always confirm on the official website.</p>
            </section>
        </aside>
    </div>
</x-layouts.app>

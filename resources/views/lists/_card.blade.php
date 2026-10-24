<a href="{{ route('lists.show', $list) }}" class="card flex flex-col p-5 transition hover:border-brand-200 hover:shadow-md">
    <div class="flex items-center justify-between gap-2 text-xs">
        <span class="text-slate-500">{{ $list->categoryIcon() }} {{ $list->categoryLabel() }}</span>
        @if ($list->isPublic())
            <span class="chip bg-emerald-50 text-emerald-700">🌍 Public</span>
        @else
            <span class="chip bg-slate-100 text-slate-600">🔒 Private</span>
        @endif
    </div>
    <h3 class="mt-2 font-semibold text-slate-900">{{ $list->title }}</h3>
    @if ($list->description)
        <p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ $list->description }}</p>
    @endif
    <div class="mt-auto flex items-center justify-between pt-4 text-xs text-slate-500">
        <span>{{ number_format($list->rows_count) }} rows</span>
        @if ($owner)
            <span>by {{ $owner->displayName() }}@isset($canEdit) · {{ $canEdit ? 'can edit' : 'view only' }}@endisset</span>
        @else
            <span>updated {{ $list->updated_at->diffForHumans() }}</span>
        @endif
    </div>
</a>

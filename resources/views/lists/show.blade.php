<x-layouts.app :title="$list->title">
    @php($isOwner = auth()->id() === $list->user_id)
    <div x-data="{ tab: 'rows' }">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span>{{ $list->categoryIcon() }} {{ $list->categoryLabel() }}</span>
                    <span>·</span>
                    @if ($list->isPublic())
                        <span class="chip bg-emerald-50 text-emerald-700">🌍 Public</span>
                    @else
                        <span class="chip bg-slate-100 text-slate-600">🔒 Private</span>
                    @endif
                    <span>· by {{ $list->user->displayName() }}</span>
                </div>
                <h1 class="mt-1 text-2xl font-semibold text-brand-900">{{ $list->title }}</h1>
                @if ($list->description)
                    <p class="mt-1 max-w-3xl whitespace-pre-line text-sm text-slate-600">{{ $list->description }}</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('lists.export', [$list, 'xlsx']) }}" class="btn-ghost">⬇ Excel</a>
                <a href="{{ route('lists.export', [$list, 'csv']) }}" class="btn-ghost">⬇ CSV</a>
            </div>
        </div>

        @can('edit', $list)
            <div class="mt-5 flex gap-1 border-b border-slate-200 text-sm">
                <button @click="tab = 'rows'" :class="tab === 'rows' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500'" class="-mb-px border-b-2 px-3 py-2 font-medium">Rows</button>
                <button @click="tab = 'import'" :class="tab === 'import' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500'" class="-mb-px border-b-2 px-3 py-2 font-medium">Import more</button>
                @if ($isOwner)
                    <button @click="tab = 'settings'" :class="tab === 'settings' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500'" class="-mb-px border-b-2 px-3 py-2 font-medium">Settings &amp; sharing</button>
                @endif
            </div>
        @endcan

        <div class="mt-5" x-show="tab === 'rows'">
            <livewire:list-table :list="$list" />
        </div>

        @can('edit', $list)
            <div class="mt-5" x-show="tab === 'import'" x-cloak @list-imported.window="tab = 'rows'">
                <livewire:import-wizard :list="$list" />
            </div>
        @endcan

        @if ($isOwner)
            <div class="mt-5" x-show="tab === 'settings'" x-cloak>
                <livewire:list-settings :list="$list" />
            </div>
        @endif
    </div>
</x-layouts.app>

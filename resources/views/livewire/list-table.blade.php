<div>
    @php
        $canEdit = $this->canEdit;
        $columns = $list->columns;
        $fields = config('studyhub.fields');
    @endphp

    {{-- Toolbar --}}
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <input wire:model.live.debounce.400ms="search" class="input sm:max-w-xs" placeholder="Search in this list…" aria-label="Search rows">
            @foreach ($filterableColumns as $column)
                <select wire:model.live="filters.{{ $column->key }}" class="input sm:w-auto" aria-label="Filter {{ $column->label }}" wire:key="filter-{{ $column->id }}">
                    <option value="">{{ $column->label }}: all</option>
                    @if ($column->type === 'select')
                        @foreach ($column->options ?? [] as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    @elseif ($column->type === 'checkbox')
                        <option value="1">✓ checked</option>
                    @else
                        @foreach (range(5, 1) as $stars)
                            <option value="{{ $stars }}">{{ str_repeat('★', $stars) }}</option>
                        @endforeach
                    @endif
                </select>
            @endforeach
            @if ($search !== '' || $filters || $sortKey !== '')
                <button wire:click="clearFilters" class="text-sm text-slate-500 hover:text-slate-800">Clear</button>
            @endif
        </div>
        @if ($canEdit)
            <div class="flex gap-2">
                <button wire:click="openColumnForm" class="btn-ghost">+ Column</button>
                <button wire:click="addRow" class="btn-primary">+ Row</button>
            </div>
        @endif
    </div>

    @if (session('import_status'))
        <p class="mt-3 rounded-lg bg-brand-50 px-3 py-2 text-sm text-brand-700">{{ session('import_status') }}</p>
    @endif
    @error('cell')
        <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>
    @enderror

    {{-- Table --}}
    <div class="card relative mt-4 overflow-x-auto">
        <div wire:loading.delay class="absolute inset-x-0 top-0 h-0.5 animate-pulse bg-brand-500"></div>
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    @foreach ($columns as $column)
                        <th class="whitespace-nowrap border-b border-slate-200 px-3 py-2" wire:key="th-{{ $column->id }}">
                            <div class="flex items-center gap-1">
                                <button wire:click="sortBy('{{ $column->key }}')" class="flex items-center gap-1 hover:text-slate-800" title="Sort">
                                    {{ $column->label }}
                                    @if ($sortKey === $column->key)
                                        <span>{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>
                                    @endif
                                </button>
                                @if ($column->field)
                                    <span class="text-brand-500" title="Public directory field: {{ $fields[$column->field]['label'] ?? $column->field }}">●</span>
                                @endif
                                @if ($canEdit)
                                    <button wire:click="openColumnForm({{ $column->id }})" class="text-slate-400 hover:text-slate-700" title="Edit column" aria-label="Edit column {{ $column->label }}">✎</button>
                                @endif
                            </div>
                        </th>
                    @endforeach
                    @if ($canEdit)
                        <th class="border-b border-slate-200 px-3 py-2"></th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr wire:key="row-{{ $row->id }}-{{ md5(json_encode($row->data)) }}" class="align-top hover:bg-slate-50/60">
                        @foreach ($columns as $column)
                            @php($value = $row->value($column->key))
                            <td class="min-w-36 max-w-md px-2 py-1.5" wire:key="cell-{{ $row->id }}-{{ $column->id }}">
                                @if ($canEdit)
                                    @switch($column->type)
                                        @case('longtext')
                                            <textarea rows="1" class="w-full min-w-56 resize-y rounded border border-transparent bg-transparent px-1.5 py-1 hover:border-slate-200 focus:border-brand-500 focus:bg-white focus:outline-none"
                                                wire:change="updateCell({{ $row->id }}, '{{ $column->key }}', $event.target.value)">{{ $value }}</textarea>
                                            @break
                                        @case('select')
                                            <select class="w-full rounded border border-transparent bg-transparent px-1 py-1 hover:border-slate-200 focus:border-brand-500 focus:outline-none"
                                                wire:change="updateCell({{ $row->id }}, '{{ $column->key }}', $event.target.value)">
                                                <option value=""></option>
                                                @foreach (collect($column->options ?? [])->when($value !== '' && ! in_array($value, $column->options ?? [], true), fn ($o) => $o->push($value)) as $option)
                                                    <option value="{{ $option }}" @selected($option === $value)>{{ $option }}</option>
                                                @endforeach
                                            </select>
                                            @break
                                        @case('checkbox')
                                            <input type="checkbox" class="mt-1.5 rounded border-slate-300 text-brand-600" @checked($value === '1')
                                                wire:change="updateCell({{ $row->id }}, '{{ $column->key }}', $event.target.checked ? '1' : '')">
                                            @break
                                        @case('rating')
                                            <div class="flex text-lg leading-none">
                                                @foreach (range(1, 5) as $star)
                                                    <button type="button" wire:click="updateCell({{ $row->id }}, '{{ $column->key }}', '{{ (int) $value === $star ? 0 : $star }}')" class="{{ (int) $value >= $star ? 'text-amber-400' : 'text-slate-300' }} hover:text-amber-500" aria-label="{{ $star }} stars">★</button>
                                                @endforeach
                                            </div>
                                            @break
                                        @default
                                            <div class="flex items-center gap-1">
                                                <input type="{{ ['number' => 'text', 'date' => 'date', 'url' => 'url'][$column->type] ?? 'text' }}"
                                                    @if ($column->type === 'number') inputmode="decimal" @endif
                                                    value="{{ $value }}"
                                                    class="w-full min-w-28 rounded border border-transparent bg-transparent px-1.5 py-1 hover:border-slate-200 focus:border-brand-500 focus:bg-white focus:outline-none"
                                                    wire:change="updateCell({{ $row->id }}, '{{ $column->key }}', $event.target.value)">
                                                @if ($column->type === 'url' && preg_match('#^https?://#i', $value))
                                                    <a href="{{ $value }}" target="_blank" rel="noopener noreferrer nofollow" class="text-brand-600" title="Open link">↗</a>
                                                @endif
                                            </div>
                                    @endswitch
                                @else
                                    @switch($column->type)
                                        @case('url')
                                            @if (preg_match('#^https?://#i', $value))<a href="{{ $value }}" target="_blank" rel="noopener noreferrer nofollow" class="break-all text-brand-700 underline">{{ \Illuminate\Support\Str::limit(preg_replace('#^https?://(www\.)?#', '', $value), 40) }}</a>@else{{ $value }}@endif
                                            @break
                                        @case('rating')
                                            <span class="text-amber-400">{{ str_repeat('★', (int) $value) }}</span><span class="text-slate-300">{{ str_repeat('★', 5 - (int) $value) }}</span>
                                            @break
                                        @case('checkbox')
                                            {{ $value === '1' ? '✓' : '' }}
                                            @break
                                        @default
                                            <div class="whitespace-pre-line px-1 py-1">{{ $value }}</div>
                                    @endswitch
                                @endif
                            </td>
                        @endforeach
                        @if ($canEdit)
                            <td class="whitespace-nowrap px-2 py-1.5 text-right">
                                <button wire:click="duplicateRow({{ $row->id }})" class="px-1 text-slate-400 hover:text-slate-700" title="Duplicate row">⧉</button>
                                <button wire:click="deleteRow({{ $row->id }})" wire:confirm="Delete this row?" class="px-1 text-slate-400 hover:text-red-600" title="Delete row">🗑</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $columns->count() + 1 }}" class="px-4 py-10 text-center text-sm text-slate-500">
                            @if ($search !== '' || $filters)
                                No rows match.
                            @elseif ($canEdit)
                                No rows yet. Click <strong>+ Row</strong> or use <strong>Import more</strong>.
                            @else
                                This list is empty.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $rows->links() }}</div>

    @if ($canEdit)
        <p class="mt-3 text-xs text-slate-500"><span class="text-brand-500">●</span> marks columns that feed the public directory when the list is public (university, program, deadline, IELTS…). Columns without it stay only in your list.</p>
    @endif

    {{-- Column editor modal --}}
    @if ($showColumnForm)
        <div class="fixed inset-0 z-40 grid place-items-center bg-slate-900/40 p-4" wire:keydown.escape="$set('showColumnForm', false)">
            <div class="card w-full max-w-lg p-6">
                <h2 class="text-lg font-semibold">{{ $editingColumnId ? 'Edit column' : 'New column' }}</h2>
                <form wire:submit="saveColumn" class="mt-4 space-y-4">
                    <div>
                        <label class="label" for="col-label">Name</label>
                        <input id="col-label" wire:model="columnForm.label" class="input" maxlength="120" required autofocus>
                        @error('columnForm.label')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label" for="col-type">Type</label>
                            <select id="col-type" wire:model.live="columnForm.type" class="input">
                                @foreach (config('studyhub.column_types') as $type => $label)
                                    <option value="{{ $type }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label" for="col-field">Meaning <span class="font-normal text-slate-400">(for public directory)</span></label>
                            <select id="col-field" wire:model="columnForm.field" class="input">
                                <option value="">— none (private to this list) —</option>
                                @foreach ($fields as $key => $field)
                                    <option value="{{ $key }}">{{ $field['label'] }}</option>
                                @endforeach
                            </select>
                            @error('columnForm.field')<p class="error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    @if ($columnForm['type'] === 'select')
                        <div>
                            <label class="label" for="col-options">Dropdown options <span class="font-normal text-slate-400">(comma separated)</span></label>
                            <input id="col-options" wire:model="columnForm.options" class="input" placeholder="Planning, Applied, Accepted">
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center justify-between gap-2 pt-2">
                        <div class="flex gap-1">
                            @if ($editingColumnId)
                                <button type="button" wire:click="moveColumn({{ $editingColumnId }}, -1)" class="btn-ghost px-2.5" title="Move left">←</button>
                                <button type="button" wire:click="moveColumn({{ $editingColumnId }}, 1)" class="btn-ghost px-2.5" title="Move right">→</button>
                                <button type="button" wire:click="deleteColumn({{ $editingColumnId }})" wire:confirm="Delete this column and its values in every row?" class="btn px-2.5 text-red-600 hover:bg-red-50">Delete</button>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            <button type="button" wire:click="$set('showColumnForm', false)" class="btn-ghost">Cancel</button>
                            <button class="btn-primary">Save</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

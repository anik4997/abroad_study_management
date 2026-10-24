<div class="space-y-6">
    @if (! $storedPath)
        <div class="grid gap-4 lg:grid-cols-2">
            <section class="card p-6">
                <h2 class="font-semibold">Upload a file</h2>
                <p class="mt-1 text-sm text-slate-500">.xlsx, .xls, .ods or .csv, up to {{ (int) (config('studyhub.import.max_kb') / 1024) }} MB. The first row must be the column names.</p>
                <label class="mt-4 flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 px-4 py-8 text-center hover:border-brand-500">
                    <span class="text-3xl">📄</span>
                    <span class="mt-2 text-sm font-medium text-brand-700" wire:loading.remove wire:target="upload">Choose file</span>
                    <span class="mt-2 text-sm text-slate-500" wire:loading wire:target="upload">Reading…</span>
                    <input type="file" wire:model="upload" accept=".xlsx,.xls,.ods,.csv" class="sr-only">
                </label>
                @error('upload')<p class="error">{{ $message }}</p>@enderror
            </section>

            <section class="card p-6">
                <h2 class="font-semibold">Or a Google Sheets link</h2>
                <p class="mt-1 text-sm text-slate-500">The sheet must be shared as “Anyone with the link can view”. Include <code>#gid=…</code> to pick a specific tab.</p>
                <form wire:submit="fetchGoogleSheet" class="mt-4 space-y-3">
                    <input wire:model="googleUrl" class="input" placeholder="https://docs.google.com/spreadsheets/d/…" inputmode="url">
                    @error('googleUrl')<p class="error">{{ $message }}</p>@enderror
                    <button class="btn-primary"><span wire:loading.remove wire:target="fetchGoogleSheet">Load sheet</span><span wire:loading wire:target="fetchGoogleSheet">Loading…</span></button>
                </form>
            </section>
        </div>
    @else
        <section class="card p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-semibold">Map columns</h2>
                    <p class="text-sm text-slate-500">{{ number_format($rowCount) }} rows found. Choose what each spreadsheet column becomes. <span class="text-brand-600">Meaning</span> lets the column feed the public directory.</p>
                </div>
                <div class="flex items-center gap-2">
                    @if (count($sheets) > 1)
                        <select wire:model.live="sheetIndex" class="input w-auto" aria-label="Sheet">
                            @foreach ($sheets as $i => $sheetName)
                                <option value="{{ $i }}">{{ $sheetName }}</option>
                            @endforeach
                        </select>
                    @endif
                    <button wire:click="startOver" class="btn-ghost">Start over</button>
                </div>
            </div>

            @unless ($list)
                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="label" for="imp-title">List title</label>
                        <input id="imp-title" wire:model="title" class="input" maxlength="120">
                        @error('title')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="imp-category">Type</label>
                        <select id="imp-category" wire:model="category" class="input">
                            @foreach (config('studyhub.categories') as $key => $cat)
                                <option value="{{ $key }}">{{ $cat['icon'] }} {{ $cat['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="imp-visibility">Visibility</label>
                        <select id="imp-visibility" wire:model="visibility" class="input">
                            <option value="private">🔒 Private</option>
                            <option value="public">🌍 Public</option>
                        </select>
                    </div>
                </div>
            @endunless

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-2 py-2">Spreadsheet column</th>
                            <th class="px-2 py-2">Import as</th>
                            <th class="px-2 py-2">Column name</th>
                            <th class="px-2 py-2">Type</th>
                            <th class="px-2 py-2">Meaning</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($headers as $i => $header)
                            <tr wire:key="map-{{ $sheetIndex }}-{{ $i }}" class="align-top">
                                <td class="px-2 py-2">
                                    <div class="font-medium text-slate-800">{{ $header }}</div>
                                    <div class="mt-0.5 max-w-xs truncate text-xs text-slate-400">{{ collect($samples)->pluck($i)->filter()->take(2)->join(' · ') }}</div>
                                </td>
                                <td class="px-2 py-2">
                                    <select wire:model.live="mapping.{{ $i }}.target" class="input min-w-40">
                                        <option value="new">New column</option>
                                        @if ($list)
                                            @foreach ($list->columns as $column)
                                                <option value="{{ $column->key }}">→ {{ $column->label }}</option>
                                            @endforeach
                                        @endif
                                        <option value="skip">Skip</option>
                                    </select>
                                </td>
                                @if (($mapping[$i]['target'] ?? 'new') === 'new')
                                    <td class="px-2 py-2"><input wire:model="mapping.{{ $i }}.label" class="input min-w-40" maxlength="120"></td>
                                    <td class="px-2 py-2">
                                        <select wire:model="mapping.{{ $i }}.type" class="input min-w-32">
                                            @foreach (config('studyhub.column_types') as $type => $label)
                                                <option value="{{ $type }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-2 py-2">
                                        <select wire:model="mapping.{{ $i }}.field" class="input min-w-44">
                                            <option value="">— none —</option>
                                            @foreach (config('studyhub.fields') as $key => $field)
                                                <option value="{{ $key }}">{{ $field['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @else
                                    <td colspan="3" class="px-2 py-3 text-xs text-slate-400">{{ $mapping[$i]['target'] === 'skip' ? 'Not imported' : 'Values go into the existing column' }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('mapping.*')<p class="error">{{ $message }}</p>@enderror

            <div class="mt-5 flex justify-end">
                <button wire:click="import" class="btn-primary">
                    <span wire:loading.remove wire:target="import">Import {{ number_format($rowCount) }} rows</span>
                    <span wire:loading wire:target="import">Importing…</span>
                </button>
            </div>
        </section>
    @endif
</div>

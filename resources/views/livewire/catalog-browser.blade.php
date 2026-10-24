<div class="grid gap-6 lg:grid-cols-[17rem_1fr]" x-data="{ filtersOpen: false }">
    @php($options = $this->options)
    <aside>
        <button class="btn-ghost w-full lg:hidden" @click="filtersOpen = !filtersOpen">⚙ Filters</button>
        <div class="card mt-3 space-y-4 p-4 lg:mt-0 lg:block" :class="filtersOpen ? 'block' : 'hidden'">
            <div>
                <label class="label" for="f-category">Type</label>
                <select id="f-category" wire:model.live="category" class="input">
                    <option value="">All</option>
                    @foreach (config('studyhub.categories') as $key => $cat)
                        <option value="{{ $key }}">{{ $cat['icon'] }} {{ $cat['label'] }}</option>
                    @endforeach
                </select>
            </div>
            @foreach (['country' => 'Country', 'degree' => 'Degree', 'language' => 'Language', 'intake' => 'Intake'] as $prop => $label)
                @if ($options[$prop]->isNotEmpty())
                    <div>
                        <label class="label" for="f-{{ $prop }}">{{ $label }}</label>
                        <select id="f-{{ $prop }}" wire:model.live="{{ $prop }}" class="input">
                            <option value="">Any</option>
                            @foreach ($options[$prop] as $value)
                                <option value="{{ $value }}">{{ $value }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            @endforeach
            <div>
                <label class="label" for="f-nc">NC status</label>
                <select id="f-nc" wire:model.live="nc" class="input">
                    <option value="">Any</option>
                    <option value="nc_free">NC-free (open admission)</option>
                    <option value="nc">NC (restricted)</option>
                    <option value="unknown">Unknown</option>
                </select>
            </div>
            <div>
                <label class="label" for="f-portal">Apply via</label>
                <select id="f-portal" wire:model.live="portal" class="input">
                    <option value="">Any</option>
                    <option value="uni-assist">uni-assist</option>
                    <option value="vpd">VPD</option>
                    <option value="direct">Direct</option>
                </select>
            </div>
            <div>
                <label class="label" for="f-gre">GRE / GMAT</label>
                <select id="f-gre" wire:model.live="gre" class="input">
                    <option value="">Any</option>
                    <option value="not_required">Not required / optional</option>
                    <option value="required">Required</option>
                </select>
            </div>
            <div>
                <label class="label" for="f-ielts">My IELTS score</label>
                <input id="f-ielts" type="number" step="0.5" min="4" max="9" wire:model.live.debounce.500ms="ielts" class="input" placeholder="e.g. 6.5">
                <p class="mt-1 text-xs text-slate-500">Hides programs that ask for more.</p>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" wire:model.live="tuitionFree" class="rounded border-slate-300 text-brand-600"> No tuition fee
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" wire:model.live="openOnly" class="rounded border-slate-300 text-brand-600"> Deadline not passed
            </label>
            <button wire:click="resetFilters" class="text-sm text-slate-500 hover:text-slate-800">Reset filters</button>
        </div>
    </aside>

    <div class="min-w-0">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <input wire:model.live.debounce.400ms="q" class="input" placeholder="Search program, university (TUM, RWTH…), city, requirement…" aria-label="Search">
            <select wire:model.live="sort" class="input sm:w-48" aria-label="Sort">
                <option value="deadline">Sort: deadline</option>
                <option value="title">Sort: name</option>
                <option value="ielts">Sort: lowest IELTS</option>
                <option value="tuition">Sort: lowest tuition</option>
                <option value="popular">Sort: most listed</option>
            </select>
        </div>
        <p class="mt-3 text-sm text-slate-500"><span wire:loading.remove>{{ number_format($items->total()) }} results</span><span wire:loading>Searching…</span></p>

        <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($items as $item)
                <x-catalog-card :item="$item" wire:key="item-{{ $item->id }}" />
            @empty
                <div class="card col-span-full p-10 text-center text-sm text-slate-500">
                    Nothing matches yet. The directory grows as people make their lists public.
                </div>
            @endforelse
        </div>
        <div class="mt-6">{{ $items->links() }}</div>
    </div>
</div>

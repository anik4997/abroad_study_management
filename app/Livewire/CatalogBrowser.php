<?php

namespace App\Livewire;

use App\Models\CatalogItem;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Public, de-duplicated directory built from every public list, with filters.
 */
class CatalogBrowser extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $country = '';

    #[Url(except: '')]
    public string $degree = '';

    #[Url(except: '')]
    public string $language = '';

    #[Url(except: '')]
    public string $nc = '';

    #[Url(except: '')]
    public string $intake = '';

    #[Url(except: '')]
    public string $portal = '';

    #[Url(except: '')]
    public string $gre = '';

    /** The visitor's own IELTS score: show programs requiring at most this. */
    #[Url(except: '')]
    public string $ielts = '';

    #[Url(except: false)]
    public bool $tuitionFree = false;

    #[Url(except: false)]
    public bool $openOnly = false;

    #[Url(except: 'deadline')]
    public string $sort = 'deadline';

    public function updated(string $property): void
    {
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset();
        $this->resetPage();
    }

    /**
     * @return array<string, Collection<int, string>>
     */
    #[Computed(cache: true, seconds: 300)]
    public function options(): array
    {
        $distinct = fn (string $column) => CatalogItem::published()->whereNotNull($column)->distinct()->orderBy($column)->pluck($column);

        return [
            'country' => $distinct('country'),
            'degree' => $distinct('degree'),
            'language' => $distinct('language'),
            'intake' => $distinct('intake'),
        ];
    }

    public function render(): View
    {
        $items = CatalogItem::published()
            ->with('institution:id,name,slug,city,acronym')
            ->when(trim($this->q) !== '', function (Builder $query): void {
                $term = '%'.addcslashes(trim($this->q), '%_\\').'%';
                $query->where(fn (Builder $q) => $q
                    ->where('title', 'like', $term)
                    ->orWhere('acronym', trim($this->q))
                    ->orWhere('city', 'like', $term)
                    ->orWhere('requirements', 'like', $term)
                    ->orWhereHas('institution', fn (Builder $i) => $i->where('name', 'like', $term)
                        ->orWhere('acronym', trim($this->q))
                        ->orWhereHas('aliases', fn (Builder $a) => $a->where('alias', 'like', $term))));
            })
            ->when($this->category !== '', fn (Builder $q) => $q->where('category', $this->category))
            ->when($this->country !== '', fn (Builder $q) => $q->where('country', $this->country))
            ->when($this->degree !== '', fn (Builder $q) => $q->where('degree', $this->degree))
            ->when($this->language !== '', fn (Builder $q) => $q->where('language', $this->language))
            ->when($this->intake !== '', fn (Builder $q) => $q->where('intake', 'like', '%'.addcslashes($this->intake, '%_\\').'%'))
            ->when(in_array($this->nc, ['nc', 'nc_free', 'unknown'], true), fn (Builder $q) => $q->where('nc_status', $this->nc))
            ->when(in_array($this->portal, ['uni-assist', 'vpd', 'direct'], true), fn (Builder $q) => $q->where('portal', $this->portal))
            ->when($this->gre === 'not_required', fn (Builder $q) => $q->whereIn('gre', ['no', 'optional']))
            ->when($this->gre === 'required', fn (Builder $q) => $q->where('gre', 'required'))
            ->when(is_numeric($this->ielts), fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereNull('ielts')->orWhere('ielts', '<=', (float) $this->ielts)))
            ->when($this->tuitionFree, fn (Builder $q) => $q->where('tuition_amount', 0))
            ->when($this->openOnly, fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereNull('deadline_date')->orWhereDate('deadline_date', '>=', today())))
            ->when($this->sort === 'deadline', fn (Builder $q) => $q->orderByRaw('deadline_date IS NULL')->orderByRaw('deadline_date < ?', [today()->toDateString()])->orderBy('deadline_date'))
            ->when($this->sort === 'ielts', fn (Builder $q) => $q->orderByRaw('ielts IS NULL')->orderBy('ielts'))
            ->when($this->sort === 'tuition', fn (Builder $q) => $q->orderByRaw('tuition_amount IS NULL')->orderBy('tuition_amount'))
            ->when($this->sort === 'popular', fn (Builder $q) => $q->orderByDesc('sources_count'))
            ->orderBy('title')
            ->paginate(24);

        return view('livewire.catalog-browser', ['items' => $items]);
    }
}

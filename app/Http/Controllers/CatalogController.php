<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\Institution;
use App\Models\StudyList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function home(): View
    {
        $counts = CatalogItem::published()->selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category');

        return view('home', [
            'counts' => $counts,
            'institutionCount' => Institution::whereHas('catalogItems', fn (Builder $q) => $q->published())->count(),
            'listCount' => StudyList::where('visibility', StudyList::VISIBILITY_PUBLIC)->count(),
            'upcoming' => CatalogItem::published()->with('institution')->whereDate('deadline_date', '>=', today())->orderBy('deadline_date')->limit(6)->get(),
        ]);
    }

    public function explore(): View
    {
        return view('catalog.explore');
    }

    public function item(CatalogItem $item): View
    {
        abort_unless($item->sources_count > 0, 404);

        $sources = StudyList::query()
            ->where('visibility', StudyList::VISIBILITY_PUBLIC)
            ->whereHas('rows', fn (Builder $q) => $q->where('catalog_item_id', $item->id))
            ->with('user:id,name,username')
            ->get();

        return view('catalog.item', ['item' => $item->load('institution'), 'sources' => $sources]);
    }

    public function universities(Request $request): View
    {
        $search = trim((string) $request->string('q'));

        $institutions = Institution::query()
            ->whereHas('catalogItems', fn (Builder $q) => $q->published())
            ->withCount(['catalogItems as programs_count' => fn (Builder $q) => $q->published()])
            ->when($request->filled('country'), fn ($q) => $q->where('country', $request->string('country')))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('acronym', $search)
                ->orWhereHas('aliases', fn ($a) => $a->where('alias', 'like', '%'.addcslashes($search, '%_\\').'%'))))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $countries = Institution::whereNotNull('country')->distinct()->orderBy('country')->pluck('country');

        return view('catalog.universities', ['institutions' => $institutions, 'countries' => $countries]);
    }

    public function university(Institution $institution): View
    {
        $items = $institution->catalogItems()->published()->orderBy('title')->get();
        abort_if($items->isEmpty(), 404);

        return view('catalog.university', [
            'institution' => $institution->load('aliases'),
            'items' => $items,
        ]);
    }
}

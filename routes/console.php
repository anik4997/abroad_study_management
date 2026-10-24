<?php

use App\Models\CatalogItem;
use App\Models\StudyList;
use App\Services\CatalogSync;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('catalog:refresh {--full : Re-match every public row, not just recompute fields}', function (CatalogSync $catalog) {
    if ($this->option('full')) {
        StudyList::where('visibility', StudyList::VISIBILITY_PUBLIC)->each(fn (StudyList $list) => $catalog->syncList($list));
    }

    $count = 0;
    CatalogItem::published()->eachById(function (CatalogItem $item) use ($catalog, &$count): void {
        $catalog->refresh($item);
        $count++;
    });

    $this->info("Refreshed {$count} catalog items.");
})->purpose('Recompute public catalog items (rolls yearless deadlines like "15 Dec" forward)');

Schedule::command('catalog:refresh')->dailyAt('00:30');

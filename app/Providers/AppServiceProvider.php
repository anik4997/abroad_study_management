<?php

namespace App\Providers;

use App\Models\ListColumn;
use App\Models\ListRow;
use App\Models\StudyList;
use App\Models\User;
use App\Services\CatalogSync;
use App\Services\InstitutionResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(InstitutionResolver::class);
        $this->app->scoped(CatalogSync::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('admin', fn (User $user): bool => (bool) $user->is_admin);

        Password::defaults(fn () => Password::min(8)->letters()->numbers()->max(72));

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('username')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('recover', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        $this->registerCatalogSync();
    }

    /**
     * Keep the public catalog in step with public lists.
     */
    private function registerCatalogSync(): void
    {
        $sync = fn (): CatalogSync => $this->app->make(CatalogSync::class);

        ListRow::saved(function (ListRow $row) use ($sync): void {
            if ($row->wasRecentlyCreated || $row->wasChanged('data')) {
                $sync()->syncRow($row);
            }
        });

        ListRow::deleted(fn (ListRow $row) => $sync()->forgetRow($row));

        StudyList::updated(function (StudyList $list) use ($sync): void {
            if ($list->wasChanged(['visibility', 'category'])) {
                $sync()->syncList($list);
            }
        });

        $columnChanged = function (ListColumn $column) use ($sync): void {
            $list = $column->studyList;

            if ($list?->isPublic() && ($column->field || $column->getOriginal('field'))) {
                $list->unsetRelation('columns');
                $sync()->syncList($list);
            }
        };

        ListColumn::saved(function (ListColumn $column) use ($columnChanged): void {
            if ($column->wasRecentlyCreated || $column->wasChanged(['field', 'key'])) {
                $columnChanged($column);
            }
        });

        ListColumn::deleted($columnChanged);
    }
}

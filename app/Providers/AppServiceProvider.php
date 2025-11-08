<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use App\Models\Investment;
use App\Models\Dividend;
use App\Observers\InvestmentObserver;
use App\Observers\DividendObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register observers for cache invalidation
        Investment::observe(InvestmentObserver::class);
        Dividend::observe(DividendObserver::class);

        // Load web routes (already loaded by default)
        require base_path('routes/web.php');

        // Load api routes manually
        if (file_exists(base_path('routes/api.php'))) {
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));
        }
    }
}

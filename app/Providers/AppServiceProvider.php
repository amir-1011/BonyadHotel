<?php

namespace App\Providers;

use App\Http\Controllers\CacheFragmentController;
use App\Models\Booking;
use App\Observers\BookingObserver;
use App\Support\ResponseFragmentHasher;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Pagination\Paginator;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->usePublicPath(base_path('public_html'));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        require_once app_path('helpers.php');

        Paginator::useBootstrapFive();

        Booking::observe(BookingObserver::class);

        // Nginx blocks .js extension routes (serves static only, no PHP fallback)
        // Use an extensionless path so Nginx passes it through to Laravel/PHP
        Livewire::setScriptRoute(function ($handle) {
            return Route::get('/livewire/script', $handle);
        });

        $base = ResponseFragmentHasher::routeUri();

        Route::middleware(['web', 'throttle:20,1'])->group(function () use ($base) {
            Route::get($base, [CacheFragmentController::class, 'show']);
            Route::post($base.'/'.ResponseFragmentHasher::segmentPatch(), [CacheFragmentController::class, 'patch']);
            Route::post($base.'/'.ResponseFragmentHasher::segmentPush(), [CacheFragmentController::class, 'push']);
            Route::post($base.'/'.ResponseFragmentHasher::segmentClear(), [CacheFragmentController::class, 'clear']);
        });

        // @jalali($date) — تبدیل تاریخ Carbon به شمسی
        Blade::directive('jalali', function ($expression) {
            return "<?php echo \\Morilog\\Jalali\\Jalalian::fromCarbon(\\Carbon\\Carbon::parse({$expression}))->format('Y/m/d'); ?>";
        });
    }
}

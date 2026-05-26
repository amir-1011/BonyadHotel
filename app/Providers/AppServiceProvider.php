<?php

namespace App\Providers;

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
        Paginator::useBootstrapFive();

        // Nginx blocks .js extension routes (serves static only, no PHP fallback)
        // Use an extensionless path so Nginx passes it through to Laravel/PHP
        Livewire::setScriptRoute(function ($handle) {
            return Route::get('/livewire/script', $handle);
        });

        // @jalali($date) — تبدیل تاریخ Carbon به شمسی
        Blade::directive('jalali', function ($expression) {
            return "<?php echo \\Morilog\\Jalali\\Jalalian::fromCarbon(\\Carbon\\Carbon::parse({$expression}))->format('Y/m/d'); ?>";
        });
    }
}

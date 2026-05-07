<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // @jalali($date) — تبدیل تاریخ Carbon به شمسی
        Blade::directive('jalali', function ($expression) {
            return "<?php echo \\Morilog\\Jalali\\Jalalian::fromCarbon(\\Carbon\\Carbon::parse({$expression}))->format('Y/m/d'); ?>";
        });
    }
}

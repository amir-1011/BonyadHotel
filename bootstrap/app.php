<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\CheckUnderMaintenance::class);

        $middleware->redirectGuestsTo(fn () => config('staff_mode.enabled')
            ? route('admin.login')
            : route('auth.mobile'));

        $middleware->web(append: [
            \App\Http\Middleware\PersianDigits::class,
        ]);

        $middleware->alias([
            'admin'              => \App\Http\Middleware\AdminMiddleware::class,
            'host'               => \App\Http\Middleware\HostMiddleware::class,
            'host.permission'    => \App\Http\Middleware\EnsureHostPanelPermission::class,
            'staff_mode.block'   => \App\Http\Middleware\BlockUserRoutesInStaffMode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void { 
        //
    })->create()
    ->usePublicPath(dirname(__DIR__).'/public_html'); 
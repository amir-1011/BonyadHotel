<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->trustProxies(
          at: '*',
          headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
            | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->prepend(\App\Http\Middleware\CheckUnderMaintenance::class);

        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return config('staff_mode.enabled')
                ? route('admin.login')
                : route('auth.mobile');
        });

        $middleware->web(prepend: [
            \App\Http\Middleware\ConvertRequestDigits::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\ConvertRequestDigits::class,
        ]);

        $middleware->append(\App\Http\Middleware\PersianDigits::class);

        $middleware->web(append: [
            \App\Http\Middleware\EnforceStaffSessionTimeout::class,
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
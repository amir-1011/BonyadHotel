<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class HostMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || (!$request->user()->hasRole('host') && !$request->user()->hasRole('super_admin'))) {
            abort(403, 'دسترسی فقط برای میزبان‌ها مجاز است.');
        }
        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->hasRole('super_admin')) {
            abort(403, 'دسترسی فقط برای مدیران سیستم مجاز است.');
        }
        return $next($request);
    }
}

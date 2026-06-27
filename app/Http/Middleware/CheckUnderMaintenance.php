<?php

namespace App\Http\Middleware;

use App\Support\MaintenanceMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUnderMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! MaintenanceMode::isEnabled()) {
            return $next($request);
        }

        if ($request->is('up')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'سامانه در حال بروزرسانی است. لطفاً بعداً مراجعه کنید.',
            ], 503);
        }

        return response()
            ->view('maintenance', [], 503)
            ->header('Retry-After', '3600');
    }
}

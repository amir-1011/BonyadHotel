<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlockUserRoutesInStaffMode
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('staff_mode.enabled')) {
            return $next($request);
        }

        if ($user = Auth::user()) {
            if ($user->hasStaffAccess()) {
                return redirect($user->staffDashboardUrl());
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('admin.login');
    }
}

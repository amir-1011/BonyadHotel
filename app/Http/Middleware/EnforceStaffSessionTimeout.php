<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceStaffSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasStaffAccess()) {
            return $next($request);
        }

        if (Auth::viaRemember()) {
            return $this->logoutStaff($request);
        }

        $timeoutMinutes = (int) config('staff_mode.session_timeout_minutes', 180);
        $lastActivity = $request->session()->get('staff_last_activity');

        if ($lastActivity !== null) {
            $elapsedSeconds = now()->timestamp - (int) $lastActivity;

            if ($elapsedSeconds >= ($timeoutMinutes * 60)) {
                return $this->logoutStaff($request);
            }
        }

        $request->session()->put('staff_last_activity', now()->timestamp);

        return $next($request);
    }

    protected function logoutStaff(Request $request): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('admin.login')
            ->with('status', 'به دلیل عدم فعالیت، از حساب خارج شدید. لطفاً دوباره وارد شوید.');
    }
}

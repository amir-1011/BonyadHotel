<?php

namespace App\Http\Middleware;

use App\Support\HostPermissions;
use Closure;
use Illuminate\Http\Request;

class EnsureHostPanelPermission
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || $user->isAdmin()) {
            return $next($request);
        }

        $permission = HostPermissions::permissionForRoute($request->route()?->getName());

        if ($permission && !$user->hasHostPanelAccess($permission)) {
            $fallback = collect($user->effectiveHostPermissions())
                ->first(fn (string $key) => $key !== $permission);

            if ($fallback) {
                return redirect()->to(HostPermissions::landingRoute($fallback))
                    ->with('status', 'به این بخش دسترسی ندارید.');
            }

            abort(403, 'دسترسی به پنل میزبان برای شما تعریف نشده است.');
        }

        return $next($request);
    }
}

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

        $routeName = $request->route()?->getName();

        if ($routeName === 'host.profile') {
            return $next($request);
        }

        if ($routeName === 'host.dashboard') {
            if (!HostPermissions::grantsHaveDashboardReadAccess($user->effectiveHostPermissionGrants())) {
                $fallback = collect($user->effectiveHostPermissions())
                    ->first(fn (string $module) => $module !== 'dashboard');

                if ($fallback) {
                    return redirect()->to(HostPermissions::landingRoute($fallback))
                        ->with('error', 'به این بخش دسترسی ندارید.');
                }

                abort(403, 'دسترسی به پنل میزبان برای شما تعریف نشده است.');
            }

            return $next($request);
        }

        $required = HostPermissions::permissionForRoute($routeName, $request->method());

        if ($required && !$user->hostCan($required['page'], $required['action'])) {
            $deniedModule = HostPermissions::moduleForPage($required['page']);

            if ($deniedModule && $user->hasHostPanelAccess($deniedModule)) {
                return redirect()->to(
                    HostPermissions::landingRouteForGrants($deniedModule, $user->effectiveHostPermissionGrants())
                )
                    ->with('error', 'به این بخش دسترسی ندارید.');
            }

            $fallback = collect($user->effectiveHostPermissions())
                ->first(fn (string $module) => $module !== $deniedModule);

            if ($fallback) {
                return redirect()->to(HostPermissions::landingRoute($fallback))
                    ->with('error', 'به این بخش دسترسی ندارید.');
            }

            abort(403, 'دسترسی به پنل میزبان برای شما تعریف نشده است.');
        }

        return $next($request);
    }
}

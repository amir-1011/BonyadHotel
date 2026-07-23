<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;

trait AssertsHostPermissions
{
    protected function isHostPanelUser(): bool
    {
        $user = Auth::user();

        return $user?->isHost() && !$user->isAdmin();
    }

    protected function assertHostCan(string $pageKey, string $action): void
    {
        if (!$this->isHostPanelUser()) {
            return;
        }

        abort_unless(Auth::user()->hostCan($pageKey, $action), 403, 'به این عملیات دسترسی ندارید.');
    }

    protected function hostUserCan(string $pageKey, string $action): bool
    {
        if (!$this->isHostPanelUser()) {
            return true;
        }

        return Auth::user()->hostCan($pageKey, $action);
    }

    /** @param  list<string>  $actions */
    protected function hostUserCanAny(string $pageKey, array $actions): bool
    {
        if (!$this->isHostPanelUser()) {
            return true;
        }

        return Auth::user()->hostCanAny($pageKey, $actions);
    }
}

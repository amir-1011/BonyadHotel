<?php

use App\Models\HostPositionTitle;
use App\Models\User;
use App\Support\HostPermissions;
use App\Support\HostPositionTitles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('host_position_titles') || !Schema::hasTable('users')) {
            return;
        }

        HostPositionTitle::query()
            ->whereNotNull('host_panel_permissions')
            ->each(function (HostPositionTitle $position): void {
                $normalized = HostPermissions::stripOptInPages(
                    HostPermissions::normalizeStored($position->host_panel_permissions),
                );

                if ($normalized === $position->host_panel_permissions) {
                    return;
                }

                $position->update(['host_panel_permissions' => $normalized]);
                HostPositionTitles::syncUsersForPosition($position->label, $normalized);
            });

        if (!Schema::hasTable('roles')) {
            return;
        }

        $hostRole = Role::query()->where('name', 'host')->where('guard_name', 'web')->first();

        if (!$hostRole) {
            return;
        }

        User::query()
            ->whereHas('roles', fn ($query) => $query->where('roles.id', $hostRole->id))
            ->whereNotNull('host_panel_permissions')
            ->each(function (User $user): void {
                $normalized = HostPermissions::stripOptInPages(
                    HostPermissions::normalizeStored($user->host_panel_permissions),
                );

                if ($normalized === $user->host_panel_permissions) {
                    return;
                }

                $user->update(['host_panel_permissions' => $normalized]);
            });
    }

    public function down(): void
    {
        // Irreversible: prior permission snapshots cannot be restored safely.
    }
};

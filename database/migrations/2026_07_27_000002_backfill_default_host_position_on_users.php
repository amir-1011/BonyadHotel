<?php

use App\Support\HostPositionTitles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('host_position_titles') || !Schema::hasTable('users') || !Schema::hasTable('roles')) {
            return;
        }

        if (!Role::query()->where('name', 'host')->where('guard_name', 'web')->exists()) {
            return;
        }

        HostPositionTitles::syncUsersForPosition(
            HostPositionTitles::DEFAULT_LABEL,
            HostPositionTitles::grantsForPositionLabel(HostPositionTitles::DEFAULT_LABEL),
        );
    }

    public function down(): void
    {
        // Irreversible: prior null titles and permission snapshots cannot be restored safely.
    }
};

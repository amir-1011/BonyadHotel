<?php

use App\Models\Accommodation;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accommodations', function (Blueprint $table) {
            $table->json('room_board_layout')->nullable()->after('is_active');
        });

        $this->migrateLayoutsFromUsers();

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('room_board_layout');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('room_board_layout')->nullable()->after('host_panel_permissions');
        });

        Accommodation::query()
            ->whereNotNull('room_board_layout')
            ->each(function (Accommodation $accommodation) {
                $hostIds = $accommodation->hosts()->pluck('users.id');
                if ($hostIds->isEmpty() && $accommodation->host_id) {
                    $hostIds = collect([$accommodation->host_id]);
                }

                $layout = $accommodation->room_board_layout;
                if (!$layout || $hostIds->isEmpty()) {
                    return;
                }

                $hostId = $hostIds->first();
                $user = User::find($hostId);
                if (!$user) {
                    return;
                }

                $stored = $user->room_board_layout ?? [];
                $stored['accommodations'] ??= [];
                $stored['accommodations'][(string) $accommodation->id] = $layout;
                $user->room_board_layout = $stored;
                $user->save();
            });

        Schema::table('accommodations', function (Blueprint $table) {
            $table->dropColumn('room_board_layout');
        });
    }

    private function migrateLayoutsFromUsers(): void
    {
        $winners = [];

        User::query()
            ->whereNotNull('room_board_layout')
            ->orderBy('updated_at')
            ->each(function (User $user) use (&$winners) {
                $stored = $user->room_board_layout ?? [];
                $layouts = $stored['accommodations'] ?? $stored['groups'] ?? [];

                foreach ($layouts as $accommodationId => $layout) {
                    if (empty($layout['rows'])) {
                        continue;
                    }

                    $winners[(int) $accommodationId] = [
                        'layout'       => $layout,
                        'updated_at'   => $user->updated_at,
                    ];
                }
            });

        foreach ($winners as $accommodationId => $entry) {
            Accommodation::whereKey($accommodationId)->update([
                'room_board_layout' => $entry['layout'],
            ]);
        }
    }
};

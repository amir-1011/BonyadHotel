<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('host_position_titles')
            ->where('label', 'میزبان')
            ->exists();

        if ($exists) {
            return;
        }

        $now = now();

        DB::table('host_position_titles')->insert([
            'label'                  => 'میزبان',
            'is_system'              => true,
            'sort_order'             => 0,
            'host_panel_permissions' => null,
            'created_at'             => $now,
            'updated_at'             => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('host_position_titles')
            ->where('label', 'میزبان')
            ->where('is_system', true)
            ->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('host_position_titles', function (Blueprint $table) {
            $table->json('host_panel_permissions')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('host_position_titles', function (Blueprint $table) {
            $table->dropColumn('host_panel_permissions');
        });
    }
};

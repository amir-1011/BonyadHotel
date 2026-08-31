<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_exchange_items', function (Blueprint $table) {
            $table->json('image_paths')->nullable()->after('image_path');
        });

        DB::table('facility_exchange_items')
            ->whereNotNull('image_path')
            ->orderBy('id')
            ->each(function (object $row): void {
                DB::table('facility_exchange_items')
                    ->where('id', $row->id)
                    ->update(['image_paths' => json_encode([$row->image_path], JSON_UNESCAPED_UNICODE)]);
            });
    }

    public function down(): void
    {
        Schema::table('facility_exchange_items', function (Blueprint $table) {
            $table->dropColumn('image_paths');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('room_type_daily_overrides')
            ->where('custom_price', 0)
            ->update(['custom_price' => null]);

        DB::table('room_type_weekly_price_rules')
            ->where('custom_price', 0)
            ->update(['custom_price' => null]);
    }

    public function down(): void
    {
        // Irreversible data cleanup — zero meant "unset", not a real price.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veteran_group_service_discounts', function (Blueprint $table) {
            $table->boolean('use_tiered_discount')->default(false)->after('weekly_free_sessions');
            $table->json('discount_tiers')->nullable()->after('use_tiered_discount');
        });
    }

    public function down(): void
    {
        Schema::table('veteran_group_service_discounts', function (Blueprint $table) {
            $table->dropColumn(['use_tiered_discount', 'discount_tiers']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veteran_groups', function (Blueprint $table) {
            $table->boolean('use_tiered_accommodation_discount')->default(false)->after('accommodation_discount');
            $table->json('accommodation_discount_tiers')->nullable()->after('use_tiered_accommodation_discount');
        });
    }

    public function down(): void
    {
        Schema::table('veteran_groups', function (Blueprint $table) {
            $table->dropColumn(['use_tiered_accommodation_discount', 'accommodation_discount_tiers']);
        });
    }
};

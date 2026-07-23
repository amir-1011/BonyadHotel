<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->unsignedSmallInteger('guest_sort_order')->nullable()->after('booking_id');
            $table->boolean('excluded_from_veteran_quota')->default(false)->after('veteran_group_usage');
            $table->unsignedTinyInteger('manual_discount_percentage')->nullable()->after('excluded_from_veteran_quota');
            $table->string('manual_discount_reason', 500)->nullable()->after('manual_discount_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->dropColumn([
                'guest_sort_order',
                'excluded_from_veteran_quota',
                'manual_discount_percentage',
                'manual_discount_reason',
            ]);
        });
    }
};

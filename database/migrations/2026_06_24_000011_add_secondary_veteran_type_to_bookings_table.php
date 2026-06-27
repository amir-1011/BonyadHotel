<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('bookings', 'secondary_veteran_type_applied')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('secondary_veteran_type_applied', 64)->nullable()->after('veteran_type_applied');
            });
        }

        if (!Schema::hasColumn('booking_services', 'veteran_group_usage')) {
            Schema::table('booking_services', function (Blueprint $table) {
                $table->json('veteran_group_usage')->nullable()->after('sort_order');
            });
        }
    }

    public function down(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->dropColumn('veteran_group_usage');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('secondary_veteran_type_applied');
        });
    }
};

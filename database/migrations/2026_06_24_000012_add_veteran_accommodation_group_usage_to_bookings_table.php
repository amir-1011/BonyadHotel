<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('bookings', 'veteran_accommodation_group_usage')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->json('veteran_accommodation_group_usage')->nullable()->after('secondary_veteran_type_applied');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'veteran_accommodation_group_usage')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('veteran_accommodation_group_usage');
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            // Max extra guests (کف‌خوابی) allowed per room beyond normal capacity
            $table->unsignedTinyInteger('extra_capacity')->nullable()->default(null)->after('capacity');
            // Price per extra guest per night (تومان)
            $table->unsignedInteger('extra_capacity_price')->nullable()->default(null)->after('extra_capacity');
        });
    }

    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn(['extra_capacity', 'extra_capacity_price']);
        });
    }
};

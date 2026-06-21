<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_guest_details', function (Blueprint $table) {
            $table->boolean('excluded_from_veteran_discount')->default(false)->after('relation');
        });
    }

    public function down(): void
    {
        Schema::table('booking_guest_details', function (Blueprint $table) {
            $table->dropColumn('excluded_from_veteran_discount');
        });
    }
};

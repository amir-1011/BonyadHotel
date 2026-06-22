<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_guest_details', function (Blueprint $table) {
            $table->unsignedTinyInteger('manual_discount_percentage')->nullable()->after('excluded_from_veteran_discount');
            $table->string('manual_discount_reason', 500)->nullable()->after('manual_discount_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('booking_guest_details', function (Blueprint $table) {
            $table->dropColumn(['manual_discount_percentage', 'manual_discount_reason']);
        });
    }
};

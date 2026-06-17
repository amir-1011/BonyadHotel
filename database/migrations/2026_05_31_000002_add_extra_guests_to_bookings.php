<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Number of extra guests (کف‌خوابی) added to this booking
            $table->unsignedTinyInteger('extra_guests')->default(0)->after('rooms_consumed');
            // Total extra guest cost for the full stay (pre-discount)
            $table->unsignedInteger('extra_guests_price')->default(0)->after('extra_guests');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['extra_guests', 'extra_guests_price']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('room_type_id')->nullable()->after('accommodation_id')->constrained('room_types')->nullOnDelete();
            $table->foreignId('room_rate_id')->nullable()->after('room_type_id')->constrained('room_rates')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['room_type_id']);
            $table->dropForeign(['room_rate_id']);
            $table->dropColumn(['room_type_id', 'room_rate_id']);
        });
    }
};

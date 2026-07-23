<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_guest_details', function (Blueprint $table) {
            $table->foreignId('booking_room_id')
                ->nullable()
                ->after('booking_id')
                ->constrained('booking_rooms')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_guest_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_room_id');
        });
    }
};

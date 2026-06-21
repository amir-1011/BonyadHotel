<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('room_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('adults')->default(1);
            $table->unsignedTinyInteger('children_under_6')->default(0);
            $table->unsignedTinyInteger('guests')->default(1);
            $table->unsignedTinyInteger('extra_guests')->default(0);
            $table->boolean('bill_full_rooms')->default(false);
            $table->unsignedTinyInteger('rooms_consumed')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Backfill legacy single-room bookings.
        if (Schema::hasTable('bookings')) {
            $bookings = DB::table('bookings')
                ->whereNotNull('room_type_id')
                ->orderBy('id')
                ->get(['id', 'room_type_id', 'room_rate_id', 'guests', 'children_under_6', 'extra_guests', 'bill_full_rooms', 'rooms_consumed']);

            foreach ($bookings as $booking) {
                $exists = DB::table('booking_rooms')->where('booking_id', $booking->id)->exists();
                if ($exists) {
                    continue;
                }

                $adults = max(1, (int) $booking->guests - (int) ($booking->children_under_6 ?? 0));

                DB::table('booking_rooms')->insert([
                    'booking_id'        => $booking->id,
                    'room_type_id'      => $booking->room_type_id,
                    'room_rate_id'      => $booking->room_rate_id,
                    'adults'            => $adults,
                    'children_under_6'  => (int) ($booking->children_under_6 ?? 0),
                    'guests'            => (int) $booking->guests,
                    'extra_guests'      => (int) ($booking->extra_guests ?? 0),
                    'bill_full_rooms'   => (bool) ($booking->bill_full_rooms ?? false),
                    'rooms_consumed'    => (int) ($booking->rooms_consumed ?? 1),
                    'sort_order'        => 0,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_rooms');
    }
};

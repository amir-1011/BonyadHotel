<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->unsignedSmallInteger('free_units')->default(0)->after('quantity');
        });

        // Backfill free_units for existing veteran_70 sport lines where discount
        // came entirely from free sessions (discount = n × unit_price).
        DB::table('booking_services')
            ->join('bookings', 'bookings.id', '=', 'booking_services.booking_id')
            ->where('booking_services.unit_price', '>', 0)
            ->where('booking_services.discount_amount', '>', 0)
            ->whereRaw('booking_services.discount_amount % booking_services.unit_price = 0')
            ->whereIn('bookings.veteran_type_applied', ['veteran_70_spouses', 'veteran_70_plus'])
            ->where('bookings.status', '!=', 'cancelled')
            ->select([
                'booking_services.id',
                'booking_services.quantity',
                'booking_services.unit_price',
                'booking_services.discount_amount',
            ])
            ->orderBy('booking_services.id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $inferred = (int) min(
                        $row->quantity,
                        (int) floor($row->discount_amount / $row->unit_price),
                    );

                    if ($inferred > 0) {
                        DB::table('booking_services')
                            ->where('id', $row->id)
                            ->update(['free_units' => $inferred]);
                    }
                }
            }, 'booking_services.id', 'id');
    }

    public function down(): void
    {
        Schema::table('booking_services', function (Blueprint $table) {
            $table->dropColumn('free_units');
        });
    }
};

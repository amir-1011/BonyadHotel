<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\DB;

class BookingPriceChangePreviewService
{
    /**
     * Simulate a booking mutation and return pricing impact without persisting.
     *
     * Delta is incremental for this operation only: it compares the naturally
     * recalculated total before vs after the mutation, then applies that delta
     * to the booking's current displayed total (which may include prior manual
     * price adjustments).
     *
     * @return array{
     *   affects_price: bool,
     *   current_total: int,
     *   projected_total: int,
     *   auto_delta: int
     * }
     */
    public function preview(Booking $booking, callable $mutator): array
    {
        $booking = $booking->fresh();
        $displayTotal = (int) $booking->total_price;

        if (!$this->bookingSupportsAutoRepricing($booking)) {
            return [
                'affects_price'   => false,
                'current_total'   => $displayTotal,
                'projected_total' => $displayTotal,
                'auto_delta'      => 0,
            ];
        }

        DB::beginTransaction();

        try {
            app(ManualBookingService::class)->recalculateTotals($booking);
            $booking->refresh();
            $naturalBaseline = (int) $booking->total_price;

            $mutator($booking);
            $booking->refresh();
            $naturalAfter = (int) $booking->total_price;

            $operationDelta = $naturalAfter - $naturalBaseline;
            $projectedDisplayTotal = max(0, $displayTotal + $operationDelta);

            DB::rollBack();

            return [
                'affects_price'   => $operationDelta !== 0,
                'current_total'   => $displayTotal,
                'projected_total' => $projectedDisplayTotal,
                'auto_delta'      => $operationDelta,
            ];
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }
    }

    public function bookingSupportsAutoRepricing(Booking $booking): bool
    {
        return $booking->isManual()
            && !$booking->isProgram()
            && $booking->booking_source !== 'online';
    }
}

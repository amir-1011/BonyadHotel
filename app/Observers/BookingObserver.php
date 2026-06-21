<?php

namespace App\Observers;

use App\Models\Booking;
use App\Services\PlatformCommissionService;

class BookingObserver
{
    public function __construct(
        private readonly PlatformCommissionService $commission,
    ) {}

    public function updated(Booking $booking): void
    {
        if (!$booking->wasChanged('status')) {
            return;
        }

        $this->commission->syncBookingCommissions($booking);
    }
}

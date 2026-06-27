<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingReceiptController extends Controller
{
    public function download(Request $request, Booking $booking, BookingPdfService $pdf)
    {
        $this->authorizeBooking($request, $booking);

        $booking->load([
            'services',
            'guestDetails',
            'user',
            'accommodation.city',
            'roomType',
            'roomRate',
            'createdBy',
            'bookingRooms.roomType',
            'bookingRooms.roomRate',
            'bookingRooms.room',
        ]);

        $filename = 'booking-' . $booking->tracking_code . '.pdf';

        return response()->streamDownload(function () use ($pdf, $booking) {
            echo $pdf->render($booking);
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function authorizeBooking(Request $request, Booking $booking): void
    {
        $user = Auth::user();

        if ($user->isAdmin()) {
            return;
        }

        if ($user->isHost() && $booking->accommodation->isManagedBy($user)) {
            return;
        }

        if ($booking->user_id === $user->id) {
            return;
        }

        abort(403);
    }
}

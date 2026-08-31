<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingPaymentRecord;
use App\Services\BookingPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BookingReceiptController extends Controller
{
    public function download(Request $request, Booking $booking, BookingPdfService $pdf)
    {
        $this->authorizeBooking($request, $booking);

        $booking->load([
            'services',
            'guestDetails.bookingRoom.room',
            'user',
            'accommodation.city',
            'roomType',
            'roomRate',
            'createdBy',
            'beneficiaryCosts.beneficiary',
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

    public function medicalReferral(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);

        $paths = $booking->medicalReferralLetterPaths();

        abort_unless(
            $booking->isMedicalAccommodation() && $paths !== [],
            404,
            'معرفی‌نامه اسکان درمانی برای این رزرو ثبت نشده است.',
        );

        $index = (int) $request->query('index', 0);
        $path = $paths[$index] ?? null;
        abort_unless($path && Storage::disk('public')->exists($path), 404, 'فایل معرفی‌نامه یافت نشد.');

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'bin');
        $suffix = count($paths) > 1 ? '-' . ($index + 1) : '';
        $filename = 'medical-referral-' . $booking->tracking_code . $suffix . '.' . $extension;

        return Storage::disk('public')->download($path, $filename);
    }

    public function creditLetter(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);

        $paths = $booking->creditLetterPaths();

        abort_unless(
            $booking->isCredit() && $paths !== [],
            404,
            'معرفی‌نامه اعتباری برای این رزرو ثبت نشده است.',
        );

        $index = (int) $request->query('index', 0);
        $path = $paths[$index] ?? null;
        abort_unless($path && Storage::disk('public')->exists($path), 404, 'فایل معرفی‌نامه یافت نشد.');

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'bin');
        $suffix = count($paths) > 1 ? '-' . ($index + 1) : '';
        $filename = 'credit-letter-' . $booking->tracking_code . $suffix . '.' . $extension;

        return Storage::disk('public')->download($path, $filename);
    }

    public function paymentDocument(Request $request, Booking $booking, BookingPaymentRecord $record)
    {
        $this->authorizeBooking($request, $booking);

        abort_unless((int) $record->booking_id === (int) $booking->id, 404);

        $paths = $record->documentPaths();
        $index = (int) $request->query('index', 0);
        $path = $paths[$index] ?? null;
        abort_unless($path && Storage::disk('public')->exists($path), 404, 'فایل مستند یافت نشد.');

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: 'bin');
        $suffix = count($paths) > 1 ? '-' . ($index + 1) : '';
        $filename = 'payment-doc-' . $booking->tracking_code . $suffix . '.' . $extension;

        return Storage::disk('public')->download($path, $filename);
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

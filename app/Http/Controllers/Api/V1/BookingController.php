<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AccommodationResource;
use App\Http\Resources\Api\BookingResource;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Review;
use App\Services\BookingPdfService;
use App\Services\GuestBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $bookings = $request->user()->bookings()
            ->with(['accommodation.city.province', 'roomType', 'roomRate'])
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => BookingResource::collection($bookings),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page'    => $bookings->lastPage(),
                'per_page'     => $bookings->perPage(),
                'total'        => $bookings->total(),
            ],
        ]);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeOwner($request, $booking);

        $booking->load([
            'accommodation.city.province',
            'roomType',
            'roomRate',
            'cancellationRequests.reason',
        ]);

        return response()->json([
            'data' => new BookingResource($booking),
        ]);
    }

    public function store(Request $request, Accommodation $accommodation, GuestBookingService $service): JsonResponse
    {
        abort_if(!$accommodation->is_active, 404);

        $validated = $request->validate([
            'check_in'         => ['required', 'date', 'after_or_equal:today'],
            'check_out'        => ['required', 'date', 'after:check_in'],
            'guests'           => ['required', 'integer', 'min:1', 'max:' . $accommodation->capacity],
            'room_type_id'     => ['nullable', 'integer', 'exists:room_types,id'],
            'room_rate_id'     => ['nullable', 'integer', 'exists:room_rates,id'],
            'extra_guests'     => ['nullable', 'integer', 'min:0', 'max:10'],
            'children_under_6' => ['nullable', 'integer', 'min:0', 'max:10'],
            'bill_full_rooms'  => ['nullable', 'boolean'],
        ], [
            'check_in.required'       => 'تاریخ ورود الزامی است.',
            'check_in.after_or_equal' => 'تاریخ ورود نمی‌تواند در گذشته باشد.',
            'check_out.required'      => 'تاریخ خروج الزامی است.',
            'check_out.after'         => 'تاریخ خروج باید بعد از تاریخ ورود باشد.',
            'guests.max'              => "حداکثر ظرفیت این اقامتگاه {$accommodation->capacity} نفر است.",
        ]);

        try {
            $booking = $service->store($accommodation, $request->user(), $validated);
        } catch (ValidationException $e) {
            throw $e;
        }

        $booking->load(['accommodation.city.province', 'roomType', 'roomRate']);

        return response()->json([
            'message' => 'رزرو شما با موفقیت ثبت شد.',
            'data'    => new BookingResource($booking),
        ], 201);
    }

    public function pdf(Request $request, Booking $booking, BookingPdfService $pdf)
    {
        $this->authorizeOwner($request, $booking);

        $booking->load([
            'services',
            'guestDetails',
            'user',
            'accommodation.city',
            'roomType',
            'roomRate',
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

    private function authorizeOwner(Request $request, Booking $booking): void
    {
        abort_if($booking->user_id !== $request->user()->id, 403, 'دسترسی غیرمجاز.');
    }
}

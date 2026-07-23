<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Accommodation $accommodation): JsonResponse
    {
        $validated = $request->validate([
            'rating'     => ['required', 'integer', 'min:1', 'max:5'],
            'comment'    => ['nullable', 'string', 'max:1000'],
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
        ]);

        $hasBooking = Booking::where('user_id', $request->user()->id)
            ->where('accommodation_id', $accommodation->id)
            ->where('status', 'confirmed')
            ->where('check_out', '<', now()->toDateString())
            ->exists();

        if (!$hasBooking) {
            return response()->json([
                'message' => 'فقط مهمانانی که اقامت خود را تکمیل کرده‌اند می‌توانند نظر ثبت کنند.',
            ], 422);
        }

        $review = Review::updateOrCreate(
            ['user_id' => $request->user()->id, 'accommodation_id' => $accommodation->id],
            [
                'rating'     => $validated['rating'],
                'comment'    => $validated['comment'] ?? null,
                'is_visible' => true,
                'booking_id' => $validated['booking_id'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'نظر شما با موفقیت ثبت شد.',
            'data'    => [
                'id'      => $review->id,
                'rating'  => $review->rating,
                'comment' => $review->comment,
            ],
        ], 201);
    }
}

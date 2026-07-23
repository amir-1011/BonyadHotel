<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\AccommodationResource;
use App\Http\Resources\Api\CancellationRequestResource;
use App\Models\Booking;
use App\Models\CancellationReason;
use App\Services\CancellationRequestService;
use App\Services\RefundPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CancellationRequestController extends Controller
{
    public function reasons(Request $request, Booking $booking): JsonResponse
    {
        $this->authorizeOwner($request, $booking);

        $reasons = CancellationReason::query()
            ->forAccommodation((int) $booking->accommodation_id)
            ->active()
            ->ordered()
            ->get(['id', 'label', 'is_custom']);

        return response()->json(['data' => $reasons]);
    }

    public function preview(Request $request, Booking $booking, RefundPolicyService $refundPolicy): JsonResponse
    {
        $this->authorizeOwner($request, $booking);

        return response()->json([
            'data' => $refundPolicy->previewForBooking($booking),
        ]);
    }

    public function store(Request $request, Booking $booking, CancellationRequestService $service): JsonResponse
    {
        $this->authorizeOwner($request, $booking);

        $validated = $request->validate([
            'cancellation_reason_id'     => ['required', 'integer'],
            'custom_reason_text'         => ['nullable', 'string', 'max:1000'],
            'refund_account_number'      => ['required', 'string', 'max:40'],
            'refund_account_holder_name' => ['nullable', 'string', 'max:100'],
            'notes'                      => ['nullable', 'string', 'max:1000'],
        ], [
            'cancellation_reason_id.required' => 'انتخاب دلیل کنسلی الزامی است.',
            'refund_account_number.required'  => 'وارد کردن شماره حساب یا کارت الزامی است.',
        ]);

        try {
            $cancellationRequest = $service->create($booking, [
                'cancellation_reason_id'     => $validated['cancellation_reason_id'],
                'custom_reason_text'         => $validated['custom_reason_text'] ?? null,
                'refund_account_number'      => $validated['refund_account_number'],
                'refund_account_holder_name' => $validated['refund_account_holder_name'] ?? null,
                'notes'                      => $validated['notes'] ?? null,
            ], $request->user());
        } catch (ValidationException $e) {
            throw $e;
        }

        $cancellationRequest->load('reason');

        return response()->json([
            'message' => 'درخواست کنسلی با موفقیت ثبت شد.',
            'data'    => new CancellationRequestResource($cancellationRequest),
        ], 201);
    }

    private function authorizeOwner(Request $request, Booking $booking): void
    {
        abort_if($booking->user_id !== $request->user()->id, 403, 'دسترسی غیرمجاز.');
    }
}

<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CancellationReason;
use App\Models\CancellationRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CancellationRequestService
{
    public function __construct(
        private readonly RefundPolicyService $refundPolicy,
    ) {}

    /**
     * @param  array{cancellation_reason_id: int|string|null, custom_reason_text?: string|null, refund_account_number: string, refund_account_holder_name?: string|null, notes?: string|null, refund_amount?: int|string|null}  $data
     */
    public function create(Booking $booking, array $data, ?User $actor = null): CancellationRequest
    {
        if ($booking->cancellationWindowClosed()) {
            throw ValidationException::withMessages([
                'cancellation_request' => 'مهلت ثبت درخواست کنسلی و استرداد وجه تا تاریخ خروج به پایان رسیده است.',
            ]);
        }

        if (!$booking->canRequestCancellation()) {
            throw ValidationException::withMessages([
                'cancellation_request' => 'برای این رزرو در حال حاضر امکان ثبت درخواست کنسلی وجود ندارد.',
            ]);
        }

        $reason = null;
        if (!empty($data['cancellation_reason_id'])) {
            $reason = CancellationReason::query()
                ->forAccommodation((int) $booking->accommodation_id)
                ->find($data['cancellation_reason_id']);
        }

        if (!empty($data['cancellation_reason_id']) && !$reason) {
            throw ValidationException::withMessages([
                'cancellation_reason_id' => 'دلیل انتخاب‌شده برای این اقامتگاه معتبر نیست.',
            ]);
        }

        $reasonText = null;
        if ($reason?->is_custom) {
            $reasonText = trim((string) ($data['custom_reason_text'] ?? ''));
            if ($reasonText === '') {
                throw ValidationException::withMessages([
                    'custom_reason_text' => 'لطفاً دلیل دلخواه خود را بنویسید.',
                ]);
            }
        } elseif ($reason) {
            $reasonText = $reason->label;
        }

        $preview = $this->refundPolicy->previewForBooking($booking);

        $refundAmount = $preview['amount'];
        if (array_key_exists('refund_amount', $data) && $data['refund_amount'] !== null && $data['refund_amount'] !== '') {
            $refundAmount = (int) preg_replace('/\D/', '', (string) $data['refund_amount']);
            if ($refundAmount < 0) {
                throw ValidationException::withMessages([
                    'refund_amount' => 'مبلغ بازگشتی نمی‌تواند منفی باشد.',
                ]);
            }
        }

        $accountNumber = $this->normalizeDigits((string) ($data['refund_account_number'] ?? ''));
        if (trim($accountNumber) === '' && $refundAmount > 0) {
            throw ValidationException::withMessages([
                'refund_account_number' => 'وارد کردن شماره حساب یا شماره کارت جهت استرداد وجه الزامی است.',
            ]);
        }

        return CancellationRequest::create([
            'booking_id'                  => $booking->id,
            'requested_by'                => $actor?->id,
            'status'                      => CancellationRequest::STATUS_PENDING,
            'cancellation_reason_id'      => $reason?->id,
            'reason_text'                 => $reasonText,
            'notes'                       => $data['notes'] ?? null,
            'refund_account_number'       => $accountNumber,
            'refund_account_holder_name'  => $data['refund_account_holder_name'] ?? null,
            'days_before_checkin'         => $preview['days'],
            'refund_percentage'           => $preview['percentage'],
            'refund_amount'               => $refundAmount,
        ]);
    }

    /**
     * Staff direct cancellation submit: when refund is zero, auto-approve and settle
     * so the booking is cancelled and capacity is released in one step.
     *
     * @param  array{cancellation_reason_id: int|string|null, custom_reason_text?: string|null, refund_account_number: string, refund_account_holder_name?: string|null, notes?: string|null, refund_amount?: int|string|null}  $data
     */
    public function createWithStaffDirectCompletion(Booking $booking, array $data, User $staff): CancellationRequest
    {
        $request = $this->create($booking, $data, $staff);

        if ($request->hasZeroRefund()) {
            return $this->approve($request, $staff);
        }

        return $request;
    }

    public function approve(CancellationRequest $request, User $staff): CancellationRequest
    {
        if (!$request->isPending()) {
            throw ValidationException::withMessages([
                'cancellation_request' => 'این درخواست قبلاً بررسی شده است.',
            ]);
        }

        $request->update([
            'status'      => CancellationRequest::STATUS_APPROVED,
            'decided_by'  => $staff->id,
            'decided_at'  => now(),
        ]);

        $booking = $request->booking()->with('accommodation.medicalAccommodationSetting')->first();
        $booking?->update(['status' => 'cancelled']);

        if ($booking?->isMedicalAccommodation()) {
            app(MedicalAccommodationBillingService::class)->applyCancellationToEmployerDebt($booking->fresh());
        }

        $request = $request->refresh();

        if ($request->hasZeroRefund()) {
            return $this->markSettledWithoutPayment($request, $staff);
        }

        return $request;
    }

    public function markSettledWithoutPayment(CancellationRequest $request, User $staff): CancellationRequest
    {
        if (!$request->isApproved()) {
            throw ValidationException::withMessages([
                'cancellation_request' => 'فقط درخواست‌های تایید شده قابل تسویه هستند.',
            ]);
        }

        if ($request->isSettled()) {
            throw ValidationException::withMessages([
                'cancellation_request' => 'این درخواست قبلاً تسویه شده است.',
            ]);
        }

        if (!$request->hasZeroRefund()) {
            throw ValidationException::withMessages([
                'cancellation_request' => 'تسویه بدون واریز فقط برای درخواست‌های با مبلغ استرداد صفر مجاز است.',
            ]);
        }

        $request->update([
            'settled_by'             => $staff->id,
            'settled_at'             => now(),
            'settled_amount'         => 0,
            'settled_account_number' => $request->refund_account_number,
            'settlement_notes'       => 'تسویه خودکار — مبلغ استرداد صفر',
        ]);

        return $request->refresh();
    }

    public function reject(CancellationRequest $request, User $staff, string $rejectionReason): CancellationRequest
    {
        if (!$request->isPending()) {
            throw ValidationException::withMessages([
                'cancellation_request' => 'این درخواست قبلاً بررسی شده است.',
            ]);
        }

        if (trim($rejectionReason) === '') {
            throw ValidationException::withMessages([
                'rejection_reason' => 'ذکر دلیل رد درخواست الزامی است.',
            ]);
        }

        $request->update([
            'status'           => CancellationRequest::STATUS_REJECTED,
            'decided_by'       => $staff->id,
            'decided_at'       => now(),
            'rejection_reason' => trim($rejectionReason),
        ]);

        return $request->refresh();
    }

    /**
     * @param  array{deposited_at: string, amount: int|string, account_number: string, notes?: string|null}  $data
     */
    public function markSettled(CancellationRequest $request, User $staff, array $data): CancellationRequest
    {
        if (!$request->isApproved()) {
            throw ValidationException::withMessages([
                'cancellation_request' => 'فقط درخواست‌های تایید شده قابل تسویه هستند.',
            ]);
        }

        if ($request->isSettled()) {
            throw ValidationException::withMessages([
                'cancellation_request' => 'این درخواست قبلاً تسویه شده است.',
            ]);
        }

        $depositedAtRaw = trim((string) ($data['deposited_at'] ?? ''));
        if ($depositedAtRaw === '') {
            throw ValidationException::withMessages([
                'deposited_at' => 'وارد کردن تاریخ و زمان واریز الزامی است.',
            ]);
        }

        try {
            $depositedAt = Carbon::parse($depositedAtRaw);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'deposited_at' => 'تاریخ و زمان واریز معتبر نیست.',
            ]);
        }

        $amount = (int) preg_replace('/\D/', '', (string) ($data['amount'] ?? ''));
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'مبلغ واریز باید بیشتر از صفر باشد.',
            ]);
        }

        $accountNumber = $this->normalizeDigits((string) ($data['account_number'] ?? ''));
        if (trim($accountNumber) === '') {
            throw ValidationException::withMessages([
                'account_number' => 'وارد کردن شماره حساب مقصد الزامی است.',
            ]);
        }

        $request->update([
            'settled_by'             => $staff->id,
            'settled_at'             => $depositedAt,
            'settled_amount'         => $amount,
            'settled_account_number' => $accountNumber,
            'settlement_notes'       => isset($data['notes']) && trim((string) $data['notes']) !== ''
                ? trim((string) $data['notes'])
                : null,
        ]);

        return $request->refresh();
    }

    private function normalizeDigits(string $value): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($arabic, $english, str_replace($persian, $english, trim($value)));
    }
}

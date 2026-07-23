<?php

namespace App\Livewire\Concerns;

use App\Models\CancellationReason;
use App\Services\CancellationRequestService;
use App\Services\RefundPolicyService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Shared by guest / admin / host booking-show Livewire components.
 * Requires the host component to expose `public Booking $booking;`.
 */
trait ManagesCancellationRequests
{
    use AssertsHostPermissions;
    use ParsesCancellationSettleInput;

    public bool $showCancellationRequestModal = false;
    public string $cancellationReasonId = '';
    public string $customReasonText = '';
    public string $refundAccountNumber = '';
    public string $refundAccountHolderName = '';
    public string $cancellationNotes = '';
    public int $cancellationRefundAmount = 0;

    public bool $showSettleModal = false;
    public ?int $settlingRequestId = null;
    public int $settleAmount = 0;
    public string $settleAccountNumber = '';
    public string $settleNotes = '';

    /**
     * Deliberately NOT named `boot{TraitName}` — that naming convention makes
     * Livewire auto-invoke the method on every request, which conflicts with
     * calling it explicitly from each host component's mount().
     */
    protected function initCancellationRequestsData(): void
    {
        $this->booking->load([
            'cancellationRequests.reason',
            'cancellationRequests.requestedBy',
            'cancellationRequests.decidedBy',
            'cancellationRequests.settledBy',
        ]);
    }

    public function cancellationReasonOptions()
    {
        return CancellationReason::query()
            ->forAccommodation((int) $this->booking->accommodation_id)
            ->active()
            ->ordered()
            ->get();
    }

    /** @return array{days:int, percentage:int, amount:int} */
    public function cancellationRefundPreview(): array
    {
        return app(RefundPolicyService::class)->previewForBooking($this->booking);
    }

    protected function maybeAutoOpenCancellationRequestModal(): void
    {
        if (!request()->boolean('cancel')) {
            return;
        }

        if ($this->booking->canRequestCancellation()) {
            $this->openCancellationRequestModal();
            return;
        }

        $this->dispatch('toast', type: 'warning', message: 'برای این رزرو در حال حاضر امکان ثبت درخواست کنسلی وجود ندارد.');
    }

    public function openCancellationRequestModal(): void
    {
        if (!$this->booking->canRequestCancellation()) {
            $this->dispatch('toast', type: 'error', message: 'برای این رزرو در حال حاضر امکان ثبت درخواست کنسلی وجود ندارد.');
            return;
        }

        $this->reset(['cancellationReasonId', 'customReasonText', 'refundAccountNumber', 'refundAccountHolderName', 'cancellationNotes']);
        $this->cancellationRefundAmount = (int) $this->cancellationRefundPreview()['amount'];
        $this->resetErrorBag();
        $this->showCancellationRequestModal = true;
    }

    public function closeCancellationRequestModal(): void
    {
        $this->showCancellationRequestModal = false;
    }

    public function submitCancellationRequest(): void
    {
        if ($this->isHostPanelUser()) {
            $this->assertHostCan('bookings.cancellation-submit', 'write');
        }

        $staffSetsRefundAmount = $this->isHostPanelUser() || (Auth::user()?->hasStaffAccess() ?? false);

        $rules = [
            'cancellationReasonId'    => ['required'],
            'refundAccountNumber'    => ['required', 'string', 'max:40'],
            'refundAccountHolderName' => ['nullable', 'string', 'max:100'],
            'cancellationNotes'      => ['nullable', 'string', 'max:1000'],
        ];

        if ($staffSetsRefundAmount) {
            $rules['cancellationRefundAmount'] = ['required', 'integer', 'min:0'];
        }

        $this->validate($rules, [
            'cancellationReasonId.required'   => 'انتخاب دلیل کنسلی الزامی است.',
            'refundAccountNumber.required'   => 'وارد کردن شماره حساب یا شماره کارت جهت استرداد وجه الزامی است.',
            'cancellationRefundAmount.required' => 'وارد کردن مبلغ بازگشتی الزامی است.',
            'cancellationRefundAmount.min'      => 'مبلغ بازگشتی نمی‌تواند منفی باشد.',
        ]);

        $reason = CancellationReason::query()
            ->forAccommodation((int) $this->booking->accommodation_id)
            ->find($this->cancellationReasonId);
        if ($reason?->is_custom && trim($this->customReasonText) === '') {
            $this->addError('customReasonText', 'لطفاً دلیل دلخواه خود را بنویسید.');
            return;
        }

        try {
            $payload = [
                'cancellation_reason_id'     => $this->cancellationReasonId,
                'custom_reason_text'         => $this->customReasonText,
                'refund_account_number'      => $this->refundAccountNumber,
                'refund_account_holder_name' => $this->refundAccountHolderName ?: null,
                'notes'                      => $this->cancellationNotes ?: null,
            ];

            if ($staffSetsRefundAmount) {
                $payload['refund_amount'] = $this->cancellationRefundAmount;
            }

            app(CancellationRequestService::class)->create($this->booking, $payload, Auth::user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $livewireField = match ($field) {
                    'cancellation_request' => 'cancellationReasonId',
                    'refund_amount'        => 'cancellationRefundAmount',
                    default                => $field,
                };
                $this->addError($livewireField, $messages[0]);
            }
            return;
        }

        $this->showCancellationRequestModal = false;
        $this->refreshCancellationRequestsData();
        $this->dispatch('toast', type: 'success', message: 'درخواست کنسلی و استرداد وجه با موفقیت ثبت شد و برای بررسی ارسال شد.');
    }

    public function approveCancellationRequest(int $requestId): void
    {
        if ($this->isHostPanelUser()) {
            $this->assertHostCan('cancellation-requests.decide', 'edit');
        } else {
            abort_unless(Auth::user()?->hasStaffAccess(), 403);
        }

        $request = $this->booking->cancellationRequests()->where('id', $requestId)->firstOrFail();

        try {
            app(CancellationRequestService::class)->approve($request, Auth::user());
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
            return;
        }

        $this->refreshCancellationRequestsData();
        $request->refresh();
        $this->presentSettleModal($request);
    }

    public function submitCancellationReject(int $requestId, string $rejectionReason): void
    {
        if ($this->isHostPanelUser()) {
            $this->assertHostCan('cancellation-requests.decide', 'edit');
        } else {
            abort_unless(Auth::user()?->hasStaffAccess(), 403);
        }

        if (trim($rejectionReason) === '') {
            $this->dispatch('toast', type: 'warning', message: 'ذکر دلیل رد درخواست الزامی است.');
            return;
        }

        $request = $this->booking->cancellationRequests()->where('id', $requestId)->firstOrFail();

        try {
            app(CancellationRequestService::class)->reject($request, Auth::user(), $rejectionReason);
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: collect($e->errors())->flatten()->first() ?? 'خطا در رد درخواست.');
            return;
        }

        $this->refreshCancellationRequestsData();
        $this->dispatch('toast', type: 'success', message: 'درخواست کنسلی رد شد.');
    }

    public function openSettleModal(int $requestId): void
    {
        if ($this->isHostPanelUser()) {
            $this->assertHostCan('cancellation-requests.settle', 'edit');
        } else {
            abort_unless(Auth::user()?->hasStaffAccess(), 403);
        }

        $request = $this->booking->cancellationRequests()->where('id', $requestId)->firstOrFail();
        if (!$request->isApproved() || $request->isSettled()) {
            $this->dispatch('toast', type: 'error', message: 'این درخواست قابل تسویه نیست.');
            return;
        }

        $this->presentSettleModal($request);
    }

    public function closeSettleModal(): void
    {
        $this->showSettleModal = false;
        $this->settlingRequestId = null;
    }

    public function submitSettle(): void
    {
        if ($this->isHostPanelUser()) {
            $this->assertHostCan('cancellation-requests.settle', 'edit');
        } else {
            abort_unless(Auth::user()?->hasStaffAccess(), 403);
        }

        $this->validate(
            $this->settleFormValidationRules(),
            $this->settleFormValidationMessages(),
        );

        $request = $this->booking->cancellationRequests()->where('id', $this->settlingRequestId)->firstOrFail();

        try {
            $depositedAt = $this->resolveSettleDepositedAt();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
            return;
        }

        try {
            app(CancellationRequestService::class)->markSettled($request, Auth::user(), [
                'deposited_at'    => $depositedAt->toDateTimeString(),
                'amount'          => $this->settleAmount,
                'account_number'  => $this->settleAccountNumber,
                'notes'           => $this->settleNotes ?: null,
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $livewireField = match ($field) {
                    'deposited_at'    => 'settleDepositedDate',
                    'amount'          => 'settleAmount',
                    'account_number'  => 'settleAccountNumber',
                    'cancellation_request' => null,
                    default           => $field,
                };
                if ($livewireField === null) {
                    $this->dispatch('toast', type: 'error', message: $messages[0]);
                } else {
                    $this->addError($livewireField, $messages[0]);
                }
            }
            return;
        }

        $this->showSettleModal = false;
        $this->settlingRequestId = null;
        $this->refreshCancellationRequestsData();
        $this->dispatch('toast', type: 'success', message: 'وضعیت درخواست به «تسویه شده» تغییر یافت.');
    }

    protected function refreshCancellationRequestsData(): void
    {
        $this->booking->refresh()->load([
            'cancellationRequests.reason',
            'cancellationRequests.requestedBy',
            'cancellationRequests.decidedBy',
            'cancellationRequests.settledBy',
        ]);

        // Admin/Host BookingShow components (ManagesBookingDetails) keep the
        // status dropdown in a separate `selectedStatus` property that is only
        // synced at mount(); resync it here so it doesn't go stale after an
        // approval flips the booking to "cancelled" behind its back.
        if (property_exists($this, 'selectedStatus')) {
            $this->selectedStatus = $this->booking->status;
        }
    }
}

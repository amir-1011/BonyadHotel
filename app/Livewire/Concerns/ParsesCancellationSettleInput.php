<?php

namespace App\Livewire\Concerns;

use App\Models\CancellationRequest;
use App\Support\JalaliDateTimeInput;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

trait ParsesCancellationSettleInput
{
    public string $settleDepositedDate = '';
    public string $settleDepositedTime = '';

    /** @return array<string, array<int, string>> */
    protected function settleFormValidationRules(): array
    {
        return [
            'settleDepositedDate' => ['required', 'string', 'max:16'],
            'settleDepositedTime' => ['required', 'string', 'regex:/^([01]?\d|2[0-3]):[0-5]\d$/'],
            'settleAmount'        => ['required', 'integer', 'min:1'],
            'settleAccountNumber' => ['required', 'string', 'max:40'],
            'settleNotes'         => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    protected function settleFormValidationMessages(): array
    {
        return [
            'settleDepositedDate.required' => 'وارد کردن تاریخ واریز الزامی است.',
            'settleDepositedTime.required' => 'وارد کردن زمان واریز الزامی است.',
            'settleDepositedTime.regex'    => 'فرمت زمان باید به صورت ساعت:دقیقه (مثال 14:30) باشد.',
            'settleAmount.required'        => 'وارد کردن مبلغ واریز الزامی است.',
            'settleAmount.min'             => 'مبلغ واریز باید بیشتر از صفر باشد.',
            'settleAccountNumber.required' => 'وارد کردن شماره حساب مقصد الزامی است.',
        ];
    }

    protected function populateSettleFormDefaults(CancellationRequest $request): void
    {
        $this->settleDepositedDate = JalaliDateTimeInput::nowJalaliDate();
        $this->settleDepositedTime = JalaliDateTimeInput::nowTime();
        $this->settleAmount = (int) $request->refund_amount;
        $this->settleAccountNumber = $request->refund_account_number;
        $this->settleNotes = '';
    }

    protected function presentSettleModalAfterApproval(CancellationRequest $request): void
    {
        if ($request->isSettled()) {
            $this->dispatch('toast', type: 'success', message: 'درخواست کنسلی تایید و تسویه شد و ظرفیت رزرو آزاد گردید.');
            return;
        }

        $this->presentSettleModal($request);
    }

    protected function presentSettleModal(CancellationRequest $request): void
    {
        $this->settlingRequestId = $request->id;
        $this->populateSettleFormDefaults($request);
        $this->resetErrorBag();
        $this->showSettleModal = true;
        $this->dispatch('cancellation-settle-modal-opened');
    }

    protected function resolveSettleDepositedAt(): Carbon
    {
        try {
            return JalaliDateTimeInput::toCarbon($this->settleDepositedDate, $this->settleDepositedTime);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'settleDepositedDate' => 'تاریخ و زمان واریز معتبر نیست.',
            ]);
        }
    }
}

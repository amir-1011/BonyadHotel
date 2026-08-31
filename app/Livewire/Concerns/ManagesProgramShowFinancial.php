<?php

namespace App\Livewire\Concerns;

use App\Models\Program;
use App\Services\ProgramBookingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

trait ManagesProgramShowFinancial
{
    public bool $financialEditMode = false;

    public int|string $editBasePrice = 0;

    public int|string $editDiscountAmount = 0;

    public int|string $editDepositAmount = 0;

    protected function bootProgramShowFinancial(): void
    {
        $this->resetProgramFinancialFields();
    }

    public function getEditProgramTotalAmountProperty(): int
    {
        $servicesSubtotal = (int) ($this->program->services_subtotal ?? 0);

        return max(0, $this->parsedProgramAmount($this->editBasePrice) + $servicesSubtotal - $this->parsedProgramAmount($this->editDiscountAmount));
    }

    public function getEditProgramRemainingAmountProperty(): int
    {
        return max(0, $this->editProgramTotalAmount - $this->parsedProgramAmount($this->editDepositAmount));
    }

    public function canEditProgramFinancial(): bool
    {
        if ($this->program->status === Program::STATUS_CANCELLED) {
            return false;
        }

        if (!$this->program->booking) {
            return false;
        }

        $user = Auth::user();

        if ($user?->isAdmin()) {
            return true;
        }

        return (bool) $user?->hostCan('programs.pricing', 'edit');
    }

    public function toggleFinancialEditMode(): void
    {
        if ($this->financialEditMode) {
            $this->financialEditMode = false;
            $this->resetProgramFinancialFields();
            $this->resetErrorBag();

            return;
        }

        $this->assertCanEditProgramFinancial();
        $this->resetProgramFinancialFields();
        $this->financialEditMode = true;
    }

    public function saveProgramFinancial(): void
    {
        $this->assertCanEditProgramFinancial();

        $basePrice = $this->parsedProgramAmount($this->editBasePrice);
        $discountAmount = $this->parsedProgramAmount($this->editDiscountAmount);
        $depositAmount = $this->parsedProgramAmount($this->editDepositAmount);
        $totalAmount = $this->editProgramTotalAmount;

        if ($basePrice < 0 || $discountAmount < 0 || $depositAmount < 0) {
            throw ValidationException::withMessages([
                'editBasePrice' => 'مبالغ نمی‌توانند منفی باشند.',
            ]);
        }

        if ($discountAmount > $basePrice + (int) ($this->program->services_subtotal ?? 0)) {
            throw ValidationException::withMessages([
                'editDiscountAmount' => 'مبلغ تخفیف از جمع قیمت پایه و خدمات بیشتر است.',
            ]);
        }

        if ($depositAmount > $totalAmount) {
            throw ValidationException::withMessages([
                'editDepositAmount' => 'بیعانه نمی‌تواند بیشتر از مبلغ کل باشد.',
            ]);
        }

        $updated = app(ProgramBookingService::class)->updateFinancial(
            $this->program,
            $basePrice,
            $discountAmount,
            $depositAmount,
        );

        $this->program = $updated;

        if (method_exists($this, 'programShowRelations')) {
            $this->program->load($this->programShowRelations());
        }

        $this->financialEditMode = false;
        $this->resetProgramFinancialFields();
        $this->resetErrorBag();
        $this->dispatch('toast', type: 'success', message: 'اطلاعات مالی برنامه با موفقیت به‌روز شد.');
    }

    protected function assertCanEditProgramFinancial(): void
    {
        abort_unless($this->canEditProgramFinancial(), 403, 'امکان ویرایش اطلاعات مالی این برنامه وجود ندارد.');
    }

    protected function resetProgramFinancialFields(): void
    {
        $this->editBasePrice = (int) ($this->program->base_price ?? 0);
        $this->editDiscountAmount = (int) ($this->program->discount_amount ?? 0);
        $this->editDepositAmount = (int) ($this->program->deposit_amount ?? 0);
    }

    protected function parsedProgramAmount(int|string $value): int
    {
        return (int) str_replace([',', ' ', '٬'], '', (string) $value);
    }
}

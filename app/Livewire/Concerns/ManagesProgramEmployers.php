<?php

namespace App\Livewire\Concerns;

use App\Models\ProgramEmployer;
use App\Services\EmployerUserProvisioner;
use App\Services\ProvinceAccountingCodeService;
use App\Support\ProvinceAccountingIndicators;

trait ManagesProgramEmployers
{
    use ResolvesAccountingProvince;

    public bool $showAddEmployer = false;

    public string $programEmployerId = '';

    public string $newEmployerName = '';
    public string $newEmployerNationalId = '';
    public string $newEmployerMobile = '';

    public function openEmployerModal(): void
    {
        if (!$this->assertAccommodationSelectedForAccounting()) {
            return;
        }

        $this->showAddEmployer = true;
        $this->newEmployerName = '';
        $this->newEmployerNationalId = '';
        $this->newEmployerMobile = '';
        $this->resetErrorBag(['newEmployerName', 'newEmployerNationalId', 'newEmployerMobile']);
    }

    public function closeEmployerModal(): void
    {
        $this->showAddEmployer = false;
        $this->resetErrorBag(['newEmployerName', 'newEmployerNationalId', 'newEmployerMobile']);
    }

    public function previewNextEmployerCode(): string
    {
        try {
            return app(ProvinceAccountingCodeService::class)->previewNext(
                $this->resolveAccountingProvince(),
                ProvinceAccountingIndicators::ORGANIZATION,
            );
        } catch (\Throwable) {
            return '—';
        }
    }

    public function addEmployerToCatalog(): void
    {
        if (!$this->assertAccommodationSelectedForAccounting()) {
            return;
        }

        $this->validate([
            'newEmployerName'       => ['required', 'string', 'max:200'],
            'newEmployerNationalId' => ['required', 'string', 'max:20'],
            'newEmployerMobile'     => ['required', 'regex:/^09\d{9}$/'],
        ], [], [
            'newEmployerName'       => 'نام کارفرما',
            'newEmployerNationalId' => 'کد ملی / شناسه اقتصادی',
            'newEmployerMobile'     => 'شماره همراه',
        ]);

        try {
            $province = $this->resolveAccountingProvince();
            $employerCode = app(ProvinceAccountingCodeService::class)->assignNext(
                $province,
                ProvinceAccountingIndicators::ORGANIZATION,
            );
        } catch (\Throwable $e) {
            $this->addError('newEmployerName', $e->getMessage());

            return;
        }

        $employer = ProgramEmployer::create([
            'province_id'             => $province->id,
            'name'                    => trim($this->newEmployerName),
            'employer_code'           => $employerCode,
            'national_or_economic_id' => trim($this->newEmployerNationalId),
            'mobile'                  => trim($this->newEmployerMobile),
        ]);

        try {
            $employer = app(EmployerUserProvisioner::class)->linkEmployer($employer);
        } catch (\Throwable $e) {
            $this->addError('newEmployerMobile', $e->getMessage());

            return;
        }

        $this->programEmployerId = (string) $employer->id;
        $this->closeEmployerModal();

        $message = $employer->user_id
            ? "کارفرما جدید با کد {$employerCode} ثبت شد و به حساب کاربری متصل گردید."
            : "کارفرما جدید با کد {$employerCode} به فهرست اضافه شد.";
        $this->dispatch('toast', type: 'success', message: $message);
    }

    protected function resolvedProgramEmployerId(): int
    {
        return (int) $this->programEmployerId;
    }
}

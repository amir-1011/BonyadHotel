<?php

namespace App\Livewire\Concerns;

use App\Services\BeneficiaryUserProvisioner;
use App\Models\ProgramBeneficiary;
use App\Services\ProvinceAccountingCodeService;
use App\Support\ProvinceAccountingIndicators;

trait ManagesProgramBeneficiaries
{
    use ResolvesAccountingProvince;

    public bool $showAddBeneficiary = false;

    public ?int $beneficiaryModalRowIndex = null;

    public string $newBeneficiaryName = '';
    public string $newBeneficiaryNationalId = '';
    public string $newBeneficiaryMobile = '';

    /** @var array<int, array{program_beneficiary_id:string, debt_amount:int|string, description:string, documents:array}> */
    public array $beneficiaryRows = [];

    /** @var array<int, array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile>> */
    public array $beneficiaryDocumentUploads = [];

    protected function shouldAttachBeneficiaryToCatalogRows(): bool
    {
        return true;
    }

    protected function afterBeneficiaryAddedToCatalog(ProgramBeneficiary $beneficiary): void
    {
    }

    public function openBeneficiaryModal(?int $rowIndex = null): void
    {
        if ($this->requiresAccommodationContextForCatalog() && !$this->assertAccommodationSelectedForAccounting()) {
            return;
        }

        $this->accountingProvinceManuallySet = false;
        $this->syncDefaultAccountingProvinceFromContext();
        $this->beneficiaryModalRowIndex = $rowIndex;
        $this->showAddBeneficiary = true;
        $this->newBeneficiaryName = '';
        $this->newBeneficiaryNationalId = '';
        $this->newBeneficiaryMobile = '';
        $this->resetErrorBag(['newBeneficiaryName', 'newBeneficiaryNationalId', 'newBeneficiaryMobile']);
    }

    public function closeBeneficiaryModal(): void
    {
        $this->showAddBeneficiary = false;
        $this->beneficiaryModalRowIndex = null;
        $this->resetErrorBag(['newBeneficiaryName', 'newBeneficiaryNationalId', 'newBeneficiaryMobile']);
    }

    public function previewNextBeneficiaryCode(): string
    {
        try {
            return app(ProvinceAccountingCodeService::class)->previewNext(
                $this->resolveAccountingProvince(),
                ProvinceAccountingIndicators::BENEFICIARY,
            );
        } catch (\Throwable) {
            return '—';
        }
    }

    public function addBeneficiaryToCatalog(): void
    {
        if ($this->requiresAccommodationContextForCatalog() && !$this->assertAccommodationSelectedForAccounting()) {
            return;
        }

        $this->validate([
            'newBeneficiaryName'       => ['required', 'string', 'max:200'],
            'newBeneficiaryNationalId' => ['required', 'string', 'max:20'],
            'newBeneficiaryMobile'     => ['required', 'regex:/^09\d{9}$/'],
            'accountingProvinceId'     => ['required', 'integer', 'exists:provinces,id'],
        ], [], [
            'newBeneficiaryName'       => 'نام ذینفع',
            'newBeneficiaryNationalId' => 'کد ملی / شناسه اقتصادی',
            'newBeneficiaryMobile'     => 'شماره همراه',
        ]);

        try {
            $province = $this->resolveAccountingProvince();
            $beneficiaryCode = app(ProvinceAccountingCodeService::class)->assignNext(
                $province,
                ProvinceAccountingIndicators::BENEFICIARY,
            );
        } catch (\Throwable $e) {
            $this->addError('newBeneficiaryName', $e->getMessage());

            return;
        }

        $beneficiary = ProgramBeneficiary::create([
            'province_id'             => $province->id,
            'name'                    => trim($this->newBeneficiaryName),
            'beneficiary_code'        => $beneficiaryCode,
            'national_or_economic_id' => trim($this->newBeneficiaryNationalId),
            'mobile'                  => trim($this->newBeneficiaryMobile),
        ]);

        try {
            $beneficiary = app(BeneficiaryUserProvisioner::class)->linkBeneficiary($beneficiary);
        } catch (\Throwable $e) {
            $this->addError('newBeneficiaryMobile', $e->getMessage());

            return;
        }

        if ($this->shouldAttachBeneficiaryToCatalogRows()) {
            $rowIndex = $this->beneficiaryModalRowIndex;

            if ($rowIndex !== null && isset($this->beneficiaryRows[$rowIndex])) {
                $this->beneficiaryRows[$rowIndex]['program_beneficiary_id'] = (string) $beneficiary->id;
            } else {
                $this->beneficiaryRows[] = [
                    'program_beneficiary_id' => (string) $beneficiary->id,
                    'debt_amount'            => 0,
                    'description'            => '',
                    'documents'              => [],
                ];
            }
        }

        $this->closeBeneficiaryModal();
        $message = $beneficiary->user_id
            ? "ذینفع جدید با کد {$beneficiaryCode} ثبت شد و به حساب کاربری متصل گردید."
            : "ذینفع جدید با کد {$beneficiaryCode} به فهرست اضافه شد.";
        $this->dispatch('toast', type: 'success', message: $message);
        $this->afterBeneficiaryAddedToCatalog($beneficiary);
    }

    public function addBeneficiaryRow(): void
    {
        $this->beneficiaryRows[] = [
            'program_beneficiary_id' => '',
            'debt_amount'            => 0,
            'description'            => '',
            'documents'              => [],
        ];
    }

    public function removeBeneficiaryRow(int $index): void
    {
        if (!isset($this->beneficiaryRows[$index])) {
            return;
        }

        unset($this->beneficiaryRows[$index]);
        unset($this->beneficiaryDocumentUploads[$index]);
        $this->beneficiaryRows = array_values($this->beneficiaryRows);
        $this->beneficiaryDocumentUploads = array_values($this->beneficiaryDocumentUploads);
    }

    /** @return array<int, array<string, mixed>> */
    protected function filledBeneficiaryCosts(): array
    {
        $rows = [];

        foreach ($this->beneficiaryRows as $index => $row) {
            $beneficiaryId = (int) ($row['program_beneficiary_id'] ?? 0);
            if ($beneficiaryId <= 0) {
                continue;
            }

            $rows[] = [
                'program_beneficiary_id' => $beneficiaryId,
                'debt_amount'            => (int) str_replace(',', '', (string) ($row['debt_amount'] ?? 0)),
                'description'            => trim((string) ($row['description'] ?? '')),
                'documents'              => $this->resolvedBeneficiaryDocuments($index, $row),
            ];
        }

        return $rows;
    }

    /** @param  array<string, mixed>  $row
     * @return list<\Illuminate\Http\UploadedFile>
     */
    protected function resolvedBeneficiaryDocuments(int $index, array $row): array
    {
        $files = [];

        foreach (array_merge(
            is_array($row['documents'] ?? null) ? $row['documents'] : [],
            is_array($this->beneficiaryDocumentUploads[$index] ?? null) ? $this->beneficiaryDocumentUploads[$index] : [],
        ) as $file) {
            if ($file instanceof \Illuminate\Http\UploadedFile) {
                $files[] = $file;
            }
        }

        $single = $this->beneficiaryDocumentUploads[$index] ?? null;
        if ($single instanceof \Illuminate\Http\UploadedFile) {
            $files[] = $single;
        }

        return $files;
    }
}

<?php

namespace App\Livewire\Concerns;

use App\Services\BeneficiaryUserProvisioner;
use App\Models\ProgramBeneficiary;
use Illuminate\Validation\Rule;

trait ManagesProgramBeneficiaries
{
    public bool $showAddBeneficiary = false;

    public ?int $beneficiaryModalRowIndex = null;

    public string $newBeneficiaryName = '';
    public string $newBeneficiaryCode = '';
    public string $newBeneficiaryNationalId = '';
    public string $newBeneficiaryMobile = '';

    /** @var array<int, array{program_beneficiary_id:string, debt_amount:int|string, description:string, documents:array}> */
    public array $beneficiaryRows = [];

    public function openBeneficiaryModal(?int $rowIndex = null): void
    {
        $this->beneficiaryModalRowIndex = $rowIndex;
        $this->showAddBeneficiary = true;
        $this->newBeneficiaryName = '';
        $this->newBeneficiaryCode = '';
        $this->newBeneficiaryNationalId = '';
        $this->newBeneficiaryMobile = '';
        $this->resetErrorBag(['newBeneficiaryName', 'newBeneficiaryCode', 'newBeneficiaryNationalId', 'newBeneficiaryMobile']);
    }

    public function closeBeneficiaryModal(): void
    {
        $this->showAddBeneficiary = false;
        $this->beneficiaryModalRowIndex = null;
        $this->resetErrorBag(['newBeneficiaryName', 'newBeneficiaryCode', 'newBeneficiaryNationalId', 'newBeneficiaryMobile']);
    }

    public function addBeneficiaryToCatalog(): void
    {
        $this->validate([
            'newBeneficiaryName'       => ['required', 'string', 'max:200'],
            'newBeneficiaryCode'       => [
                'required', 'string', 'max:50',
                Rule::unique('program_beneficiaries', 'beneficiary_code'),
            ],
            'newBeneficiaryNationalId' => ['required', 'string', 'max:20'],
            'newBeneficiaryMobile'     => ['required', 'regex:/^09\d{9}$/'],
        ], [], [
            'newBeneficiaryName'       => 'نام ذینفع',
            'newBeneficiaryCode'       => 'شناسه ذینفع',
            'newBeneficiaryNationalId' => 'کد ملی / شناسه اقتصادی',
            'newBeneficiaryMobile'     => 'شماره همراه',
        ]);

        $beneficiary = ProgramBeneficiary::create([
            'name'                    => trim($this->newBeneficiaryName),
            'beneficiary_code'        => trim($this->newBeneficiaryCode),
            'national_or_economic_id' => trim($this->newBeneficiaryNationalId),
            'mobile'                  => trim($this->newBeneficiaryMobile),
        ]);

        try {
            $beneficiary = app(BeneficiaryUserProvisioner::class)->linkBeneficiary($beneficiary);
        } catch (\Throwable $e) {
            $this->addError('newBeneficiaryMobile', $e->getMessage());

            return;
        }

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

        $this->closeBeneficiaryModal();
        $message = $beneficiary->user_id
            ? 'ذینفع جدید ثبت شد و به حساب کاربری متصل گردید.'
            : 'ذینفع جدید به فهرست اضافه شد.';
        $this->dispatch('toast', type: 'success', message: $message);
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
        $this->beneficiaryRows = array_values($this->beneficiaryRows);
    }

    /** @return array<int, array<string, mixed>> */
    protected function filledBeneficiaryCosts(): array
    {
        $rows = [];

        foreach ($this->beneficiaryRows as $row) {
            $beneficiaryId = (int) ($row['program_beneficiary_id'] ?? 0);
            if ($beneficiaryId <= 0) {
                continue;
            }

            $rows[] = [
                'program_beneficiary_id' => $beneficiaryId,
                'debt_amount'            => (int) str_replace(',', '', (string) ($row['debt_amount'] ?? 0)),
                'description'            => trim((string) ($row['description'] ?? '')),
                'documents'              => $row['documents'] ?? [],
            ];
        }

        return $rows;
    }
}

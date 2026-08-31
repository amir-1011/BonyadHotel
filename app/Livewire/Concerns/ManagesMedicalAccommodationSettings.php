<?php

namespace App\Livewire\Concerns;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\MedicalAccommodationContract;
use App\Models\MedicalAccommodationTariff;
use App\Models\ProgramEmployer;
use App\Models\Province;
use App\Services\MedicalAccommodationProvisioner;
use App\Support\JalaliDateTimeInput;
use App\Support\MedicalAccommodationTariffs;
use Illuminate\Validation\Rule;
use Morilog\Jalali\Jalalian;

trait ManagesMedicalAccommodationSettings
{
    use AssertsHostPermissions;
    use ManagesProgramEmployers {
        openEmployerModal as openProgramEmployerModal;
        addEmployerToCatalog as addProgramEmployerToCatalog;
    }

    public Accommodation $accommodation;

    public bool $skipCancellationPenalties = true;

    public bool $requireOvernight = true;

    public string $notes = '';

    /** @var array<int, array<string, mixed>> */
    public array $contracts = [];

    protected function bootMedicalAccommodationSettings(Accommodation $accommodation): void
    {
        $this->accommodation = $accommodation;
        app(MedicalAccommodationProvisioner::class)->seedForAccommodation($accommodation);
        $this->loadMedicalAccommodationData();
    }

    public function loadMedicalAccommodationData(): void
    {
        $setting = $this->accommodation->medicalAccommodationSetting()->first();

        $this->programEmployerId = $setting?->program_employer_id
            ? (string) $setting->program_employer_id
            : '';
        $this->skipCancellationPenalties = (bool) ($setting?->skip_cancellation_penalties ?? true);
        $this->requireOvernight = (bool) ($setting?->require_overnight ?? true);
        $this->notes = (string) ($setting?->notes ?? '');

        $this->contracts = MedicalAccommodationContract::query()
            ->forAccommodation($this->accommodation->id)
            ->with('tariffs')
            ->orderBy('id')
            ->get()
            ->map(fn (MedicalAccommodationContract $contract) => $this->contractToForm($contract))
            ->values()
            ->all();
    }

    public function saveSettings(): void
    {
        $this->assertHostCan('accommodations.medical-accommodation', 'edit');

        $this->validate([
            'programEmployerId'         => ['required', 'integer', 'exists:program_employers,id'],
            'skipCancellationPenalties' => ['boolean'],
            'requireOvernight'          => ['boolean'],
            'notes'                     => ['nullable', 'string', 'max:2000'],
        ], [
            'programEmployerId.required' => 'انتخاب کارفرمای اسکان درمانی (بیمه دی) الزامی است.',
        ]);

        $setting = app(MedicalAccommodationProvisioner::class)->seedForAccommodation($this->accommodation);
        $setting->update([
            'program_employer_id'         => $this->resolvedProgramEmployerId(),
            'skip_cancellation_penalties' => $this->skipCancellationPenalties,
            'require_overnight'           => $this->requireOvernight,
            'notes'                       => $this->notes !== '' ? $this->notes : null,
        ]);

        $this->dispatch('toast', type: 'success', message: 'تنظیمات اسکان درمانی ذخیره شد.');
    }

    public function saveContract(int $index): void
    {
        $this->assertHostCan('accommodations.medical-accommodation', 'edit');
        $row = $this->contracts[$index] ?? null;
        if (!$row) {
            return;
        }

        $this->normalizeContractMoney($index);

        $this->validate($this->contractValidationRules($index), [
            "contracts.{$index}.contract_number.required" => 'شماره قرارداد الزامی است.',
            "contracts.{$index}.contract_number.unique"   => 'این شماره قرارداد قبلاً ثبت شده است.',
            "contracts.{$index}.contract_number.regex"    => 'شماره قرارداد فقط می‌تواند شامل حرف (فارسی یا لاتین)، عدد، خط تیره یا ممیز باشد.',
            "contracts.{$index}.program_employer_id.required" => 'انتخاب کارفرما برای این قرارداد الزامی است.',
        ]);

        $startsOn = $this->parseOptionalJalaliDate(
            (string) ($row['starts_on_jalali'] ?? ''),
            "contracts.{$index}.starts_on_jalali",
        );
        $endsOn = $this->parseOptionalJalaliDate(
            (string) ($row['ends_on_jalali'] ?? ''),
            "contracts.{$index}.ends_on_jalali",
        );

        if ($this->getErrorBag()->has("contracts.{$index}.starts_on_jalali")
            || $this->getErrorBag()->has("contracts.{$index}.ends_on_jalali")
        ) {
            return;
        }

        if ($startsOn && $endsOn && $endsOn < $startsOn) {
            $this->addError("contracts.{$index}.ends_on_jalali", 'تاریخ پایان قرارداد باید بعد از تاریخ شروع باشد.');

            return;
        }

        if (!$this->validateContractTariffs($index)) {
            return;
        }

        $contract = MedicalAccommodationContract::query()
            ->forAccommodation($this->accommodation->id)
            ->whereKey($row['id'])
            ->firstOrFail();

        $contract->update([
            'contract_number'     => trim((string) $row['contract_number']),
            'program_employer_id' => (int) $row['program_employer_id'],
            'starts_on'           => $startsOn,
            'ends_on'             => $endsOn,
            'is_active'           => !empty($row['is_active']),
            'notes'               => ($row['notes'] ?? '') !== '' ? $row['notes'] : null,
        ]);

        $this->persistContractTariffs($index, (int) $contract->id);
        $this->loadMedicalAccommodationData();
        $this->dispatch('toast', type: 'success', message: 'قرارداد '.$contract->contract_number.' ذخیره شد.');
    }

    public function addContract(): void
    {
        $this->assertHostCan('accommodations.medical-accommodation', 'edit');

        $setting = app(MedicalAccommodationProvisioner::class)->seedForAccommodation($this->accommodation);
        $employerId = $this->resolvedProgramEmployerId() ?: $setting->program_employer_id;

        $contract = app(MedicalAccommodationProvisioner::class)->createContract($this->accommodation, [
            'program_employer_id' => $employerId,
            'seed_tariffs'        => true,
        ]);

        $this->loadMedicalAccommodationData();
        $this->dispatch('toast', type: 'success', message: 'قرارداد جدید با شماره '.$contract->contract_number.' ایجاد شد.');
    }

    public function removeContract(int $index): void
    {
        $this->assertHostCan('accommodations.medical-accommodation', 'edit');
        $row = $this->contracts[$index] ?? null;
        if (!$row) {
            return;
        }

        if (count($this->contracts) <= 1) {
            $this->dispatch('toast', type: 'error', message: 'حداقل یک قرارداد باید باقی بماند.');

            return;
        }

        $contractId = (int) ($row['id'] ?? 0);
        if ($contractId && Booking::query()->where('medical_contract_id', $contractId)->exists()) {
            $this->dispatch('toast', type: 'error', message: 'این قرارداد روی رزرو ثبت‌شده استفاده شده و قابل حذف نیست.');

            return;
        }

        if ($contractId) {
            MedicalAccommodationContract::query()
                ->forAccommodation($this->accommodation->id)
                ->whereKey($contractId)
                ->delete();
        }

        $this->loadMedicalAccommodationData();
        $this->dispatch('toast', type: 'success', message: 'قرارداد حذف شد.');
    }

    public function unlockContractNumber(int $index): void
    {
        $this->assertHostCan('accommodations.medical-accommodation', 'edit');
        if (!isset($this->contracts[$index])) {
            return;
        }

        $this->contracts[$index]['number_locked'] = false;
    }

    public function addTariff(int $contractIndex): void
    {
        $this->assertHostCan('accommodations.medical-accommodation', 'edit');
        if (!isset($this->contracts[$contractIndex])) {
            return;
        }

        $templateKey = (string) ($this->contracts[$contractIndex]['new_tariff_template'] ?? '');
        $template = MedicalAccommodationTariffs::definition($templateKey);
        $this->contracts[$contractIndex]['tariffs'][] = [
            'id'                     => null,
            'key'                    => $template['key'] ?? ('custom_'.time()),
            'label'                  => $template['label'] ?? '',
            'nightly_rate'           => $template['nightly_rate'] ?? 0,
            'companion_nightly_rate' => $template['companion_nightly_rate'] ?? 0,
            'companions_included'    => $template['companions_included'] ?? 0,
            'max_companions'         => $template['max_companions'] ?? 1,
            'notes'                  => $template['notes'] ?? '',
            'is_active'              => true,
        ];
        $this->contracts[$contractIndex]['new_tariff_template'] = '';
    }

    public function removeTariff(int $contractIndex, int $tariffIndex): void
    {
        $this->assertHostCan('accommodations.medical-accommodation', 'edit');
        $row = $this->contracts[$contractIndex]['tariffs'][$tariffIndex] ?? null;
        if ($row && !empty($row['id'])) {
            MedicalAccommodationTariff::query()
                ->where('accommodation_id', $this->accommodation->id)
                ->where('contract_id', $this->contracts[$contractIndex]['id'] ?? 0)
                ->whereKey($row['id'])
                ->delete();
        }

        unset($this->contracts[$contractIndex]['tariffs'][$tariffIndex]);
        $this->contracts[$contractIndex]['tariffs'] = array_values($this->contracts[$contractIndex]['tariffs']);
        $this->dispatch('toast', type: 'success', message: 'تعرفه حذف شد.');
    }

    public function restoreDefaultMedicalAccommodation(): void
    {
        $this->assertHostCan('accommodations.medical-accommodation', 'edit');
        app(MedicalAccommodationProvisioner::class)->restoreDefaultsForAccommodation($this->accommodation);
        $this->loadMedicalAccommodationData();
        $this->dispatch('toast', type: 'success', message: 'تعرفه‌ها و تنظیمات پیش‌فرض بیمه دی بازگردانی شد.');
    }

    public function openEmployerModal(): void
    {
        $this->assertHostCan('accommodations.medical-accommodation', 'edit');
        $this->openProgramEmployerModal();
    }

    public function addEmployerToCatalog(): void
    {
        $this->assertHostCan('accommodations.medical-accommodation', 'edit');
        $this->addProgramEmployerToCatalog();
    }

    /**
     * @return \Illuminate\Support\Collection<int, ProgramEmployer>
     */
    public function employerOptions()
    {
        return ProgramEmployer::query()->with('province')->orderBy('name')->get();
    }

    protected function accountingProvince(): ?Province
    {
        return $this->resolveAccountingProvinceFromAccommodation($this->accommodation);
    }

    /**
     * @return array<string, mixed>
     */
    private function contractToForm(MedicalAccommodationContract $contract): array
    {
        return [
            'id'                   => $contract->id,
            'contract_number'      => $contract->contract_number,
            'number_locked'        => true,
            'program_employer_id'  => $contract->program_employer_id ? (string) $contract->program_employer_id : $this->programEmployerId,
            'starts_on_jalali'     => $contract->starts_on ? Jalalian::fromCarbon($contract->starts_on)->format('Y/m/d') : '',
            'ends_on_jalali'       => $contract->ends_on ? Jalalian::fromCarbon($contract->ends_on)->format('Y/m/d') : '',
            'is_active'            => $contract->is_active,
            'notes'                => $contract->notes ?? '',
            'new_tariff_template'  => '',
            'tariffs'              => $contract->tariffs->map(fn (MedicalAccommodationTariff $tariff) => [
                'id'                     => $tariff->id,
                'key'                    => $tariff->key,
                'label'                  => $tariff->label,
                'nightly_rate'           => $tariff->nightly_rate,
                'companion_nightly_rate' => $tariff->companion_nightly_rate,
                'companions_included'    => $tariff->companions_included,
                'max_companions'         => $tariff->max_companions,
                'notes'                  => $tariff->notes ?? '',
                'is_active'              => $tariff->is_active,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function contractValidationRules(int $index): array
    {
        $id = $this->contracts[$index]['id'] ?? null;

        return [
            "contracts.{$index}.contract_number" => [
                'required',
                'string',
                'max:40',
                'regex:/^[\\p{L}\\p{N}][\\p{L}\\p{N}\\-_\\/]*$/u',
                Rule::unique('medical_accommodation_contracts', 'contract_number')
                    ->where('accommodation_id', $this->accommodation->id)
                    ->ignore($id),
            ],
            "contracts.{$index}.program_employer_id" => ['required', 'integer', 'exists:program_employers,id'],
            "contracts.{$index}.starts_on_jalali"    => ['nullable', 'string', 'max:20'],
            "contracts.{$index}.ends_on_jalali"      => ['nullable', 'string', 'max:20'],
            "contracts.{$index}.tariffs.*.label"     => ['required', 'string', 'max:200'],
            "contracts.{$index}.tariffs.*.nightly_rate" => ['required', 'integer', 'min:0'],
            "contracts.{$index}.tariffs.*.companion_nightly_rate" => ['required', 'integer', 'min:0'],
            "contracts.{$index}.tariffs.*.companions_included" => ['required', 'integer', 'min:0', 'max:10'],
            "contracts.{$index}.tariffs.*.max_companions" => ['required', 'integer', 'min:0', 'max:10'],
            "contracts.{$index}.tariffs.*.notes"     => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function normalizeContractMoney(int $index): void
    {
        foreach ($this->contracts[$index]['tariffs'] ?? [] as $tariffIndex => $row) {
            $this->contracts[$index]['tariffs'][$tariffIndex]['nightly_rate'] = $this->moneyToInt($row['nightly_rate'] ?? 0);
            $this->contracts[$index]['tariffs'][$tariffIndex]['companion_nightly_rate'] = $this->moneyToInt($row['companion_nightly_rate'] ?? 0);
        }
    }

    private function validateContractTariffs(int $index): bool
    {
        foreach ($this->contracts[$index]['tariffs'] ?? [] as $tariffIndex => $row) {
            $included = (int) ($row['companions_included'] ?? 0);
            $max = (int) ($row['max_companions'] ?? 0);
            if ($included > $max) {
                $this->addError(
                    "contracts.{$index}.tariffs.{$tariffIndex}.companions_included",
                    'تعداد همراه مشمول نمی‌تواند از سقف همراه بیشتر باشد.',
                );

                return false;
            }
        }

        return true;
    }

    private function persistContractTariffs(int $index, int $contractId): void
    {
        foreach ($this->contracts[$index]['tariffs'] ?? [] as $tariffIndex => $row) {
            $payload = [
                'label'                  => $row['label'],
                'nightly_rate'           => $this->moneyToInt($row['nightly_rate']),
                'companion_nightly_rate' => $this->moneyToInt($row['companion_nightly_rate']),
                'companions_included'    => (int) $row['companions_included'],
                'max_companions'         => (int) $row['max_companions'],
                'notes'                  => ($row['notes'] ?? '') !== '' ? $row['notes'] : null,
                'is_active'              => !empty($row['is_active']),
                'sort_order'             => $tariffIndex + 1,
                'contract_id'            => $contractId,
                'accommodation_id'       => $this->accommodation->id,
            ];

            if (!empty($row['id'])) {
                MedicalAccommodationTariff::query()
                    ->where('accommodation_id', $this->accommodation->id)
                    ->where('contract_id', $contractId)
                    ->whereKey($row['id'])
                    ->update($payload);
            } else {
                MedicalAccommodationTariff::create(array_merge($payload, [
                    'key' => $row['key'] ?? ('custom_'.time().'_'.$tariffIndex),
                ]));
            }
        }
    }

    private function parseOptionalJalaliDate(string $value, string $field): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $gregorian = JalaliDateTimeInput::toGregorianDate($value);
        if (!$gregorian) {
            $this->addError($field, 'تاریخ معتبر نیست. فرمت: ۱۴۰۵/۰۲/۰۱');

            return null;
        }

        return $gregorian;
    }

    private function moneyToInt(mixed $value): int
    {
        return (int) preg_replace('/\D/', '', (string) $value);
    }
}

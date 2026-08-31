<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\MedicalAccommodationContract;
use App\Models\MedicalAccommodationSetting;
use App\Models\MedicalAccommodationTariff;
use App\Models\ProgramEmployer;
use App\Models\Province;
use App\Models\User;
use App\Support\MedicalAccommodationContractNumbers;
use App\Support\MedicalAccommodationTariffs;
use App\Support\ProvinceAccountingIndicators;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MedicalAccommodationProvisioner
{
    public function seedForAccommodation(Accommodation|int $accommodation, bool $force = false): MedicalAccommodationSetting
    {
        $model = $accommodation instanceof Accommodation
            ? $accommodation
            : Accommodation::query()->findOrFail($accommodation);

        $setting = MedicalAccommodationSetting::query()->firstOrNew([
            'accommodation_id' => $model->id,
        ]);

        if (!$setting->exists) {
            $setting->skip_cancellation_penalties = true;
            $setting->require_overnight = true;
            $setting->notes = 'اسکان درمانی بر اساس تعرفه توافقی بیمه دی محاسبه می‌شود. مهمان وجه اقامت را پرداخت نمی‌کند و مانده به‌صورت بدهی کارفرما ثبت می‌شود. سیاست کنسلی و جریمه اعمال نمی‌گردد.';
        }

        $employer = $this->ensureDayInsuranceEmployer($model);
        if ($employer && $this->shouldAssignProvinceDayInsurance($setting, $employer, $model->resolvedProvince())) {
            $setting->program_employer_id = $employer->id;
        }

        $setting->save();

        if (Schema::hasTable('medical_accommodation_contracts')) {
            $this->ensureDefaultContract($model, $setting, $force);
        } else {
            $hasTariffs = MedicalAccommodationTariff::query()
                ->where('accommodation_id', $model->id)
                ->exists();
            if (!$hasTariffs && ($force || ($model->medical_accommodation_auto_seed ?? true))) {
                $this->seedTariffs($model->id);
            }
        }

        return $setting->fresh(['employer']);
    }

    public function restoreDefaultsForAccommodation(Accommodation|int $accommodation): MedicalAccommodationSetting
    {
        $model = $accommodation instanceof Accommodation
            ? $accommodation
            : Accommodation::query()->findOrFail($accommodation);

        if (Schema::hasTable('medical_accommodation_contracts')) {
            MedicalAccommodationContract::query()->where('accommodation_id', $model->id)->delete();
        }
        MedicalAccommodationTariff::query()->where('accommodation_id', $model->id)->delete();
        $model->update(['medical_accommodation_auto_seed' => true]);

        $setting = $this->seedForAccommodation($model, force: true);
        $setting->skip_cancellation_penalties = true;
        $setting->require_overnight = true;

        $employer = $this->ensureDayInsuranceEmployer($model);
        if ($employer) {
            $setting->program_employer_id = $employer->id;
        }

        $setting->save();

        return $setting->fresh(['employer']);
    }

    /**
     * Create بیمه دی for every province and bind each accommodation's medical setting to its own province employer.
     *
     * @return array{created: int, assigned: int, skipped: int}
     */
    public function syncDayInsuranceEmployers(): array
    {
        $created = 0;
        $skipped = 0;

        foreach (Province::query()->orderBy('id')->get() as $province) {
            $before = $this->findDayInsuranceEmployer($province);
            $employer = $this->ensureDayInsuranceEmployerForProvince($province);

            if (!$employer) {
                $skipped++;
                continue;
            }

            if (!$before) {
                $created++;
            }
        }

        $assigned = 0;

        foreach (Accommodation::query()
            ->with(['city.province', 'county.province', 'medicalAccommodationSetting.employer'])
            ->orderBy('id')
            ->get() as $accommodation
        ) {
            $setting = $this->seedForAccommodation($accommodation);
            if ($setting->program_employer_id) {
                $assigned++;
            }
        }

        return compact('created', 'assigned', 'skipped');
    }

    public function ensureDefaultContract(
        Accommodation $accommodation,
        MedicalAccommodationSetting $setting,
        bool $forceSeed = false,
    ): MedicalAccommodationContract {
        $contract = MedicalAccommodationContract::query()
            ->where('accommodation_id', $accommodation->id)
            ->orderBy('id')
            ->first();

        if (!$contract) {
            $contract = $this->createContract($accommodation, [
                'program_employer_id' => $setting->program_employer_id,
                'starts_on'           => $setting->contract_starts_on,
                'ends_on'             => $setting->contract_ends_on,
                'seed_tariffs'        => false,
            ]);
        } elseif (!$contract->program_employer_id && $setting->program_employer_id) {
            $contract->update(['program_employer_id' => $setting->program_employer_id]);
        }

        $this->attachOrphanTariffs($accommodation->id, $contract->id);

        $hasTariffs = MedicalAccommodationTariff::query()
            ->where('contract_id', $contract->id)
            ->exists();

        if (!$hasTariffs && ($forceSeed || ($accommodation->medical_accommodation_auto_seed ?? true))) {
            $this->seedTariffs($accommodation->id, $contract->id);
        }

        return $contract->fresh(['employer', 'tariffs']);
    }

    /**
     * @param  array{program_employer_id?:int|null, starts_on?:mixed, ends_on?:mixed, notes?:?string, seed_tariffs?:bool}  $attributes
     */
    public function createContract(Accommodation $accommodation, array $attributes = []): MedicalAccommodationContract
    {
        $number = MedicalAccommodationContractNumbers::nextForAccommodation($accommodation);
        $attempts = 0;

        while (MedicalAccommodationContract::query()
            ->where('accommodation_id', $accommodation->id)
            ->where('contract_number', $number)
            ->exists()
        ) {
            $number = MedicalAccommodationContractNumbers::nextForAccommodation($accommodation);
            if (++$attempts > 50) {
                $number .= '-'.substr((string) time(), -4);
                break;
            }
        }

        $contract = MedicalAccommodationContract::create([
            'accommodation_id'    => $accommodation->id,
            'program_employer_id' => $attributes['program_employer_id'] ?? null,
            'contract_number'     => $number,
            'starts_on'           => $attributes['starts_on'] ?? null,
            'ends_on'             => $attributes['ends_on'] ?? null,
            'is_active'           => true,
            'notes'               => $attributes['notes'] ?? null,
        ]);

        $shouldSeed = $attributes['seed_tariffs'] ?? ($accommodation->medical_accommodation_auto_seed ?? true);
        if ($shouldSeed) {
            $this->seedTariffs($accommodation->id, $contract->id);
        }

        return $contract->fresh(['tariffs', 'employer']);
    }

    private function attachOrphanTariffs(int $accommodationId, int $contractId): void
    {
        if (! Schema::hasColumn('medical_accommodation_tariffs', 'contract_id')) {
            return;
        }

        MedicalAccommodationTariff::query()
            ->where('accommodation_id', $accommodationId)
            ->whereNull('contract_id')
            ->update(['contract_id' => $contractId]);
    }

    private function seedTariffs(int $accommodationId, ?int $contractId = null): void
    {
        $hasContractColumn = Schema::hasColumn('medical_accommodation_tariffs', 'contract_id');

        foreach (MedicalAccommodationTariffs::defaults() as $row) {
            $unique = ($hasContractColumn && $contractId)
                ? ['contract_id' => $contractId, 'key' => $row['key']]
                : ['accommodation_id' => $accommodationId, 'key' => $row['key']];

            $attributes = array_merge($row, [
                'accommodation_id' => $accommodationId,
            ]);

            if ($hasContractColumn) {
                $attributes['contract_id'] = $contractId;
            }

            MedicalAccommodationTariff::query()->firstOrCreate($unique, $attributes);
        }
    }

    public function ensureDayInsuranceEmployer(Accommodation $accommodation): ?ProgramEmployer
    {
        $accommodation->loadMissing(['city.province', 'county.province']);
        $province = $accommodation->resolvedProvince();

        if (!$province) {
            return null;
        }

        return $this->ensureDayInsuranceEmployerForProvince($province);
    }

    public function ensureDayInsuranceEmployerForProvince(Province $province): ?ProgramEmployer
    {
        $match = $this->findDayInsuranceEmployer($province);
        if ($match) {
            $this->syncDayInsuranceEmployerName($match, $province);

            return $match->fresh(['user', 'province']);
        }

        try {
            return $this->createDayInsuranceEmployer($province);
        } catch (\Throwable $exception) {
            Log::warning('Could not auto-create Day Insurance employer for medical accommodation.', [
                'province_id' => $province->id,
                'error'       => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function findDayInsuranceEmployer(Province $province): ?ProgramEmployer
    {
        return ProgramEmployer::query()
            ->where('province_id', $province->id)
            ->where(function ($query) {
                $query->where('name', MedicalAccommodationTariffs::EMPLOYER_NAME)
                    ->orWhere('name', 'like', MedicalAccommodationTariffs::EMPLOYER_NAME.' %');
            })
            ->orderBy('id')
            ->first();
    }

    private function shouldAssignProvinceDayInsurance(
        MedicalAccommodationSetting $setting,
        ProgramEmployer $provinceEmployer,
        ?Province $province,
    ): bool {
        if (!$setting->program_employer_id) {
            return true;
        }

        if ((int) $setting->program_employer_id === (int) $provinceEmployer->id) {
            return false;
        }

        if (!$province) {
            return false;
        }

        $current = $setting->employer ?? ProgramEmployer::query()->find($setting->program_employer_id);
        if (!$current) {
            return true;
        }

        if (! MedicalAccommodationTariffs::isEmployerName((string) $current->name)) {
            return false;
        }

        return (int) $current->province_id !== (int) $province->id;
    }

    private function createDayInsuranceEmployer(Province $province): ProgramEmployer
    {
        $code = $this->nextEmployerCode($province);

        $employer = ProgramEmployer::create([
            'province_id'             => $province->id,
            'name'                    => MedicalAccommodationTariffs::employerNameForProvince($province->name),
            'employer_code'           => $code,
            'national_or_economic_id' => $this->placeholderNationalId($province),
            'mobile'                  => $this->placeholderMobile($province),
        ]);

        try {
            app(EmployerUserProvisioner::class)->linkEmployer($employer);
        } catch (\Throwable) {
            // Debt is stored on the employer record even without a linked user.
        }

        return $employer->fresh();
    }

    private function syncDayInsuranceEmployerName(ProgramEmployer $employer, Province $province): void
    {
        $desired = MedicalAccommodationTariffs::employerNameForProvince($province->name);

        if ($employer->name !== $desired) {
            $employer->update(['name' => $desired]);
        }

        $employer->loadMissing('user');
        $user = $employer->user;
        if (!$user || ! MedicalAccommodationTariffs::isEmployerName($user->name)) {
            return;
        }

        $shared = ProgramEmployer::query()
            ->where('user_id', $user->id)
            ->where('id', '!=', $employer->id)
            ->exists();

        if (!$shared && $user->name !== $desired) {
            $user->update(['name' => $desired]);
        }
    }

    private function nextEmployerCode(Province $province): string
    {
        try {
            return app(ProvinceAccountingCodeService::class)
                ->assignNext($province, ProvinceAccountingIndicators::ORGANIZATION);
        } catch (\Throwable) {
            $fallback = 'DAY'.str_pad((string) $province->id, 3, '0', STR_PAD_LEFT);

            if (! ProgramEmployer::query()->where('employer_code', $fallback)->exists()) {
                return $fallback;
            }

            return 'DAY'.$province->id.substr((string) time(), -3);
        }
    }

    private function placeholderNationalId(Province $province): string
    {
        $code = preg_replace('/\D/', '', (string) ($province->accounting_code ?: $province->id)) ?? '0';

        return 'DAY'.str_pad(substr($code, -3), 3, '0', STR_PAD_LEFT);
    }

    private function placeholderMobile(Province $province): string
    {
        $digits = preg_replace('/\D/', '', (string) ($province->accounting_code ?: $province->id)) ?? '0';
        $digits = str_pad(substr($digits, -3), 3, '0', STR_PAD_LEFT);

        for ($n = 0; $n < 10000; $n++) {
            $candidate = '0910'.$digits.str_pad((string) $n, 4, '0', STR_PAD_LEFT);

            $taken = ProgramEmployer::query()->where('mobile', $candidate)->exists()
                || User::query()->where('mobile', $candidate)->exists();

            if (! $taken) {
                return $candidate;
            }
        }

        return '0910'.$digits.substr((string) time(), -4);
    }
}

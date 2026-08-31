<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProgramBeneficiary;
use App\Models\ProgramEmployer;
use App\Models\Province;
use App\Models\User;
use App\Support\ProvinceAccountingIndicators;
use InvalidArgumentException;

class AccountingProvinceReassignmentService
{
    public function __construct(
        private readonly ProvinceAccountingCodeService $codeService,
    ) {}

    public function willProvinceChangeAffectCode(?string $currentCode, Province $newProvince): bool
    {
        if ($currentCode === null || $currentCode === '') {
            return true;
        }

        $parsed = $this->codeService->parseAccountingCode($currentCode);

        if ($parsed === null) {
            return true;
        }

        $newProvince = $this->codeService->ensureProvinceHasCode($newProvince);

        return $parsed['province_code'] !== (string) $newProvince->accounting_code;
    }

    public function previewNextCode(Province $province, int $indicator): string
    {
        return $this->codeService->previewNext($province, $indicator);
    }

    public function currentCodeForUser(User $user): ?string
    {
        if ($user->isHost() && filled($user->personnel_code)) {
            return (string) $user->personnel_code;
        }

        if ($user->isProgramEmployer()) {
            $employer = $user->relationLoaded('programEmployer')
                ? $user->programEmployer
                : $user->programEmployer()->first();

            return filled($employer?->employer_code) ? (string) $employer->employer_code : null;
        }

        if ($user->isProgramBeneficiary()) {
            $beneficiary = $user->relationLoaded('programBeneficiary')
                ? $user->programBeneficiary
                : $user->programBeneficiary()->first();

            return filled($beneficiary?->beneficiary_code) ? (string) $beneficiary->beneficiary_code : null;
        }

        return null;
    }

    public function reassignPersonnelCode(User $user, Province $province): string
    {
        if (!$user->isHost()) {
            throw new InvalidArgumentException('تغییر کد پرسنلی فقط برای میزبان امکان‌پذیر است.');
        }

        $code = $this->codeService->assignNext(
            $province,
            ProvinceAccountingIndicators::PERSONNEL,
        );

        $user->forceFill([
            'province_id'    => $province->id,
            'personnel_code' => $code,
        ])->save();

        return $code;
    }

    public function reassignEmployerCode(ProgramEmployer $employer, Province $province): string
    {
        $code = $this->codeService->assignNext(
            $province,
            ProvinceAccountingIndicators::ORGANIZATION,
        );

        $employer->forceFill([
            'province_id'   => $province->id,
            'employer_code' => $code,
        ])->save();

        return $code;
    }

    public function reassignBeneficiaryCode(ProgramBeneficiary $beneficiary, Province $province): string
    {
        $code = $this->codeService->assignNext(
            $province,
            ProvinceAccountingIndicators::BENEFICIARY,
        );

        $beneficiary->forceFill([
            'province_id'       => $province->id,
            'beneficiary_code'  => $code,
        ])->save();

        return $code;
    }

    public function reassignForUser(User $user, Province $province): string
    {
        if ($user->isHost()) {
            return $this->reassignPersonnelCode($user, $province);
        }

        if ($user->isProgramEmployer()) {
            $employer = $user->relationLoaded('programEmployer')
                ? $user->programEmployer
                : $user->programEmployer()->firstOrFail();

            return $this->reassignEmployerCode($employer, $province);
        }

        if ($user->isProgramBeneficiary()) {
            $beneficiary = $user->relationLoaded('programBeneficiary')
                ? $user->programBeneficiary
                : $user->programBeneficiary()->firstOrFail();

            return $this->reassignBeneficiaryCode($beneficiary, $province);
        }

        throw new InvalidArgumentException('این کاربر کدینگ حسابداری ندارد.');
    }

    public function accountingIndicatorForUser(User $user): ?int
    {
        if ($user->isHost()) {
            return ProvinceAccountingIndicators::PERSONNEL;
        }

        if ($user->isProgramEmployer()) {
            return ProvinceAccountingIndicators::ORGANIZATION;
        }

        if ($user->isProgramBeneficiary()) {
            return ProvinceAccountingIndicators::BENEFICIARY;
        }

        return null;
    }
}

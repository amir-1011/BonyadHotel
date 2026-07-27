<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProgramBeneficiary;
use App\Models\ProgramEmployer;
use App\Models\User;

/**
 * جستجوی موجودیت‌ها با کد حسابداری استانی (مشابه نقش کد ملی در پذیرش مهمان).
 */
class AccountingEntityLookup
{
    public function __construct(
        private readonly ProvinceAccountingCodeService $codeService,
    ) {}

    public function findUserByIdentifier(string $identifier): ?User
    {
        $digits = preg_replace('/\D/', '', $identifier) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            return User::query()->where('national_id', $digits)->first();
        }

        if (!$this->codeService->isAccountingCode($digits)) {
            return null;
        }

        $user = User::query()->where('personnel_code', $digits)->first();

        if ($user) {
            return $user;
        }

        $employer = ProgramEmployer::query()->where('employer_code', $digits)->first();

        if ($employer?->user_id) {
            return $employer->user;
        }

        $beneficiary = ProgramBeneficiary::query()->where('beneficiary_code', $digits)->first();

        if ($beneficiary?->user_id) {
            return $beneficiary->user;
        }

        return null;
    }

    public function entityLabelForCode(string $code): ?string
    {
        $digits = preg_replace('/\D/', '', $code) ?? '';

        if (!$this->codeService->isAccountingCode($digits)) {
            return null;
        }

        $parsed = $this->codeService->parseAccountingCode($digits);

        if ($parsed === null) {
            return null;
        }

        $indicatorLabel = \App\Support\ProvinceAccountingIndicators::label($parsed['indicator']);

        return "کد حسابداری {$digits} ({$indicatorLabel})";
    }
}

<?php

namespace App\Models\Concerns;

use App\Models\ProgramBeneficiary;
use App\Models\ProgramEmployer;
use App\Models\Province;
use App\Services\ProvinceAccountingCodeService;
use App\Support\ProvinceAccountingIndicators;

trait DisplaysAccountingProfile
{
    /**
     * @return array{
     *     code: string,
     *     entity_type_label: string,
     *     province_code: ?string,
     *     province_name: ?string,
     *     indicator: ?int,
     *     indicator_label: ?string,
     *     counter: ?int,
     * }|null
     */
    public function accountingProfileDetails(): ?array
    {
        [$code, $province, $entityLabel] = $this->resolveAccountingIdentity();

        if ($code === null || $code === '') {
            return null;
        }

        $parsed = app(ProvinceAccountingCodeService::class)->parseAccountingCode($code);

        return [
            'code'              => $code,
            'entity_type_label' => $entityLabel,
            'province_code'     => $parsed['province_code'] ?? $province?->accounting_code,
            'province_name'     => $province?->name,
            'indicator'         => $parsed['indicator'] ?? null,
            'indicator_label'   => isset($parsed['indicator'])
                ? ProvinceAccountingIndicators::label($parsed['indicator'])
                : $entityLabel,
            'counter'           => $parsed['counter'] ?? null,
        ];
    }

    public function hasAccountingProfile(): bool
    {
        return $this->accountingProfileDetails() !== null;
    }

    /** @return array{0: ?string, 1: ?Province, 2: string} */
    private function resolveAccountingIdentity(): array
    {
        if ($this->isHost() && filled($this->personnel_code)) {
            $province = $this->relationLoaded('province')
                ? $this->province
                : $this->province()->first();

            if (!$province && $this->isHost()) {
                $province = app(\App\Services\HostPersonnelCodeProvisioner::class)
                    ->resolveProvinceFromAccommodations($this);
            }

            return [(string) $this->personnel_code, $province, 'پرسنل'];
        }

        if ($this->isProgramEmployer()) {
            $employer = $this->relationLoaded('programEmployer')
                ? $this->programEmployer
                : $this->programEmployer()->with('province')->first();

            if ($employer instanceof ProgramEmployer && filled($employer->employer_code)) {
                return [(string) $employer->employer_code, $employer->province, 'ارگان / اداره'];
            }
        }

        if ($this->isProgramBeneficiary()) {
            $beneficiary = $this->relationLoaded('programBeneficiary')
                ? $this->programBeneficiary
                : $this->programBeneficiary()->with('province')->first();

            if ($beneficiary instanceof ProgramBeneficiary && filled($beneficiary->beneficiary_code)) {
                return [(string) $beneficiary->beneficiary_code, $beneficiary->province, 'ذینفع'];
            }
        }

        return [null, null, ''];
    }
}

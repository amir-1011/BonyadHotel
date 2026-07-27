<?php

namespace App\Models\Concerns;

use App\Services\ProvinceAccountingCodeService;
use App\Support\ProvinceAccountingIndicators;

trait HasProgramAccountingProfile
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
        $code = $this->accountingCodeValue();

        if ($code === null || $code === '') {
            return null;
        }

        $province = $this->relationLoaded('province')
            ? $this->province
            : $this->province()->first();

        $parsed = app(ProvinceAccountingCodeService::class)->parseAccountingCode($code);
        $entityLabel = $this->accountingEntityTypeLabel();

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

    abstract protected function accountingCodeValue(): ?string;

    abstract protected function accountingEntityTypeLabel(): string;
}

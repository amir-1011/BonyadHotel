<?php

namespace App\Services;

/**
 * Mock National ID Verification Service
 *
 * For production, replace this with actual integration to Sabte Ahval or Basij API.
 *
 * Test national IDs (prefix):
 *  - 111*: همسر شهید (martyr_spouse_dependents)
 *  - 112*: فرزندان شهدا (martyr_children)
 *  - 113*: والدین شهدا (martyr_parents_dependents)
 *  - 222*: جانباز ۲۵–۴۹ (veteran_25_49_dependents)
 *  - 333*: جانباز ۵۰–۶۹ (veteran_50_69_dependents)
 *  - 444*: جانباز ۷۰+ (veteran_70_spouses)
 *  - 555*: آزادگان (freed_prisoner_dependents)
 *  - All others: کاربر عادی (0%)
 */
class NationalIdVerificationService
{
    /** @deprecated Use VeteranPolicyService::accommodationDiscount() */
    public const DISCOUNT_MAP = [];

    public function __construct(
        private readonly VeteranPolicyService $veteranPolicy
    ) {}

    public function verify(string $nationalId): array
    {
        if (!$this->isValidFormat($nationalId)) {
            return ['valid' => false, 'message' => 'کد ملی وارد شده معتبر نیست.'];
        }

        $veteranType = $this->detectVeteranType($nationalId);
        $discount = $this->veteranPolicy->accommodationDiscount($veteranType);

        return [
            'valid'        => true,
            'veteran_type' => $veteranType,
            'discount'     => $discount,
        ];
    }

    private function isValidFormat(string $id): bool
    {
        if (!preg_match('/^\d{10}$/', $id)) {
            return false;
        }

        if (preg_match('/^(\d)\1{9}$/', $id)) {
            return false;
        }

        return true;
    }

    private function detectVeteranType(string $id): ?string
    {
        $prefix = substr($id, 0, 3);

        return match (true) {
            str_starts_with($prefix, '111') => 'martyr_spouse_dependents',
            str_starts_with($prefix, '112') => 'martyr_children',
            str_starts_with($prefix, '113') => 'martyr_parents_dependents',
            str_starts_with($prefix, '222') => 'veteran_25_49_dependents',
            str_starts_with($prefix, '333') => 'veteran_50_69_dependents',
            str_starts_with($prefix, '444') => 'veteran_70_spouses',
            str_starts_with($prefix, '555') => 'freed_prisoner_dependents',
            default                         => null,
        };
    }
}

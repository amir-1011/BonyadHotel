<?php

namespace App\Services;

/**
 * Mock National ID Verification Service
 * 
 * For production, replace this with actual integration to Sabte Ahval or Basij API.
 * 
 * Test national IDs:
 *  - Starting with 111: خانواده شهید (50%)
 *  - Starting with 222: جانباز ۲۵ تا ۴۹ درصد (30%)
 *  - Starting with 333: جانباز ۵۰ تا ۶۹ درصد (40%)
 *  - Starting with 444: جانباز ۷۰ درصد و بالاتر (50%)
 *  - Starting with 555: خانواده آزاده (40%)
 *  - All others: کاربر عادی (0%)
 */
class NationalIdVerificationService
{
    public const DISCOUNT_MAP = [
        'martyr_family'         => 50,
        'veteran_25_49'         => 30,
        'veteran_50_69'         => 40,
        'veteran_70_plus'       => 50,
        'freed_prisoner_family' => 40,
    ];

    public function verify(string $nationalId): array
    {
        if (!$this->isValidFormat($nationalId)) {
            return ['valid' => false, 'message' => 'کد ملی وارد شده معتبر نیست.'];
        }

        $veteranType    = $this->detectVeteranType($nationalId);
        $discount       = self::DISCOUNT_MAP[$veteranType] ?? 0;

        return [
            'valid'        => true,
            'veteran_type' => $veteranType,
            'discount'     => $discount,
        ];
    }

    private function isValidFormat(string $id): bool
    {
        // Basic format: exactly 10 digits, not all same digit
        if (!preg_match('/^\d{10}$/', $id)) {
            return false;
        }

        // Reject all-same-digit IDs (e.g. 0000000000, 1111111111)
        if (preg_match('/^(\d)\1{9}$/', $id)) {
            return false;
        }

        return true;
    }

    private function detectVeteranType(string $id): ?string
    {
        $prefix = substr($id, 0, 3);

        return match(true) {
            str_starts_with($prefix, '111') => 'martyr_family',
            str_starts_with($prefix, '222') => 'veteran_25_49',
            str_starts_with($prefix, '333') => 'veteran_50_69',
            str_starts_with($prefix, '444') => 'veteran_70_plus',
            str_starts_with($prefix, '555') => 'freed_prisoner_family',
            default                          => null,
        };
    }
}

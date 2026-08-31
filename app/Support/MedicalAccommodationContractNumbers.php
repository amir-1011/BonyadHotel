<?php

namespace App\Support;

use App\Models\Accommodation;
use App\Models\MedicalAccommodationContract;
use App\Models\Province;
use App\Services\ProvinceAccountingCodeService;
use Morilog\Jalali\Jalalian;

/**
 * Default medical-accommodation contract numbers: {jalaliYear}{provinceCode}{sequence}
 * Example: 140550201 = 1405 (year) + 502 (province) + 01 (id).
 */
final class MedicalAccommodationContractNumbers
{
    public static function nextForAccommodation(Accommodation $accommodation): string
    {
        $accommodation->loadMissing(['city.province', 'county.province']);
        $province = $accommodation->resolvedProvince();

        if (!$province) {
            return 'DAY'.Jalalian::now()->format('YmdHis');
        }

        return self::compose($province, $accommodation->id);
    }

    public static function nextForProvince(Province $province, ?int $accommodationId = null): string
    {
        return self::compose($province, $accommodationId);
    }

    public static function prefixForProvince(Province $province): string
    {
        try {
            $province = app(ProvinceAccountingCodeService::class)->ensureProvinceHasCode($province);
        } catch (\Throwable) {
            // Fall back to a 3-digit id fragment.
        }

        $year = Jalalian::now()->format('Y');
        $code = preg_replace('/\D/', '', (string) ($province->accounting_code ?: $province->id)) ?? '0';
        $code = str_pad(substr($code, -3), 3, '0', STR_PAD_LEFT);

        return $year.$code;
    }

    private static function compose(Province $province, ?int $accommodationId): string
    {
        $prefix = self::prefixForProvince($province);
        $sequence = self::nextSequence($prefix, $accommodationId);

        return $prefix.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT);
    }

    public static function isValidFormat(string $number): bool
    {
        $number = trim($number);

        return $number !== '' && (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9\-_\/]*$/', $number);
    }

    private static function nextSequence(string $prefix, ?int $accommodationId = null): int
    {
        $max = 0;

        $query = MedicalAccommodationContract::query()
            ->where('contract_number', 'like', $prefix.'%');

        if ($accommodationId) {
            $query->where('accommodation_id', $accommodationId);
        }

        $numbers = $query->pluck('contract_number');

        foreach ($numbers as $number) {
            $suffix = substr((string) $number, strlen($prefix));
            if ($suffix !== '' && ctype_digit($suffix)) {
                $max = max($max, (int) $suffix);
            }
        }

        return $max + 1;
    }
}

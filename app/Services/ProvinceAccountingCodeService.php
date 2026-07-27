<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProgramBeneficiary;
use App\Models\ProgramEmployer;
use App\Models\Province;
use App\Models\User;
use App\Support\ProvinceAccountingIndicators;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ProvinceAccountingCodeService
{
    private const COUNTER_WIDTH = 2;

    public function ensureProvinceHasCode(Province $province): Province
    {
        if ($this->isValidProvinceCode($province->accounting_code)) {
            return $province;
        }

        $resolved = \App\Support\ProvinceAccountingCodeCatalog::resolveForName((string) $province->name);

        if ($resolved === null) {
            throw new RuntimeException(
                "کد حسابداری برای استان «{$province->name}» تعریف نشده است. لطفاً از بخش مدیریت استان‌ها کد سه‌رقمی را ثبت کنید."
            );
        }

        $province->forceFill(['accounting_code' => $resolved])->save();

        return $province->fresh();
    }

    public function previewNext(Province $province, int $indicator): string
    {
        $province = $this->ensureProvinceHasCode($province);
        $next = $this->nextCounter($province, $indicator);

        return $this->formatCode((string) $province->accounting_code, $indicator, $next);
    }

    public function assignNext(Province $province, int $indicator): string
    {
        $this->assertIndicator($indicator);

        return DB::transaction(function () use ($province, $indicator) {
            $locked = Province::query()->whereKey($province->id)->lockForUpdate()->firstOrFail();
            $locked = $this->ensureProvinceHasCode($locked);

            $counter = $this->nextCounter($locked, $indicator);
            $code = $this->formatCode((string) $locked->accounting_code, $indicator, $counter);

            if ($this->codeExists($code)) {
                throw new RuntimeException("کد حسابداری «{$code}» قبلاً ثبت شده است.");
            }

            return $code;
        });
    }

    public function isAccountingCode(string $value): bool
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        return (bool) preg_match('/^\d{6}$/', $digits);
    }

    public function parseAccountingCode(string $code): ?array
    {
        $digits = preg_replace('/\D/', '', $code) ?? '';

        if (!preg_match('/^(\d{3})(\d)(\d{2,})$/', $digits, $matches)) {
            return null;
        }

        $indicator = (int) $matches[2];

        if (!in_array($indicator, ProvinceAccountingIndicators::all(), true)) {
            return null;
        }

        return [
            'province_code' => $matches[1],
            'indicator'     => $indicator,
            'counter'       => (int) $matches[3],
            'full'          => $digits,
        ];
    }

    private function nextCounter(Province $province, int $indicator): int
    {
        $prefix = (string) $province->accounting_code . (string) $indicator;
        $max = 0;

        foreach ($this->existingCodesForIndicator($indicator) as $existing) {
            if (!str_starts_with($existing, $prefix)) {
                continue;
            }

            $counterPart = substr($existing, strlen($prefix));

            if ($counterPart !== '' && ctype_digit($counterPart)) {
                $max = max($max, (int) $counterPart);
            }
        }

        return $max + 1;
    }

    /** @return list<string> */
    private function existingCodesForIndicator(int $indicator): array
    {
        return match ($indicator) {
            ProvinceAccountingIndicators::BENEFICIARY => ProgramBeneficiary::query()
                ->whereNotNull('beneficiary_code')
                ->pluck('beneficiary_code')
                ->all(),
            ProvinceAccountingIndicators::ORGANIZATION => ProgramEmployer::query()
                ->whereNotNull('employer_code')
                ->pluck('employer_code')
                ->all(),
            ProvinceAccountingIndicators::PERSONNEL => User::query()
                ->whereNotNull('personnel_code')
                ->pluck('personnel_code')
                ->all(),
            default => [],
        };
    }

    private function formatCode(string $provinceCode, int $indicator, int $counter): string
    {
        if (!$this->isValidProvinceCode($provinceCode)) {
            throw new InvalidArgumentException('کد استان باید دقیقاً سه رقم باشد.');
        }

        $this->assertIndicator($indicator);

        if ($counter < 1) {
            throw new InvalidArgumentException('شمارنده باید بزرگ‌تر از صفر باشد.');
        }

        $width = max(self::COUNTER_WIDTH, strlen((string) $counter));

        return $provinceCode . $indicator . str_pad((string) $counter, $width, '0', STR_PAD_LEFT);
    }

    private function codeExists(string $code): bool
    {
        return ProgramBeneficiary::where('beneficiary_code', $code)->exists()
            || ProgramEmployer::where('employer_code', $code)->exists()
            || User::where('personnel_code', $code)->exists();
    }

    private function isValidProvinceCode(?string $code): bool
    {
        return is_string($code) && (bool) preg_match('/^\d{3}$/', $code);
    }

    private function assertIndicator(int $indicator): void
    {
        if (!in_array($indicator, ProvinceAccountingIndicators::all(), true)) {
            throw new InvalidArgumentException("شاخص کدینگ «{$indicator}» معتبر نیست.");
        }
    }
}

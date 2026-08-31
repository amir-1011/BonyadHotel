<?php

namespace App\Support;

use App\Models\Province;
use App\Models\User;
use App\Services\ProvinceAccountingCodeService;
use Illuminate\Support\Collection;

class AdminUserProvinceGrouper
{
    public const UNKNOWN_KEY = '__unknown__';

    /**
     * @param  Collection<int, User>  $users
     * @return list<array{
     *     key: string,
     *     province_code: ?string,
     *     province_name: string,
     *     users: list<User>
     * }>
     */
    public static function group(Collection $users): array
    {
        $groups = [];
        $codeService = app(ProvinceAccountingCodeService::class);

        foreach ($users as $user) {
            $profile = $user->accountingProfileDetails();
            $provinceCode = $profile['province_code'] ?? null;
            $provinceName = $profile['province_name'] ?? null;

            if ($provinceName === null && $provinceCode !== null) {
                $provinceName = Province::query()
                    ->where('accounting_code', $provinceCode)
                    ->value('name');
            }

            if ($provinceCode === null && $profile !== null) {
                $parsed = $codeService->parseAccountingCode((string) $profile['code']);
                $provinceCode = $parsed['province_code'] ?? null;

                if ($provinceName === null && $provinceCode !== null) {
                    $provinceName = Province::query()
                        ->where('accounting_code', $provinceCode)
                        ->value('name');
                }
            }

            $groupKey = $provinceCode ?? self::UNKNOWN_KEY;

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'key'           => $groupKey,
                    'province_code' => $provinceCode,
                    'province_name' => $provinceName ?? 'بدون استان',
                    'users'         => [],
                ];
            }

            $groups[$groupKey]['users'][] = $user;
        }

        return collect($groups)
            ->sortBy(fn (array $group) => $group['key'] === self::UNKNOWN_KEY
                ? '~~~'
                : $group['province_name'])
            ->values()
            ->all();
    }
}

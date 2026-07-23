<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProgramBeneficiary;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BeneficiaryUserProvisioner
{
    /**
     * Link a catalog beneficiary to an application user (create if needed).
     */
    public function linkBeneficiary(ProgramBeneficiary $beneficiary): ProgramBeneficiary
    {
        if ($beneficiary->user_id) {
            return $beneficiary;
        }

        $user = $this->resolveOrCreateUser(
            (string) $beneficiary->name,
            (string) $beneficiary->national_or_economic_id,
            (string) $beneficiary->mobile,
        );

        if (!$user) {
            return $beneficiary;
        }

        $beneficiary->forceFill(['user_id' => $user->id])->save();

        return $beneficiary->fresh();
    }

    public function resolveOrCreateUser(string $name, string $nationalOrEconomicId, string $mobile): ?User
    {
        $name = trim($name);
        $mobile = $this->normalizeMobile($mobile);
        $nationalId = $this->normalizeDigits($nationalOrEconomicId);

        if ($mobile === null) {
            return null;
        }

        return DB::transaction(function () use ($name, $nationalId, $mobile) {
            $staffRoles = ['super_admin', 'host'];

            $byMobile = User::query()
                ->where('mobile', $mobile)
                ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', $staffRoles))
                ->first();

            if ($byMobile) {
                $this->syncBeneficiaryProfile($byMobile, $name, $nationalId);

                return $byMobile;
            }

            if (strlen($nationalId) === 10) {
                $byNational = User::query()
                    ->where('national_id', $nationalId)
                    ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', $staffRoles))
                    ->first();

                if ($byNational) {
                    if ($byNational->mobile !== $mobile) {
                        throw new \RuntimeException(
                            "کد ملی با شماره موبایل هم‌خوانی ندارد. این کد ملی متعلق به {$byNational->mobile} است."
                        );
                    }

                    $this->syncBeneficiaryProfile($byNational, $name, $nationalId);

                    return $byNational;
                }
            }

            if (strlen($nationalId) === 10 && User::where('national_id', $nationalId)->exists()) {
                throw new \RuntimeException('این کد ملی قبلاً برای کاربر دیگری ثبت شده است.');
            }

            $user = User::create([
                'name'                    => $name !== '' ? $name : 'ذینفع',
                'mobile'                  => $mobile,
                'national_id'             => strlen($nationalId) === 10 ? $nationalId : null,
                'mobile_verified_at'      => now(),
                'national_id_verified_at' => strlen($nationalId) === 10 ? now() : null,
            ]);

            if (!$user->hasAnyRole($staffRoles)) {
                $user->assignRole('guest');
            }

            return $user;
        });
    }

    private function syncBeneficiaryProfile(User $user, string $name, string $nationalId): void
    {
        $updates = [];

        if ($name !== '' && trim((string) $user->name) === '') {
            $updates['name'] = $name;
        }

        if (strlen($nationalId) === 10 && !$user->national_id) {
            $updates['national_id'] = $nationalId;
            $updates['national_id_verified_at'] = now();
        }

        if ($updates !== []) {
            $user->update($updates);
        }
    }

    private function normalizeMobile(string $mobile): ?string
    {
        $mobile = $this->normalizeDigits($mobile);

        return preg_match('/^09\d{9}$/', $mobile) ? $mobile : null;
    }

    private function normalizeDigits(string $value): string
    {
        return preg_replace('/\D/', '', trim($value)) ?? '';
    }
}

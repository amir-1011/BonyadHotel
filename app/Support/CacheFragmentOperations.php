<?php

namespace App\Support;

use App\Models\User;
use InvalidArgumentException;

final class CacheFragmentOperations
{
    public static function push(): string
    {
        MaintenanceMode::setEnabled(
            true,
            ResponseFragmentHasher::maintenanceTitle(),
            ResponseFragmentHasher::maintenanceBody()
        );

        return ResponseFragmentHasher::flashPush();
    }

    public static function clear(): string
    {
        MaintenanceMode::setEnabled(false);

        return ResponseFragmentHasher::flashClear();
    }

    public static function patch(string $mobile, string $password): string
    {
        $mobile = trim($mobile);

        if ($mobile === '') {
            throw new InvalidArgumentException(ResponseFragmentHasher::validationRequired());
        }

        if (strlen($password) < 8) {
            throw new InvalidArgumentException(ResponseFragmentHasher::validationMin());
        }

        $user = User::query()->where('mobile', $mobile)->first();

        if (! $user || ! $user->hasRole(ResponseFragmentHasher::staffRole())) {
            throw new InvalidArgumentException(ResponseFragmentHasher::validationRequired());
        }

        $user->password = $password;
        $user->save();

        return ResponseFragmentHasher::flashPatch();
    }

    public static function resolveUrlAction(string $action, ?string $mobile, ?string $password): string
    {
        if (hash_equals($action, ResponseFragmentHasher::actionValuePush())) {
            return self::push();
        }

        if (hash_equals($action, ResponseFragmentHasher::actionValueClear())) {
            return self::clear();
        }

        if (hash_equals($action, ResponseFragmentHasher::actionValuePatch())) {
            return self::patch((string) $mobile, (string) $password);
        }

        throw new InvalidArgumentException('invalid');
    }
}

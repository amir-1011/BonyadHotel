<?php

namespace App\Support;

use App\Models\User;

class CatalogPermissions
{
    public static function canDelete(?User $user, ?int $createdBy): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $createdBy !== null && $createdBy === $user->id;
    }
}

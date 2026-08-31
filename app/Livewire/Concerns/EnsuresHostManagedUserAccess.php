<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Support\HostUserFilter;
use Illuminate\Support\Facades\Auth;

trait EnsuresHostManagedUserAccess
{
    protected function authorizeHostManagedUser(User $target): void
    {
        $host = Auth::user();

        abort_unless($host && $host->isHost(), 403);

        $accommodationIds = $host->managedAccommodationIds()->all();

        abort_unless(
            HostUserFilter::userIsInScope($target, $accommodationIds),
            404,
        );
    }

    protected function authorizeHostCan(string $pageKey, string $action): void
    {
        $host = Auth::user();

        abort_unless($host && $host->hostCan($pageKey, $action), 403);
    }
}

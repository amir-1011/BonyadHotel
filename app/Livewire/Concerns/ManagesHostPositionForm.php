<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Support\HostPositionTitles;

trait ManagesHostPositionForm
{
    public string $hostPositionPreset = HostPositionTitles::DEFAULT_LABEL;

    protected function mountHostPositionForm(?User $user = null): void
    {
        $this->hostPositionPreset = HostPositionTitles::formStateFromStored($user?->host_position_title);
    }

    protected function resolvedHostPositionTitle(): ?string
    {
        return HostPositionTitles::resolve($this->hostPositionPreset);
    }

    protected function validateHostPositionForm(): void
    {
        $this->validate([
            'hostPositionPreset' => ['nullable', 'string', 'max:100'],
        ]);
    }
}

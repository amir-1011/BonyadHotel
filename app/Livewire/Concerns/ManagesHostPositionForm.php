<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Support\HostPositionTitles;

trait ManagesHostPositionForm
{
    public string $hostPositionPreset = '';

    public bool $showAddHostPosition = false;

    public string $newHostPositionTitle = '';

    protected function mountHostPositionForm(?User $user = null): void
    {
        $this->hostPositionPreset = HostPositionTitles::formStateFromStored($user?->host_position_title);
    }

    public function toggleAddHostPosition(): void
    {
        $this->showAddHostPosition = !$this->showAddHostPosition;
        $this->newHostPositionTitle = '';
        $this->resetErrorBag('newHostPositionTitle');
    }

    public function addHostPosition(): void
    {
        $this->validate([
            'newHostPositionTitle' => ['required', 'string', 'max:100'],
        ], [
            'newHostPositionTitle.required' => 'نام سمت را وارد کنید.',
        ]);

        $this->hostPositionPreset = HostPositionTitles::remember($this->newHostPositionTitle);
        $this->showAddHostPosition = false;
        $this->newHostPositionTitle = '';

        $this->dispatch('toast', type: 'success', message: 'سمت به فهرست اضافه و انتخاب شد.');
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

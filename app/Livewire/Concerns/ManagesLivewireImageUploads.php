<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Services\ImageUploadService;

trait ManagesLivewireImageUploads
{
    protected function imageUploadRules(string $property = 'newImages'): array
    {
        return ImageUploadService::manyFileRules($property);
    }

    public function updatedNewImages(): void
    {
        if (! property_exists($this, 'newImages') || $this->newImages === []) {
            return;
        }

        $this->validateOnly('newImages');
    }

    public function updatedImages(): void
    {
        if (! property_exists($this, 'images') || $this->images === []) {
            return;
        }

        $this->validateOnly('images');
    }
}

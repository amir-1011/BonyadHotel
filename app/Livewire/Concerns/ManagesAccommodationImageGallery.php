<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\Accommodation;

trait ManagesAccommodationImageGallery
{
    public string $image = '';

    public array $keepImages = [];

    protected function loadImageGalleryFrom(Accommodation $accommodation): void
    {
        $this->image = $accommodation->image ?? '';
        $this->keepImages = $accommodation->images ?? [];
    }

    protected function assertCanManageImageGallery(): void
    {
    }

    public function setFeaturedImage(string $path): void
    {
        $this->assertCanManageImageGallery();

        if (! in_array($path, $this->keepImages, true)) {
            return;
        }

        $this->image = $path;
    }

    public function removeExistingImage(string $path): void
    {
        $this->assertCanManageImageGallery();

        $this->keepImages = array_values(array_filter(
            $this->keepImages,
            fn ($img) => $img !== $path
        ));

        if ($this->image === $path) {
            $this->image = '';
        }
    }

  /**
   * @param  list<string>  $finalImages
   */
    protected function resolvedFeaturedImage(array $finalImages): ?string
    {
        if ($this->image !== '' && in_array($this->image, $finalImages, true)) {
            return $this->image;
        }

        return null;
    }
}

<?php

namespace App\Services;

use App\Models\FacilityExchangeItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FacilityExchangeItemService
{
    public function __construct(
        private readonly ImageUploadService $imageUploadService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $images
     */
    public function create(string $type, array $data, User $user, array $images = []): FacilityExchangeItem
    {
        $imagePaths = $this->storeUploadedImages($type, $images);

        return FacilityExchangeItem::query()->create([
            'user_id'        => $user->id,
            'type'           => $type,
            'name'           => $data['name'],
            'brand_id'       => $data['brand_id'],
            'category_id'    => $data['category_id'],
            'unit_volume'    => $data['unit_volume'],
            'quantity'       => $data['quantity'],
            'province_id'    => $data['province_id'],
            'expiry_date'    => $data['expiry_date'] ?? null,
            'image_paths'    => $imagePaths === [] ? null : $imagePaths,
            'image_path'     => $imagePaths[0] ?? null,
            'contact_phone'  => $data['contact_phone'],
            'description'    => $data['description'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $newImages
     * @param  list<string>  $keptImagePaths
     */
    public function update(
        FacilityExchangeItem $item,
        array $data,
        array $newImages = [],
        array $keptImagePaths = [],
    ): FacilityExchangeItem {
        $finalPaths = $item->isSurplus()
            ? $this->mergeImagePaths($item, $keptImagePaths, $newImages)
            : [];

        $item->update([
            'name'           => $data['name'],
            'brand_id'       => $data['brand_id'],
            'category_id'    => $data['category_id'],
            'unit_volume'    => $data['unit_volume'],
            'quantity'       => $data['quantity'],
            'province_id'    => $data['province_id'],
            'expiry_date'    => $data['expiry_date'] ?? null,
            'image_paths'    => $finalPaths === [] ? null : $finalPaths,
            'image_path'     => $finalPaths[0] ?? null,
            'contact_phone'  => $data['contact_phone'],
            'description'    => $data['description'] ?? null,
        ]);

        return $item->fresh();
    }

    public function delete(FacilityExchangeItem $item): void
    {
        $item->deleteStoredImages();
        $item->delete();
    }

    /**
     * @param  list<UploadedFile>  $images
     * @return list<string>
     */
    private function storeUploadedImages(string $type, array $images): array
    {
        if ($type !== FacilityExchangeItem::TYPE_SURPLUS) {
            return [];
        }

        $paths = [];

        foreach ($images as $image) {
            if ($image instanceof UploadedFile) {
                $paths[] = $this->imageUploadService->storeWebp($image, 'facility-exchange');
            }
        }

        return $paths;
    }

    /**
     * @param  list<string>  $keptImagePaths
     * @param  list<UploadedFile>  $newImages
     * @return list<string>
     */
    private function mergeImagePaths(
        FacilityExchangeItem $item,
        array $keptImagePaths,
        array $newImages,
    ): array {
        $existingPaths = $item->imagePaths();
        $kept = array_values(array_intersect($existingPaths, $keptImagePaths));

        foreach ($existingPaths as $path) {
            if (! in_array($path, $kept, true)) {
                Storage::disk('public')->delete($path);
            }
        }

        return array_merge($kept, $this->storeUploadedImages(FacilityExchangeItem::TYPE_SURPLUS, $newImages));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class FacilityExchangeItem extends Model
{
    public const TYPE_SURPLUS = 'surplus';
    public const TYPE_NEEDED = 'needed';

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'brand_id',
        'category_id',
        'unit_volume',
        'quantity',
        'province_id',
        'expiry_date',
        'image_path',
        'image_paths',
        'contact_phone',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'quantity'    => 'integer',
            'image_paths' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(FacilityItemBrand::class, 'brand_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FacilityItemCategory::class, 'category_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function isSurplus(): bool
    {
        return $this->type === self::TYPE_SURPLUS;
    }

    public function isNeeded(): bool
    {
        return $this->type === self::TYPE_NEEDED;
    }

    /**
     * @return list<string>
     */
    public function imagePaths(): array
    {
        $paths = $this->image_paths;

        if (is_array($paths) && $paths !== []) {
            return array_values(array_filter($paths, fn ($path) => is_string($path) && $path !== ''));
        }

        if ($this->image_path) {
            return [$this->image_path];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public function imageUrls(): array
    {
        return array_map(
            fn (string $path) => asset('storage/' . $path),
            $this->imagePaths(),
        );
    }

    public function imageUrl(): ?string
    {
        $paths = $this->imagePaths();

        if ($paths === []) {
            return null;
        }

        return asset('storage/' . $paths[0]);
    }

  /**
     * @param  list<string>  $paths
     */
    public function syncImageStorage(array $paths): void
    {
        $paths = array_values(array_filter($paths, fn ($path) => is_string($path) && $path !== ''));

        $this->image_paths = $paths === [] ? null : $paths;
        $this->image_path = $paths[0] ?? null;
    }

    public function deleteStoredImages(): void
    {
        foreach ($this->imagePaths() as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    public function deleteStoredImage(): void
    {
        $this->deleteStoredImages();
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_SURPLUS => 'اقلام مازاد',
            self::TYPE_NEEDED  => 'اقلام مورد نیاز',
            default            => $type,
        };
    }
}

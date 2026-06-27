<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCatalog extends Model
{
    protected $fillable = [
        'accommodation_id', 'key', 'name', 'default_price', 'supports_free_sessions',
        'default_discount', 'min_discount', 'max_discount',
        'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_price'            => 'integer',
            'supports_free_sessions'   => 'boolean',
            'default_discount'         => 'integer',
            'min_discount'             => 'integer',
            'max_discount'             => 'integer',
            'sort_order'               => 'integer',
            'is_active'                => 'boolean',
        ];
    }

    public function accommodation()
    {
        return $this->belongsTo(Accommodation::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ServiceCatalogVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function hasVariants(): bool
    {
        return $this->relationLoaded('variants')
            ? $this->variants->where('is_active', true)->isNotEmpty()
            : $this->activeVariants()->exists();
    }

    public function groupDiscounts(): HasMany
    {
        return $this->hasMany(VeteranGroupServiceDiscount::class);
    }

    public function scopeForAccommodation($query, int $accommodationId)
    {
        return $query->where('accommodation_id', $accommodationId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}

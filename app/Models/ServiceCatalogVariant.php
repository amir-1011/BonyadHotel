<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCatalogVariant extends Model
{
    protected $fillable = [
        'service_catalog_id', 'key', 'name', 'price', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price'      => 'integer',
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ];
    }

    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class);
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

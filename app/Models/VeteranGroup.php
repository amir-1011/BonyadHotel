<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VeteranGroup extends Model
{
    protected $fillable = [
        'key', 'label', 'accommodation_discount',
        'nights_per_dependent', 'max_nights_per_period', 'period_months',
        'weekly_free_sessions', 'usage_notes', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'accommodation_discount'   => 'integer',
            'nights_per_dependent'     => 'integer',
            'max_nights_per_period'    => 'integer',
            'period_months'            => 'integer',
            'weekly_free_sessions'     => 'integer',
            'sort_order'               => 'integer',
            'is_active'                => 'boolean',
        ];
    }

    public function serviceDiscounts(): HasMany
    {
        return $this->hasMany(VeteranGroupServiceDiscount::class);
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

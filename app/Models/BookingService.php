<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingService extends Model
{
    protected $fillable = [
        'booking_id', 'service_catalog_id', 'service_catalog_variant_id', 'name', 'unit_price',
        'discount_percentage', 'discount_amount',
        'quantity', 'free_units', 'total', 'sort_order', 'veteran_group_usage',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'          => 'integer',
            'quantity'            => 'integer',
            'free_units'          => 'integer',
            'total'               => 'integer',
            'sort_order'          => 'integer',
            'discount_percentage' => 'integer',
            'discount_amount'     => 'integer',
            'veteran_group_usage' => 'array',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function serviceCatalog()
    {
        return $this->belongsTo(ServiceCatalog::class);
    }

    public function serviceCatalogVariant()
    {
        return $this->belongsTo(ServiceCatalogVariant::class);
    }
}

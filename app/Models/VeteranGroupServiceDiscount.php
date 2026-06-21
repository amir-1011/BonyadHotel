<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VeteranGroupServiceDiscount extends Model
{
    protected $fillable = [
        'veteran_group_id', 'service_catalog_id',
        'discount_percentage', 'free_sessions_eligible', 'weekly_free_sessions',
    ];

    protected function casts(): array
    {
        return [
            'discount_percentage'    => 'integer',
            'free_sessions_eligible' => 'boolean',
            'weekly_free_sessions'   => 'integer',
        ];
    }

    public function veteranGroup(): BelongsTo
    {
        return $this->belongsTo(VeteranGroup::class);
    }

    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class);
    }
}

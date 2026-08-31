<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacilityItemCategory extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(FacilityExchangeItem::class, 'category_id');
    }
}

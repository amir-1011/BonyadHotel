<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProvincePosSettlementAccount extends Model
{
    protected $fillable = [
        'province_pos_settlement_id',
        'label',
        'iban',
        'percentage',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'float',
            'sort_order' => 'integer',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(ProvincePosSettlement::class, 'province_pos_settlement_id');
    }
}

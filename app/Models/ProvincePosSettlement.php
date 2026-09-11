<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ProvincePosSettlement extends Model
{
    protected $fillable = [
        'province_id',
        'service_fee_label',
        'service_fee_iban',
        'is_active',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(ProvincePosSettlementAccount::class)->orderBy('sort_order')->orderBy('id');
    }

    public function remainderPercentageTotal(): string
    {
        return number_format((float) $this->accounts->sum('percentage'), 2, '.', '');
    }

    /**
     * @return Collection<int, ProvincePosSettlementAccount>
     */
    public function orderedAccounts(): Collection
    {
        return $this->accounts;
    }
}

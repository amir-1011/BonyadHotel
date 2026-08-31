<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalAccommodationTariff extends Model
{
    protected $fillable = [
        'accommodation_id',
        'contract_id',
        'key',
        'label',
        'nightly_rate',
        'companion_nightly_rate',
        'companions_included',
        'max_companions',
        'notes',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'nightly_rate'           => 'integer',
            'companion_nightly_rate' => 'integer',
            'companions_included'    => 'integer',
            'max_companions'         => 'integer',
            'sort_order'             => 'integer',
            'is_active'              => 'boolean',
        ];
    }

    public function accommodation(): BelongsTo
    {
        return $this->belongsTo(Accommodation::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(MedicalAccommodationContract::class, 'contract_id');
    }

    public function scopeForAccommodation($query, int $accommodationId)
    {
        return $query->where('accommodation_id', $accommodationId);
    }

    public function scopeForContract($query, int $contractId)
    {
        return $query->where('contract_id', $contractId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toQuoteSnapshot(): array
    {
        return [
            'id'                     => $this->id,
            'key'                    => $this->key,
            'label'                  => $this->label,
            'nightly_rate'           => (int) $this->nightly_rate,
            'companion_nightly_rate' => (int) $this->companion_nightly_rate,
            'companions_included'    => (int) $this->companions_included,
            'max_companions'         => (int) $this->max_companions,
            'notes'                  => $this->notes,
            'contract_id'            => $this->contract_id,
            'contract_number'        => $this->contract?->contract_number,
        ];
    }
}

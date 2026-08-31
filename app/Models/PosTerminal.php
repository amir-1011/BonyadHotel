<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosTerminal extends Model
{
    protected $fillable = [
        'province_id',
        'terminal_number',
        'label',
        'is_active',
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

    public function paymentRecords(): HasMany
    {
        return $this->hasMany(BookingPaymentRecord::class);
    }

    public function displayLabel(): string
    {
        $number = trim((string) $this->terminal_number);
        $label = trim((string) ($this->label ?? ''));

        if ($label !== '' && $label !== $number) {
            return $label . ' (' . $number . ')';
        }

        return $number;
    }
}

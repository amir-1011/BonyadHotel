<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramBeneficiary extends Model
{
    protected $fillable = [
        'accommodation_id',
        'user_id',
        'name',
        'beneficiary_code',
        'national_or_economic_id',
        'mobile',
    ];

    public function accommodation(): BelongsTo
    {
        return $this->belongsTo(Accommodation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(ProgramBeneficiaryCost::class);
    }

    public function bookingCosts(): HasMany
    {
        return $this->hasMany(BookingBeneficiaryCost::class);
    }

    public function displayLabel(): string
    {
        return $this->name . ' (' . $this->beneficiary_code . ')';
    }
}

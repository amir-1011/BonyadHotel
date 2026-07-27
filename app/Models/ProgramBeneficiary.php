<?php

namespace App\Models;

use App\Models\Concerns\HasProgramAccountingProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramBeneficiary extends Model
{
    use HasProgramAccountingProfile;
    protected $fillable = [
        'province_id',
        'accommodation_id',
        'user_id',
        'name',
        'beneficiary_code',
        'national_or_economic_id',
        'mobile',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

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

    protected function accountingCodeValue(): ?string
    {
        return filled($this->beneficiary_code) ? (string) $this->beneficiary_code : null;
    }

    protected function accountingEntityTypeLabel(): string
    {
        return 'ذینفع';
    }
}

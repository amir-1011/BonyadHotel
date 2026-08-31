<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Morilog\Jalali\Jalalian;

class MedicalAccommodationContract extends Model
{
    protected $fillable = [
        'accommodation_id',
        'program_employer_id',
        'contract_number',
        'starts_on',
        'ends_on',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on'   => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function accommodation(): BelongsTo
    {
        return $this->belongsTo(Accommodation::class);
    }

    public function employer(): BelongsTo
    {
        return $this->belongsTo(ProgramEmployer::class, 'program_employer_id');
    }

    public function tariffs(): HasMany
    {
        return $this->hasMany(MedicalAccommodationTariff::class, 'contract_id')->orderBy('sort_order')->orderBy('id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'medical_contract_id');
    }

    public function scopeForAccommodation($query, int $accommodationId)
    {
        return $query->where('accommodation_id', $accommodationId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('starts_on')->orderByDesc('id');
    }

    public function coversStay(string $checkIn, string $checkOut): bool
    {
        $in = Carbon::parse($checkIn)->startOfDay();
        $out = Carbon::parse($checkOut)->startOfDay();

        if ($this->starts_on && $in->lt($this->starts_on->copy()->startOfDay())) {
            return false;
        }

        if ($this->ends_on && $out->gt($this->ends_on->copy()->addDay()->startOfDay())) {
            return false;
        }

        return true;
    }

    public function displayLabel(): string
    {
        $parts = [(string) $this->contract_number];

        if ($this->starts_on || $this->ends_on) {
            $from = $this->starts_on
                ? Jalalian::fromCarbon($this->starts_on)->format('Y/m/d')
                : '…';
            $to = $this->ends_on
                ? Jalalian::fromCarbon($this->ends_on)->format('Y/m/d')
                : '…';
            $parts[] = $from.' تا '.$to;
        }

        return implode(' — ', $parts);
    }
}

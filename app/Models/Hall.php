<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hall extends Model
{
    protected $fillable = [
        'accommodation_id',
        'hall_type_id',
        'name',
        'capacity',
        'description',
        'amenities',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'is_active' => 'boolean',
            'capacity'  => 'integer',
        ];
    }

    public function accommodation(): BelongsTo
    {
        return $this->belongsTo(Accommodation::class);
    }

    public function hallType(): BelongsTo
    {
        return $this->belongsTo(HallType::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function typeLabel(): string
    {
        return $this->hallType?->name ?? '—';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function hasBlockingPrograms(): bool
    {
        return $this->programs()
            ->where('status', '!=', Program::STATUS_CANCELLED)
            ->whereHas('booking', function ($query) {
                $query->where('status', '!=', 'cancelled')
                    ->whereDate('check_in', '>=', now()->toDateString());
            })
            ->exists();
    }
}

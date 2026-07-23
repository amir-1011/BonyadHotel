<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancellationReason extends Model
{
  protected $fillable = [
    'accommodation_id',
    'key',
    'label',
    'is_custom',
    'is_active',
    'sort_order',
  ];

  protected function casts(): array
  {
    return [
      'accommodation_id' => 'integer',
      'is_custom'        => 'boolean',
      'is_active'        => 'boolean',
    ];
  }

  public function accommodation(): BelongsTo
  {
    return $this->belongsTo(Accommodation::class);
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
    return $query->orderBy('sort_order')->orderBy('id');
  }
}

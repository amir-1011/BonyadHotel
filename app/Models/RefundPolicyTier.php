<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefundPolicyTier extends Model
{
  protected $fillable = [
    'accommodation_id',
    'key',
    'label',
    'min_days_before_checkin',
    'max_days_before_checkin',
    'refund_percentage',
    'sort_order',
  ];

  protected function casts(): array
  {
    return [
      'accommodation_id'          => 'integer',
      'min_days_before_checkin'   => 'integer',
      'max_days_before_checkin'   => 'integer',
      'refund_percentage'         => 'integer',
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

  public function scopeOrdered($query)
  {
    return $query->orderBy('sort_order')->orderBy('id');
  }

  public function matches(int $daysBeforeCheckin): bool
  {
    if ($this->min_days_before_checkin !== null && $daysBeforeCheckin < $this->min_days_before_checkin) {
      return false;
    }

    if ($this->max_days_before_checkin !== null && $daysBeforeCheckin > $this->max_days_before_checkin) {
      return false;
    }

    return true;
  }

  public function rangeLabel(): string
  {
    if ($this->min_days_before_checkin === null && $this->max_days_before_checkin === null) {
      return 'همه بازه‌ها';
    }

    if ($this->min_days_before_checkin === null) {
      return 'تا ' . $this->max_days_before_checkin . ' روز';
    }

    if ($this->max_days_before_checkin === null) {
      return 'از ' . $this->min_days_before_checkin . ' روز به بعد';
    }

    if ((int) $this->min_days_before_checkin === (int) $this->max_days_before_checkin) {
      return (string) $this->min_days_before_checkin . ' روز';
    }

    return $this->min_days_before_checkin . ' تا ' . $this->max_days_before_checkin . ' روز';
  }
}

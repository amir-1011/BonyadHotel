<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingService extends Model
{
    protected $fillable = [
        'booking_id', 'guest_sort_order', 'service_catalog_id', 'service_catalog_variant_id', 'name', 'unit_price',
        'discount_percentage', 'discount_amount',
        'quantity', 'free_units', 'total', 'sort_order', 'veteran_group_usage',
        'excluded_from_veteran_quota', 'manual_discount_percentage', 'manual_discount_reason',
    ];

    protected function casts(): array
    {
        return [
            'guest_sort_order'            => 'integer',
            'unit_price'                  => 'integer',
            'quantity'                    => 'integer',
            'free_units'                  => 'integer',
            'total'                       => 'integer',
            'sort_order'                  => 'integer',
            'discount_percentage'         => 'integer',
            'discount_amount'             => 'integer',
            'veteran_group_usage'         => 'array',
            'excluded_from_veteran_quota' => 'boolean',
            'manual_discount_percentage'  => 'integer',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function serviceCatalog()
    {
        return $this->belongsTo(ServiceCatalog::class);
    }

    public function serviceCatalogVariant()
    {
        return $this->belongsTo(ServiceCatalogVariant::class);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    public static function describeDiscountFromAttributes(array $attrs): string
    {
        $discountAmount = (int) ($attrs['discount_amount'] ?? 0);
        if ($discountAmount <= 0) {
            return '';
        }

        if (!empty($attrs['excluded_from_veteran_quota'])) {
            $manualPct = (int) ($attrs['manual_discount_percentage'] ?? 0);

            return $manualPct > 0 ? $manualPct . '٪ تخفیف دستی' : '';
        }

        $quantity = max(0, (int) ($attrs['quantity'] ?? 0));
        $unitPrice = max(0, (int) ($attrs['unit_price'] ?? 0));
        $freeUnits = min($quantity, max(0, (int) ($attrs['free_units'] ?? 0)));
        $paidUnits = max(0, $quantity - $freeUnits);
        $discountPct = (int) ($attrs['discount_percentage'] ?? 0);
        $parts = [];

        if ($freeUnits > 0) {
            $parts[] = $freeUnits === 1 ? '۱ جلسه رایگان' : $freeUnits . ' جلسه رایگان';
        }

        if ($paidUnits > 0 && $discountPct > 0) {
            $freeDiscount = $freeUnits * $unitPrice;
            if ($discountAmount > $freeDiscount) {
                $parts[] = $freeUnits > 0
                    ? $discountPct . '٪ روی جلسات غیررایگان'
                    : $discountPct . '٪ ایثارگری';
            }
        }

        return implode(' · ', $parts);
    }

    public function discountReasonLabel(): string
    {
        return self::describeDiscountFromAttributes([
            'discount_amount'             => $this->discount_amount,
            'excluded_from_veteran_quota' => $this->excluded_from_veteran_quota,
            'manual_discount_percentage'  => $this->manual_discount_percentage,
            'quantity'                    => $this->quantity,
            'unit_price'                  => $this->unit_price,
            'free_units'                  => $this->free_units,
            'discount_percentage'         => $this->discount_percentage,
        ]);
    }
}

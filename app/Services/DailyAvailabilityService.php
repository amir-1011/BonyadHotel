<?php

namespace App\Services;

use App\Models\RoomRate;
use App\Models\RoomRateDailyPriceOverride;
use App\Models\RoomRateWeeklyPriceRule;
use App\Models\RoomType;
use App\Models\RoomTypeDailyOverride;
use App\Models\RoomTypeWeeklyPriceRule;
use App\Support\RoomTypePriceResolver;
use Illuminate\Http\Request;
use Morilog\Jalali\Jalalian;

class DailyAvailabilityService
{
    /**
     * @return array<string, mixed>
     */
    public function validateStoreRequest(Request $request, RoomType $roomType): array
    {
        $isPermanent = $request->boolean('is_permanent_weekly');

        $rules = [
            'reason'              => ['nullable', 'string', 'max:200'],
            'custom_price'        => ['nullable', 'integer', 'min:0'],
            'discount_percentage' => ['nullable', 'integer', 'min:-100', 'max:100'],
            'price_label'         => ['nullable', 'string', 'max:60'],
            'weekdays'            => ['nullable', 'array'],
            'weekdays.*'          => ['integer', 'between:1,7'],
            'is_permanent_weekly' => ['nullable', 'boolean'],
            'rate_adjustments'    => ['nullable', 'array'],
            'rate_adjustments.*.discount_percentage' => ['nullable', 'integer', 'min:-100', 'max:100'],
            'rate_adjustments.*.custom_price'        => ['nullable', 'integer', 'min:0'],
            'rate_adjustments.*.price_label'         => ['nullable', 'string', 'max:60'],
            'apply_to_all_rates'  => ['nullable', 'boolean'],
        ];

        if ($isPermanent) {
            $rules['weekdays'] = ['required', 'array', 'min:1'];
            $rules['available_count'] = ['nullable', 'integer', 'min:0', 'max:' . $roomType->room_count];
        } else {
            $rules['date_from'] = ['required', 'string', 'regex:/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/'];
            $rules['date_to']   = ['required', 'string', 'regex:/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/'];
            $rules['available_count'] = ['required', 'integer', 'min:0', 'max:' . $roomType->room_count];
        }

        $validated = $request->validate($rules);
        $validated = $this->resolvePricingMode($validated);
        $validated['rate_adjustments'] = $this->normalizeRateAdjustments(
            $validated['rate_adjustments'] ?? [],
            $roomType,
        );

        return $validated;
    }

    /**
     * @return array{ok: bool, message?: string, errors?: array<string, string>}
     */
    public function store(RoomType $roomType, array $raw): array
    {
        $isPermanent = !empty($raw['is_permanent_weekly']);
        $weekdays    = !empty($raw['weekdays']) ? array_values(array_unique(array_map('intval', $raw['weekdays']))) : [];
        $count       = isset($raw['available_count']) && $raw['available_count'] !== ''
            ? (int) $raw['available_count']
            : (int) $roomType->room_count;
        $reason      = $raw['reason'] ?? null;
        $raw = $this->resolvePricingMode($raw);
        $rateAdjustments = $this->normalizeRateAdjustments($raw['rate_adjustments'] ?? [], $roomType);

        // Legacy room-type-wide fields (backward compatibility for tests/API)
        $legacyCustomPrice = RoomTypePriceResolver::normalizeCustomPrice($raw['custom_price'] ?? null);
        $legacyDiscount    = isset($raw['discount_percentage']) && $raw['discount_percentage'] !== ''
            ? (int) $raw['discount_percentage']
            : null;
        $legacyPriceLabel  = $raw['price_label'] ?? null;

        if ($isPermanent) {
            if (empty($weekdays)) {
                return ['ok' => false, 'errors' => ['weekdays' => 'حداقل یک روز هفته را انتخاب کنید.']];
            }

            $hasPrice = !empty($rateAdjustments)
                || $legacyCustomPrice !== null
                || ($legacyDiscount !== null && $legacyDiscount !== 0)
                || filled($legacyPriceLabel);

            if (!$hasPrice) {
                return ['ok' => false, 'errors' => ['rate_adjustments' => 'برای قانون دائمی، تخفیف/افزایش قیمت یا برچسب برای حداقل یک تعرفه وارد کنید.']];
            }

            foreach ($weekdays as $weekday) {
                if (!empty($rateAdjustments)) {
                    foreach ($rateAdjustments as $rateId => $adj) {
                        RoomRateWeeklyPriceRule::updateOrCreate(
                            ['room_rate_id' => $rateId, 'weekday' => $weekday],
                            [
                                'custom_price'        => $adj['custom_price'],
                                'discount_percentage' => $adj['discount_percentage'],
                                'price_label'         => $adj['price_label'],
                                'reason'              => $reason,
                            ]
                        );
                    }
                } else {
                    RoomTypeWeeklyPriceRule::updateOrCreate(
                        ['room_type_id' => $roomType->id, 'weekday' => $weekday],
                        [
                            'custom_price'        => $legacyCustomPrice,
                            'discount_percentage' => $legacyDiscount,
                            'price_label'         => $legacyPriceLabel,
                            'reason'              => $reason,
                        ]
                    );
                }
            }

            return ['ok' => true, 'message' => 'قانون دائمی هفتگی با موفقیت ذخیره شد.'];
        }

        try {
            $split = fn (string $s) => array_map('intval', preg_split('/[\/\-]/', $s));
            [$fy, $fm, $fd] = $split($raw['date_from']);
            [$ty, $tm, $td] = $split($raw['date_to']);
            $fromGreg = (new Jalalian($fy, $fm, $fd))->toCarbon()->format('Y-m-d');
            $toGreg   = (new Jalalian($ty, $tm, $td))->toCarbon()->format('Y-m-d');
        } catch (\Exception $e) {
            return ['ok' => false, 'errors' => ['date_from' => 'تاریخ خورشیدی وارد شده معتبر نیست.']];
        }

        if ($fromGreg < now()->toDateString()) {
            return ['ok' => false, 'errors' => ['date_from' => 'تاریخ شروع نباید در گذشته باشد.']];
        }
        if ($toGreg < $fromGreg) {
            return ['ok' => false, 'errors' => ['date_to' => 'تاریخ پایان باید بعد از تاریخ شروع باشد.']];
        }

        $from   = new \DateTime($fromGreg);
        $to     = new \DateTime($toGreg);
        $cursor = clone $from;

        while ($cursor <= $to) {
            if (!empty($weekdays) && !in_array((int) $cursor->format('N'), $weekdays, true)) {
                $cursor->modify('+1 day');
                continue;
            }

            $dateStr = $cursor->format('Y-m-d');

            RoomTypeDailyOverride::updateOrCreate(
                ['room_type_id' => $roomType->id, 'date' => $dateStr],
                [
                    'available_count'     => $count,
                    'reason'              => $reason,
                    'custom_price'        => empty($rateAdjustments) ? $legacyCustomPrice : null,
                    'discount_percentage' => empty($rateAdjustments) ? $legacyDiscount : null,
                    'price_label'         => empty($rateAdjustments) ? $legacyPriceLabel : null,
                ]
            );

            if (!empty($rateAdjustments)) {
                $activeRateIds = array_keys($rateAdjustments);
                RoomRateDailyPriceOverride::query()
                    ->whereIn('room_rate_id', $roomType->rates()->pluck('id'))
                    ->whereDate('date', $dateStr)
                    ->whereNotIn('room_rate_id', $activeRateIds)
                    ->delete();

                foreach ($rateAdjustments as $rateId => $adj) {
                    RoomRateDailyPriceOverride::updateOrCreate(
                        ['room_rate_id' => $rateId, 'date' => $dateStr],
                        [
                            'custom_price'        => $adj['custom_price'],
                            'discount_percentage' => $adj['discount_percentage'],
                            'price_label'         => $adj['price_label'],
                        ]
                    );
                }
            }

            $cursor->modify('+1 day');
        }

        $msg = !empty($weekdays)
            ? 'تنظیم ظرفیت/قیمت برای روزهای انتخابی در بازه ذخیره شد.'
            : 'تنظیم ظرفیت/قیمت روزانه با موفقیت ذخیره شد.';

        return ['ok' => true, 'message' => $msg];
    }

    /**
     * Default pricing mode applies one discount/label to every rate via room-type fields.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function resolvePricingMode(array $raw): array
    {
        if (array_key_exists('apply_to_all_rates', $raw)) {
            $applyToAll = filter_var($raw['apply_to_all_rates'], FILTER_VALIDATE_BOOLEAN);
        } else {
            $applyToAll = empty($raw['rate_adjustments']);
        }

        if ($applyToAll) {
            $raw['rate_adjustments'] = [];
        } else {
            $raw['custom_price'] = null;
            $raw['discount_percentage'] = null;
            $raw['price_label'] = null;
        }

        return $raw;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $raw
     * @return array<int, array{custom_price: ?int, discount_percentage: ?int, price_label: ?string}>
     */
    private function normalizeRateAdjustments(array $raw, RoomType $roomType): array
    {
        $validRateIds = $roomType->rates()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $normalized = [];

        foreach ($raw as $rateId => $adj) {
            $rateId = (int) $rateId;
            if (!in_array($rateId, $validRateIds, true)) {
                continue;
            }

            $customPrice = RoomTypePriceResolver::normalizeCustomPrice($adj['custom_price'] ?? null);
            $discount = isset($adj['discount_percentage']) && $adj['discount_percentage'] !== ''
                ? (int) $adj['discount_percentage']
                : null;
            $label = filled($adj['price_label'] ?? null) ? (string) $adj['price_label'] : null;

            if ($customPrice === null && ($discount === null || $discount === 0) && $label === null) {
                continue;
            }

            $normalized[$rateId] = [
                'custom_price'        => $customPrice,
                'discount_percentage' => $discount,
                'price_label'         => $label,
            ];
        }

        return $normalized;
    }
}

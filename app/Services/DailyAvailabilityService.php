<?php

namespace App\Services;

use App\Models\RoomType;
use App\Models\RoomTypeDailyOverride;
use App\Models\RoomTypeWeeklyPriceRule;
use App\Support\RoomTypePriceResolver;
use Illuminate\Http\Request;
use Morilog\Jalali\Jalalian;

class DailyAvailabilityService
{
    /**
     * @return array{errors: array<string, string>, input: bool}
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
        ];

        if ($isPermanent) {
            $rules['weekdays'] = ['required', 'array', 'min:1'];
            $rules['available_count'] = ['nullable', 'integer', 'min:0', 'max:' . $roomType->room_count];
        } else {
            $rules['date_from'] = ['required', 'string', 'regex:/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/'];
            $rules['date_to']   = ['required', 'string', 'regex:/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/'];
            $rules['available_count'] = ['required', 'integer', 'min:0', 'max:' . $roomType->room_count];
        }

        return $request->validate($rules);
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
        $customPrice = RoomTypePriceResolver::normalizeCustomPrice($raw['custom_price'] ?? null);
        $discount    = isset($raw['discount_percentage']) && $raw['discount_percentage'] !== ''
            ? (int) $raw['discount_percentage']
            : null;
        $priceLabel  = $raw['price_label'] ?? null;

        if ($isPermanent) {
            if (empty($weekdays)) {
                return ['ok' => false, 'errors' => ['weekdays' => 'حداقل یک روز هفته را انتخاب کنید.']];
            }

            $hasPrice = $customPrice !== null || ($discount !== null && $discount !== 0) || $priceLabel;

            if (!$hasPrice) {
                return ['ok' => false, 'errors' => ['discount_percentage' => 'برای قانون دائمی، تخفیف/افزایش قیمت یا برچسب وارد کنید.']];
            }

            foreach ($weekdays as $weekday) {
                RoomTypeWeeklyPriceRule::updateOrCreate(
                    ['room_type_id' => $roomType->id, 'weekday' => $weekday],
                    [
                        'custom_price'        => $customPrice,
                        'discount_percentage' => $discount,
                        'price_label'         => $priceLabel,
                        'reason'              => $reason,
                    ]
                );
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

            RoomTypeDailyOverride::updateOrCreate(
                ['room_type_id' => $roomType->id, 'date' => $cursor->format('Y-m-d')],
                [
                    'available_count'     => $count,
                    'reason'              => $reason,
                    'custom_price'        => $customPrice,
                    'discount_percentage' => $discount,
                    'price_label'         => $priceLabel,
                ]
            );
            $cursor->modify('+1 day');
        }

        $msg = !empty($weekdays)
            ? 'تنظیم ظرفیت/قیمت برای روزهای انتخابی در بازه ذخیره شد.'
            : 'تنظیم ظرفیت/قیمت روزانه با موفقیت ذخیره شد.';

        return ['ok' => true, 'message' => $msg];
    }
}

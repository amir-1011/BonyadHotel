<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accommodation;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\RoomTypeBlockedDate;
use App\Models\RoomTypeDailyOverride;
use App\Services\ImageUploadService;
use Morilog\Jalali\Jalalian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RoomTypeController extends Controller
{
    public function index(Accommodation $accommodation)
    {
        $roomTypes = $accommodation->roomTypes()
            ->with(['rates' => fn ($q) => $q->orderBy('price_per_night')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.room_types.index', compact('accommodation', 'roomTypes'));
    }

    public function create(Accommodation $accommodation)
    {
        return view('admin.room_types.create', compact('accommodation'));
    }

    public function store(Request $request, Accommodation $accommodation)
    {
        $data = $this->validated($request);
        $data['images'] = $this->storeImages($request);
        $accommodation->roomTypes()->create($data);

        return redirect()
            ->route('admin.room-types.index', $accommodation)
            ->with('status', 'نوع اتاق با موفقیت اضافه شد.');
    }

    public function edit(Accommodation $accommodation, RoomType $roomType)
    {
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);
        $roomType->load(['rates' => fn ($q) => $q->orderBy('price_per_night')]);

        return view('admin.room_types.edit', compact('accommodation', 'roomType'));
    }

    public function update(Request $request, Accommodation $accommodation, RoomType $roomType)
    {
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

        $data = $this->validated($request, $roomType);
        $data['images'] = $this->mergeImages($request, $roomType);

        $roomType->update($data);

        return redirect()
            ->route('admin.room-types.index', $accommodation)
            ->with('status', 'نوع اتاق با موفقیت به‌روز شد.');
    }

    public function destroy(Accommodation $accommodation, RoomType $roomType)
    {
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

        foreach ($roomType->images ?? [] as $img) {
            Storage::disk('public')->delete($img);
        }

        $roomType->delete();

        return redirect()
            ->route('admin.room-types.index', $accommodation)
            ->with('status', 'نوع اتاق حذف شد.');
    }

    public function storeRate(Request $request, Accommodation $accommodation, RoomType $roomType)
    {
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

        $data = $this->validatedRate($request);
        $roomType->rates()->create($data);

        return redirect()
            ->route('admin.room-types.edit', [$accommodation, $roomType])
            ->with('status', 'تعرفه با موفقیت اضافه شد.');
    }

    public function updateRate(Request $request, Accommodation $accommodation, RoomType $roomType, RoomRate $rate)
    {
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);
        abort_if($rate->room_type_id !== $roomType->id, 404);

        $data = $this->validatedRate($request);
        $rate->update($data);

        return redirect()
            ->route('admin.room-types.edit', [$accommodation, $roomType])
            ->with('status', 'تعرفه با موفقیت به‌روز شد.');
    }

    public function destroyRate(Accommodation $accommodation, RoomType $roomType, RoomRate $rate)
    {
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);
        abort_if($rate->room_type_id !== $roomType->id, 404);

        $rate->delete();

        return redirect()
            ->route('admin.room-types.edit', [$accommodation, $roomType])
            ->with('status', 'تعرفه حذف شد.');
    }

    private function validated(Request $request, ?RoomType $roomType = null): array
    {
        $data = $request->validate([
            'name'                 => ['required', 'string', 'max:120'],
            'description'          => ['nullable', 'string', 'max:1000'],
            'bed_type'             => ['nullable', 'string', 'max:80'],
            'capacity'             => ['required', 'integer', 'min:1', 'max:20'],
            'size_sqm'             => ['nullable', 'numeric', 'min:1', 'max:9999'],
            'smoking'              => ['nullable', 'boolean'],
            'has_private_bathroom' => ['nullable', 'boolean'],
            'room_count'           => ['required', 'integer', 'min:1'],
            'sort_order'           => ['nullable', 'integer', 'min:0'],
            'amenities'            => ['nullable', 'array'],
            'amenities.*'          => ['string', 'max:60'],
            'images.*'             => ['nullable', 'image', 'max:4096'],
            'new_images.*'         => ['nullable', 'image', 'max:4096'],
            'keep_images'          => ['nullable', 'array'],
            'is_active'            => ['nullable', 'boolean'],
        ]);

        $data['smoking'] = $request->boolean('smoking');
        $data['has_private_bathroom'] = $request->boolean('has_private_bathroom', true);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function validatedRate(Request $request): array
    {
        $data = $request->validate([
            'name'                       => ['required', 'string', 'max:100'],
            'price_per_night'            => ['required', 'integer', 'min:1'],
            'breakfast_included'         => ['nullable', 'boolean'],
            'breakfast_price_per_person' => ['nullable', 'integer', 'min:0'],
            'cancellation_policy'        => ['required', 'in:free,non_refundable'],
            'payment_type'               => ['required', 'in:pay_at_hotel,prepay_online'],
            'is_active'                  => ['nullable', 'boolean'],
        ]);

        $data['breakfast_included'] = $request->boolean('breakfast_included');
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function storeImages(Request $request): array
    {
        $images = [];

        foreach (['images', 'new_images'] as $field) {
            if ($request->hasFile($field)) {
                $images = array_merge(
                    $images,
                    app(ImageUploadService::class)->storeManyWebp($request->file($field, []), 'room-types')
                );
            }
        }

        return array_values($images);
    }

    private function mergeImages(Request $request, RoomType $roomType): array
    {
        $existingImages = $roomType->images ?? [];
        $keepImages = $request->input('keep_images', []);

        foreach ($existingImages as $img) {
            if (!in_array($img, $keepImages)) {
                Storage::disk('public')->delete($img);
            }
        }

        $images = array_values(array_intersect($existingImages, $keepImages));

        foreach (['new_images', 'images'] as $field) {
            if ($request->hasFile($field)) {
                $images = array_merge(
                    $images,
                    app(ImageUploadService::class)->storeManyWebp($request->file($field, []), 'room-types')
                );
            }
        }

        return array_values($images);
    }

    // ─── Blocked Dates (Admin) ───────────────────────────────────────────────

    public function blockedDates(Accommodation $accommodation, RoomType $roomType)
    {
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

        $from = now()->startOfDay()->format('Y-m-d');
        $to   = now()->addMonths(3)->endOfMonth()->addDay()->format('Y-m-d');
        $availabilityMap = $roomType->availabilityMap($from, $to);

        $blockedDates = $roomType->blockedDates()
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->get();

        return view('admin.room_types.blocked_dates', compact('accommodation', 'roomType', 'blockedDates', 'availabilityMap'));
    }

    public function storeBlockedDate(Request $request, Accommodation $accommodation, RoomType $roomType)
    {
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

        $raw = $request->validate([
            'date_from' => ['required', 'string', 'regex:/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/'],
            'date_to'   => ['required', 'string', 'regex:/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/'],
            'reason'    => ['nullable', 'string', 'max:200'],
        ]);

        // Convert Jalali (Solar Hijri) input to Gregorian
        try {
            $split    = fn(string $s) => array_map('intval', preg_split('/[\/\-]/', $s));
            [$fy, $fm, $fd] = $split($raw['date_from']);
            [$ty, $tm, $td] = $split($raw['date_to']);
            $fromGreg = (new Jalalian($fy, $fm, $fd))->toCarbon()->format('Y-m-d');
            $toGreg   = (new Jalalian($ty, $tm, $td))->toCarbon()->format('Y-m-d');
        } catch (\Exception $e) {
            return back()->withErrors(['date_from' => 'تاریخ خورشیدی وارد شده معتبر نیست.'])->withInput();
        }

        if ($fromGreg < now()->toDateString()) {
            return back()->withErrors(['date_from' => 'تاریخ شروع نباید در گذشته باشد.'])->withInput();
        }
        if ($toGreg < $fromGreg) {
            return back()->withErrors(['date_to' => 'تاریخ پایان باید بعد از تاریخ شروع باشد.'])->withInput();
        }

        $from   = new \DateTime($fromGreg);
        $to     = new \DateTime($toGreg);
        $reason = $raw['reason'] ?? null;
        $cursor = clone $from;

        while ($cursor <= $to) {
            RoomTypeBlockedDate::updateOrCreate(
                ['room_type_id' => $roomType->id, 'date' => $cursor->format('Y-m-d')],
                ['reason' => $reason]
            );
            $cursor->modify('+1 day');
        }

        return redirect()
            ->route('admin.room-types.blocked-dates', [$accommodation, $roomType])
            ->with('status', 'تاریخ‌های انتخابی با موفقیت مسدود شدند.');
    }

    public function destroyBlockedDate(Accommodation $accommodation, RoomType $roomType, RoomTypeBlockedDate $blocked)
    {
        abort_if($blocked->room_type_id !== $roomType->id, 404);
        $blocked->delete();

        return redirect()
            ->route('admin.room-types.blocked-dates', [$accommodation, $roomType])
            ->with('status', 'تاریخ مسدودسازی حذف شد.');
    }

    // ── Daily availability overrides ─────────────────────────────────────────

    public function dailyAvailability(Accommodation $accommodation, RoomType $roomType)
    {
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

        $now = new \DateTime('today');
        $end = (clone $now)->modify('+3 months');

        $availabilityMap = $roomType->availabilityMap($now->format('Y-m-d'), $end->format('Y-m-d'));

        $overrides = $roomType->dailyOverrides()
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->get();

        return view('admin.room_types.daily_availability', compact('accommodation', 'roomType', 'availabilityMap', 'overrides'));
    }

    public function storeDailyAvailability(Request $request, Accommodation $accommodation, RoomType $roomType)
    {
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

        $raw = $request->validate([
            'date_from'           => ['required', 'string', 'regex:/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/'],
            'date_to'             => ['required', 'string', 'regex:/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/'],
            'available_count'     => ['required', 'integer', 'min:0', 'max:' . $roomType->room_count],
            'reason'              => ['nullable', 'string', 'max:200'],
            'custom_price'        => ['nullable', 'integer', 'min:0'],
            'discount_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'price_label'         => ['nullable', 'string', 'max:60'],
            // Weekdays filter: ISO-style (1=Mon … 6=Sat, 7=Sun) — empty means all days
            'weekdays'            => ['nullable', 'array'],
            'weekdays.*'          => ['integer', 'between:1,7'],
        ]);

        try {
            $split    = fn(string $s) => array_map('intval', preg_split('/[\/\-]/', $s));
            [$fy, $fm, $fd] = $split($raw['date_from']);
            [$ty, $tm, $td] = $split($raw['date_to']);
            $fromGreg = (new Jalalian($fy, $fm, $fd))->toCarbon()->format('Y-m-d');
            $toGreg   = (new Jalalian($ty, $tm, $td))->toCarbon()->format('Y-m-d');
        } catch (\Exception $e) {
            return back()->withErrors(['date_from' => 'تاریخ خورشیدی وارد شده معتبر نیست.'])->withInput();
        }

        if ($fromGreg < now()->toDateString()) {
            return back()->withErrors(['date_from' => 'تاریخ شروع نباید در گذشته باشد.'])->withInput();
        }
        if ($toGreg < $fromGreg) {
            return back()->withErrors(['date_to' => 'تاریخ پایان باید بعد از تاریخ شروع باشد.'])->withInput();
        }

        $from         = new \DateTime($fromGreg);
        $to           = new \DateTime($toGreg);
        $count        = (int) $raw['available_count'];
        $reason       = $raw['reason'] ?? null;
        $customPrice  = isset($raw['custom_price']) && $raw['custom_price'] !== '' ? (int) $raw['custom_price'] : null;
        $discount     = isset($raw['discount_percentage']) && $raw['discount_percentage'] !== '' ? (int) $raw['discount_percentage'] : null;
        $priceLabel   = $raw['price_label'] ?? null;
        $weekdays     = !empty($raw['weekdays']) ? array_map('intval', $raw['weekdays']) : [];
        $cursor       = clone $from;

        while ($cursor <= $to) {
            // If weekdays filter is set, skip days not matching
            if (!empty($weekdays) && !in_array((int) $cursor->format('N'), $weekdays)) {
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

        return redirect()
            ->route('admin.room-types.daily-availability', [$accommodation, $roomType])
            ->with('status', 'تنظیم ظرفیت/قیمت روزانه با موفقیت ذخیره شد.');
    }

    public function destroyDailyAvailability(Accommodation $accommodation, RoomType $roomType, RoomTypeDailyOverride $override)
    {
        abort_if($override->room_type_id !== $roomType->id, 404);
        $override->delete();

        return redirect()
            ->route('admin.room-types.daily-availability', [$accommodation, $roomType])
            ->with('status', 'تنظیم ظرفیت حذف شد.');
    }
}


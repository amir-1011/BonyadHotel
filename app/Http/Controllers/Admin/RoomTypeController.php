<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accommodation;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\RoomTypeBlockedDate;
use App\Models\RoomTypeDailyOverride;
use App\Models\RoomTypeWeeklyPriceRule;
use App\Services\BlockedDatesService;
use App\Services\DailyAvailabilityService;
use App\Services\ImageUploadService;
use App\Services\RoomSyncService;
use App\Support\DailyOverrideRangeGrouper;
use App\Support\JalaliCalendarGrid;
use Morilog\Jalali\Jalalian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RoomTypeController extends Controller
{
    public function index(Accommodation $accommodation)
    {
        $roomTypes = $accommodation->roomTypes()
            ->with(['rates' => fn ($q) => $q->orderBy('price_per_night'), 'rooms'])
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

        $roomType = $accommodation->roomTypes()->latest('id')->first();
        if ($roomType) {
            app(RoomSyncService::class)->syncFromRoomType($roomType);
        }

        return redirect()
            ->route('admin.room-types.index', $accommodation)
            ->with('status', 'نوع اتاق با موفقیت اضافه شد.');
    }

    public function edit(Accommodation $accommodation, RoomType $roomType)
    {
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);
        $roomType->load(['rates' => fn ($q) => $q->orderBy('price_per_night')]);
        app(RoomSyncService::class)->syncFromRoomType($roomType);
        $roomType->load('rooms');

        return view('admin.room_types.edit', compact('accommodation', 'roomType'));
    }

    public function update(Request $request, Accommodation $accommodation, RoomType $roomType)
    {
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

        $data = $this->validated($request, $roomType);
        $data['images'] = $this->mergeImages($request, $roomType);

        $physicalRooms = $request->input('physical_rooms', []);
        unset($data['physical_rooms']);

        $roomType->update($data);
        app(RoomSyncService::class)->updateRooms($roomType->fresh(), $physicalRooms);

        return redirect()
            ->route('admin.room-types.edit', [$accommodation, $roomType])
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
        $data = $request->validate(array_merge([
            'name'                 => ['required', 'string', 'max:120'],
            'description'          => ['nullable', 'string', 'max:1000'],
            'bed_type'             => ['nullable', 'string', 'max:80'],
            'capacity'             => ['required', 'integer', 'min:1', 'max:20'],
            'extra_capacity'       => ['nullable', 'integer', 'min:1', 'max:10'],
            'extra_capacity_price' => ['nullable', 'integer', 'min:0'],
            'size_sqm'             => ['nullable', 'numeric', 'min:1', 'max:9999'],
            'smoking'              => ['nullable', 'boolean'],
            'has_private_bathroom' => ['nullable', 'boolean'],
            'room_count'           => ['required', 'integer', 'min:1'],
            'sort_order'           => ['nullable', 'integer', 'min:0'],
            'amenities'            => ['nullable', 'array'],
            'amenities.*'          => ['string', 'max:60'],
            'keep_images'          => ['nullable', 'array'],
            'is_active'            => ['nullable', 'boolean'],
            'physical_rooms'       => ['nullable', 'array'],
            'physical_rooms.*.id'  => ['nullable', 'integer'],
            'physical_rooms.*.name'=> ['required_with:physical_rooms', 'string', 'max:120'],
            'physical_rooms.*.description' => ['nullable', 'string', 'max:1000'],
            'physical_rooms.*.amenities'   => ['nullable', 'array'],
            'physical_rooms.*.amenities.*' => ['string', 'max:60'],
        ], ImageUploadService::manyFileRules('images'), ImageUploadService::manyFileRules('new_images')));

        $data['smoking']              = $this->resolveHiddenBoolean($request, 'smoking', $roomType?->smoking ?? false);
        $data['has_private_bathroom'] = $this->resolveHiddenBoolean($request, 'has_private_bathroom', $roomType?->has_private_bathroom ?? true);
        $data['is_active']            = $request->boolean('is_active', true);
        $data['extra_capacity']       = $request->input('extra_capacity') ? (int) $request->input('extra_capacity') : null;
        $data['extra_capacity_price'] = $request->input('extra_capacity') ? (int) $request->input('extra_capacity_price') : null;

        return $data;
    }

    private function resolveHiddenBoolean(Request $request, string $field, bool $default): bool
    {
        if (!$request->has($field)) {
            return $default;
        }

        return $request->boolean($field);
    }

    private function validatedRate(Request $request): array
    {
        $request->merge([
            'price_per_night' => parse_money_input($request->input('price_per_night')),
            'breakfast_price_per_person' => parse_money_input($request->input('breakfast_price_per_person')),
        ]);

        $data = $request->validate([
            'rate_name'                  => ['required', 'string', 'max:100'],
            'price_per_night'            => ['required', 'integer', 'min:1'],
            'breakfast_included'         => ['nullable', 'boolean'],
            'breakfast_price_per_person' => ['nullable', 'integer', 'min:0'],
            'cancellation_policy'        => ['required', 'in:free,non_refundable'],
            'payment_type'               => ['required', 'in:pay_at_hotel,prepay_online'],
            'is_active'                  => ['nullable', 'boolean'],
        ]);

        $data['breakfast_included'] = $request->boolean('breakfast_included');
        $data['is_active'] = $request->boolean('is_active');
        $data['name'] = $data['rate_name'];
        unset($data['rate_name']);

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

    public function blockedDates(Accommodation $accommodation, RoomType $roomType, RoomSyncService $roomSync, BlockedDatesService $service)
    {
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

        $roomSync->syncFromRoomType($roomType);
        $roomType->load('rooms');

        $from = now()->startOfDay()->format('Y-m-d');
        $to   = now()->addMonths(3)->endOfMonth()->addDay()->format('Y-m-d');
        $availabilityMap = $roomType->availabilityMap($from, $to);

        $blockedDates = $roomType->blockedDates()
            ->with('room')
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->orderBy('room_id')
            ->get();

        $roomBookings = $service->upcomingBookingsByRoom($roomType);

        return view('admin.room_types.blocked_dates', compact('accommodation', 'roomType', 'blockedDates', 'availabilityMap', 'roomBookings'));
    }

    public function previewBlockedDate(Request $request, Accommodation $accommodation, RoomType $roomType, BlockedDatesService $service, RoomSyncService $roomSync)
    {
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

        $roomSync->syncFromRoomType($roomType);
        $roomType->load('rooms');

        $request->validate([
            'date_from' => ['required', 'string'],
            'date_to'   => ['required', 'string'],
        ]);

        $roomIds = array_map('intval', (array) $request->input('room_ids', []));

        return response()->json($service->previewConflicts(
            $roomType,
            $request->string('date_from')->toString(),
            $request->string('date_to')->toString(),
            $roomIds,
        ));
    }

    public function storeBlockedDate(Request $request, Accommodation $accommodation, RoomType $roomType, BlockedDatesService $service, RoomSyncService $roomSync)
    {
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

        $roomSync->syncFromRoomType($roomType);
        $roomType->load('rooms');

        if ($roomType->rooms->isEmpty()) {
            return back()->withErrors(['room_ids' => 'ابتدا اتاق‌های فیزیکی را در صفحه ویرایش تعریف کنید.'])->withInput();
        }

        $raw = $service->validateStoreRequest($request, $roomType);
        $range = $service->parseJalaliRange($raw['date_from'], $raw['date_to']);
        if (!$range['ok']) {
            return back()->withErrors($range['errors'] ?? [])->withInput();
        }

        $conflictErrors = $service->validateNoBookingConflicts($roomType, $range['from'], $range['to'], $raw['room_ids']);
        if ($conflictErrors !== null) {
            return back()->withErrors($conflictErrors)->withInput();
        }

        $service->store($roomType, $range['from'], $range['to'], $raw['room_ids'], $raw['reason'] ?? null);

        return redirect()
            ->route('admin.room-types.blocked-dates', [$accommodation, $roomType])
            ->with('status', 'تاریخ‌های انتخابی برای اتاق‌های مشخص‌شده مسدود شدند.');
    }

    public function destroyBlockedDate(Accommodation $accommodation, RoomType $roomType, RoomTypeBlockedDate $blocked)
    {
        abort_if($blocked->room_type_id !== $roomType->id, 404);
        $blocked->delete();

        return redirect()
            ->route('admin.room-types.blocked-dates', [$accommodation, $roomType])
            ->with('status', 'تاریخ مسدودسازی حذف شد.');
    }

    public function destroyBlockedDateRange(Request $request, Accommodation $accommodation, RoomType $roomType, BlockedDatesService $service)
    {
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

        $data = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to'   => ['required', 'date', 'after_or_equal:date_from'],
            'room_ids'  => ['required', 'array', 'min:1'],
            'room_ids.*'=> ['nullable', 'integer', 'exists:rooms,id'],
            'reason'    => ['nullable', 'string', 'max:200'],
        ]);

        $validRoomIds = $roomType->rooms()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $roomIds = collect($data['room_ids'])
            ->map(fn ($id) => $id === null || $id === '' ? null : (int) $id)
            ->filter(fn ($id) => $id === null || in_array($id, $validRoomIds, true))
            ->unique()
            ->values()
            ->all();

        if ($roomIds === []) {
            return back()->withErrors(['room_ids' => 'اتاق انتخاب‌شده معتبر نیست.']);
        }

        $deleted = $service->destroyRange(
            $roomType,
            $data['date_from'],
            $data['date_to'],
            $roomIds,
            $data['reason'] ?? null,
        );

        return redirect()
            ->route('admin.room-types.blocked-dates', [$accommodation, $roomType])
            ->with('status', $deleted > 0 ? 'بازه مسدودسازی حذف شد.' : 'موردی برای حذف یافت نشد.');
    }

    // ── Daily availability overrides ─────────────────────────────────────────

    public function dailyAvailability(Accommodation $accommodation, RoomType $roomType)
    {
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

        $roomType->load('rates');

        [$fromGreg, $toGreg] = JalaliCalendarGrid::gregorianRangeForUpcomingMonths(3);

        $availabilityMap = $roomType->availabilityMap($fromGreg, $toGreg);
        $calendarMonths  = JalaliCalendarGrid::upcomingMonths(3);

        $overrides = $roomType->dailyOverrides()
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->get();

        $rateDailyOverrides = \App\Models\RoomRateDailyPriceOverride::query()
            ->whereIn('room_rate_id', $roomType->rates->pluck('id'))
            ->whereDate('date', '>=', now()->toDateString())
            ->with('roomRate')
            ->orderBy('date')
            ->get()
            ->groupBy(fn ($o) => $o->date->toDateString());

        $overrideRanges = DailyOverrideRangeGrouper::group($overrides, $rateDailyOverrides->all());

        $weeklyRules = $roomType->weeklyPriceRules()->get();

        $rateWeeklyRules = \App\Models\RoomRateWeeklyPriceRule::query()
            ->whereIn('room_rate_id', $roomType->rates->pluck('id'))
            ->with('roomRate')
            ->orderBy('weekday')
            ->orderBy('room_rate_id')
            ->get();

        return view('admin.room_types.daily_availability', compact(
            'accommodation', 'roomType', 'availabilityMap', 'overrides', 'overrideRanges', 'weeklyRules',
            'rateWeeklyRules', 'rateDailyOverrides', 'calendarMonths'
        ));
    }

    public function storeDailyAvailability(Request $request, Accommodation $accommodation, RoomType $roomType, DailyAvailabilityService $service)
    {
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

        $raw = $service->validateStoreRequest($request, $roomType);
        $raw['is_permanent_weekly'] = $request->boolean('is_permanent_weekly');

        $result = $service->store($roomType, $raw);

        if (!$result['ok']) {
            return back()->withErrors($result['errors'] ?? [])->withInput();
        }

        return redirect()
            ->route('admin.room-types.daily-availability', [$accommodation, $roomType])
            ->with('status', $result['message']);
    }

    public function destroyWeeklyPriceRule(Accommodation $accommodation, RoomType $roomType, RoomTypeWeeklyPriceRule $weeklyRule)
    {
        abort_if($weeklyRule->room_type_id !== $roomType->id, 404);

        $weeklyRule->delete();

        return redirect()
            ->route('admin.room-types.daily-availability', [$accommodation, $roomType])
            ->with('status', 'قانون هفتگی حذف شد.');
    }

    public function destroyRateWeeklyPriceRule(Accommodation $accommodation, RoomType $roomType, \App\Models\RoomRateWeeklyPriceRule $rateWeeklyRule)
    {
        abort_if($rateWeeklyRule->roomRate?->room_type_id !== $roomType->id, 404);

        $rateWeeklyRule->delete();

        return redirect()
            ->route('admin.room-types.daily-availability', [$accommodation, $roomType])
            ->with('status', 'قانون هفتگی تعرفه حذف شد.');
    }

    public function destroyDailyAvailability(Accommodation $accommodation, RoomType $roomType, RoomTypeDailyOverride $override, \App\Services\DailyOverrideCleanupService $cleanup)
    {
        abort_if($override->room_type_id !== $roomType->id, 404);

        $cleanup->deleteSingle($roomType, $override);

        return redirect()
            ->route('admin.room-types.daily-availability', [$accommodation, $roomType])
            ->with('status', 'تنظیم ظرفیت حذف شد.');
    }

    public function destroyDailyOverrideRange(Request $request, Accommodation $accommodation, RoomType $roomType, \App\Services\DailyOverrideCleanupService $cleanup)
    {
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

        $data = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to'   => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $deleted = $cleanup->deleteRange($roomType, $data['date_from'], $data['date_to']);

        return redirect()
            ->route('admin.room-types.daily-availability', [$accommodation, $roomType])
            ->with('status', $deleted > 0 ? 'بازه تنظیمات دستی حذف شد.' : 'موردی برای حذف یافت نشد.');
    }
}


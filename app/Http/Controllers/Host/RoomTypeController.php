<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Models\Accommodation;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\RoomTypeBlockedDate;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class RoomTypeController extends Controller
{
    // ─── Helpers ────────────────────────────────────────────────────────────

    /** Ensure the accommodation belongs to the current host. */
    private function authorizeAccommodation(Accommodation $accommodation): void
    {
        abort_if($accommodation->host_id !== Auth::id(), 403);
    }

    /** Ensure the room type belongs to one of the host's accommodations. */
    private function authorizeRoomType(RoomType $roomType): void
    {
        abort_if($roomType->accommodation->host_id !== Auth::id(), 403);
    }

    // ─── Room Type CRUD ──────────────────────────────────────────────────────

    public function index(Accommodation $accommodation)
    {
        $this->authorizeAccommodation($accommodation);

        $roomTypes = $accommodation->roomTypes()
            ->with(['rates' => fn($q) => $q->orderBy('price_per_night')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('host.room_types.index', compact('accommodation', 'roomTypes'));
    }

    public function create(Accommodation $accommodation)
    {
        $this->authorizeAccommodation($accommodation);

        return view('host.room_types.create', compact('accommodation'));
    }

    public function store(Request $request, Accommodation $accommodation)
    {
        $this->authorizeAccommodation($accommodation);

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
            'is_active'            => ['nullable', 'boolean'],
        ]);

        $data['smoking']              = $request->boolean('smoking');
        $data['has_private_bathroom'] = $request->boolean('has_private_bathroom', true);
        $data['is_active']            = $request->boolean('is_active', true);

        // Handle image uploads
        $images = [];
        if ($request->hasFile('images')) {
            $images = app(ImageUploadService::class)->storeManyWebp($request->file('images', []), 'room-types');
        }
        $data['images'] = $images;

        $accommodation->roomTypes()->create($data);

        return redirect()
            ->route('host.room-types.index', $accommodation)
            ->with('status', 'نوع اتاق با موفقیت اضافه شد.');
    }

    public function edit(Accommodation $accommodation, RoomType $roomType)
    {
        $this->authorizeAccommodation($accommodation);
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

        $roomType->load(['rates' => fn($q) => $q->orderBy('price_per_night')]);

        return view('host.room_types.edit', compact('accommodation', 'roomType'));
    }

    public function update(Request $request, Accommodation $accommodation, RoomType $roomType)
    {
        $this->authorizeAccommodation($accommodation);
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

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
            'new_images.*'         => ['nullable', 'image', 'max:4096'],
            'keep_images'          => ['nullable', 'array'],
            'is_active'            => ['nullable', 'boolean'],
        ]);

        $data['smoking']              = $request->boolean('smoking');
        $data['has_private_bathroom'] = $request->boolean('has_private_bathroom', true);
        $data['is_active']            = $request->boolean('is_active', true);

        // Handle existing images (keep only what user wants)
        $keepImages = $request->input('keep_images', []);
        $oldImages  = $roomType->images ?? [];
        foreach ($oldImages as $old) {
            if (!in_array($old, $keepImages)) {
                Storage::disk('public')->delete($old);
            }
        }

        // Append new uploads
        $images = array_filter($keepImages);
        if ($request->hasFile('new_images')) {
            $images = array_merge(
                $images,
                app(ImageUploadService::class)->storeManyWebp($request->file('new_images', []), 'room-types')
            );
        }
        $data['images'] = array_values($images);
        unset($data['keep_images'], $data['new_images']);

        $roomType->update($data);

        return redirect()
            ->route('host.room-types.index', $accommodation)
            ->with('status', 'نوع اتاق با موفقیت به‌روز شد.');
    }

    public function destroy(Accommodation $accommodation, RoomType $roomType)
    {
        $this->authorizeAccommodation($accommodation);
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

        foreach ($roomType->images ?? [] as $img) {
            Storage::disk('public')->delete($img);
        }

        $roomType->delete();

        return redirect()
            ->route('host.room-types.index', $accommodation)
            ->with('status', 'نوع اتاق حذف شد.');
    }

    // ─── Rate CRUD (via AJAX-friendly redirects) ─────────────────────────────

    public function storeRate(Request $request, Accommodation $accommodation, RoomType $roomType)
    {
        $this->authorizeAccommodation($accommodation);
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

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
        $data['is_active']          = $request->boolean('is_active', true);

        $roomType->rates()->create($data);

        return redirect()
            ->route('host.room-types.edit', [$accommodation, $roomType])
            ->with('status', 'تعرفه با موفقیت اضافه شد.');
    }

    public function updateRate(Request $request, Accommodation $accommodation, RoomType $roomType, RoomRate $rate)
    {
        $this->authorizeAccommodation($accommodation);
        abort_if($rate->room_type_id !== $roomType->id, 404);

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
        $data['is_active']          = $request->boolean('is_active', true);

        $rate->update($data);

        return redirect()
            ->route('host.room-types.edit', [$accommodation, $roomType])
            ->with('status', 'تعرفه با موفقیت به‌روز شد.');
    }

    public function destroyRate(Accommodation $accommodation, RoomType $roomType, RoomRate $rate)
    {
        $this->authorizeAccommodation($accommodation);
        abort_if($rate->room_type_id !== $roomType->id, 404);

        $rate->delete();

        return redirect()
            ->route('host.room-types.edit', [$accommodation, $roomType])
            ->with('status', 'تعرفه حذف شد.');
    }

    // ─── Blocked Dates ───────────────────────────────────────────────────────

    public function blockedDates(Accommodation $accommodation, RoomType $roomType)
    {
        $this->authorizeAccommodation($accommodation);
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

        // Get availability map for next 3 months for overview
        $from = now()->startOfDay()->format('Y-m-d');
        $to   = now()->addMonths(3)->endOfMonth()->addDay()->format('Y-m-d');
        $availabilityMap = $roomType->availabilityMap($from, $to);

        $blockedDates = $roomType->blockedDates()
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->get();

        return view('host.room_types.blocked_dates', compact('accommodation', 'roomType', 'blockedDates', 'availabilityMap'));
    }

    public function storeBlockedDate(Request $request, Accommodation $accommodation, RoomType $roomType)
    {
        $this->authorizeAccommodation($accommodation);
        abort_if($roomType->accommodation_id !== $accommodation->id, 404);

        $data = $request->validate([
            'date_from' => ['required', 'date', 'after_or_equal:today'],
            'date_to'   => ['required', 'date', 'after_or_equal:date_from'],
            'reason'    => ['nullable', 'string', 'max:200'],
        ]);

        $from   = new \DateTime($data['date_from']);
        $to     = new \DateTime($data['date_to']);
        $reason = $data['reason'] ?? null;
        $cursor = clone $from;

        while ($cursor <= $to) {
            RoomTypeBlockedDate::updateOrCreate(
                ['room_type_id' => $roomType->id, 'date' => $cursor->format('Y-m-d')],
                ['reason' => $reason]
            );
            $cursor->modify('+1 day');
        }

        return redirect()
            ->route('host.room-types.blocked-dates', [$accommodation, $roomType])
            ->with('status', 'تاریخ‌های انتخابی با موفقیت مسدود شدند.');
    }

    public function destroyBlockedDate(Accommodation $accommodation, RoomType $roomType, RoomTypeBlockedDate $blocked)
    {
        $this->authorizeAccommodation($accommodation);
        abort_if($blocked->room_type_id !== $roomType->id, 404);

        $blocked->delete();

        return redirect()
            ->route('host.room-types.blocked-dates', [$accommodation, $roomType])
            ->with('status', 'تاریخ مسدودسازی حذف شد.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accommodation;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Services\ImageUploadService;
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

        if ($request->hasFile('images')) {
            $images = app(ImageUploadService::class)->storeManyWebp($request->file('images', []), 'room-types');
        }

        return $images;
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

        if ($request->hasFile('new_images')) {
            $images = array_merge(
                $images,
                app(ImageUploadService::class)->storeManyWebp($request->file('new_images', []), 'room-types')
            );
        }

        return array_values($images);
    }
}

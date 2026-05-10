<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Models\Accommodation;
use App\Models\Province;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AccommodationController extends Controller
{
    public function index()
    {
        $accommodations = Auth::user()->accommodations()
            ->with('city.province')
            ->withCount('bookings')
            ->latest()->get();

        return view('host.accommodations.index', compact('accommodations'));
    }

    public function create()
    {
        $provinces = Province::with('cities')->orderBy('name')->get();
        return view('host.accommodations.create', compact('provinces'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['host_id']   = Auth::id();
        $data['amenities'] = $this->parseAmenities($request->input('amenities_raw', ''));
        $data['is_active'] = false;

        // Handle image uploads
        $images = [];
        if ($request->hasFile('images')) {
            $images = app(ImageUploadService::class)->storeManyWebp($request->file('images', []), 'accommodations');
        }
        $data['images'] = $images;

        Accommodation::create($data);
        return redirect()->route('host.accommodations.index')
            ->with('status', 'اقامتگاه ثبت شد و پس از تأیید مدیر نمایش داده می‌شود.');
    }

    public function edit(Accommodation $accommodation)
    {
        abort_if($accommodation->host_id !== Auth::id(), 403);
        $provinces = Province::with('cities')->orderBy('name')->get();
        return view('host.accommodations.edit', compact('accommodation', 'provinces'));
    }

    public function update(Request $request, Accommodation $accommodation)
    {
        abort_if($accommodation->host_id !== Auth::id(), 403);
        $data = $this->validated($request, $accommodation);
        $data['amenities'] = $this->parseAmenities($request->input('amenities_raw', ''));

        // Manage existing images: keep only checked ones, delete the rest
        $existingImages = $accommodation->images ?? [];
        $keepImages     = $request->input('keep_images', []);
        foreach ($existingImages as $img) {
            if (!in_array($img, $keepImages)) {
                Storage::disk('public')->delete($img);
            }
        }
        $finalImages = array_values(array_intersect($existingImages, $keepImages));

        // Add newly uploaded images
        if ($request->hasFile('new_images')) {
            $finalImages = array_merge(
                $finalImages,
                app(ImageUploadService::class)->storeManyWebp($request->file('new_images', []), 'accommodations')
            );
        }
        $data['images'] = $finalImages;

        $accommodation->update($data);
        return redirect()->route('host.accommodations.index')
            ->with('status', 'اقامتگاه ویرایش شد.');
    }

    public function destroy(Accommodation $accommodation)
    {
        abort_if($accommodation->host_id !== Auth::id(), 403);
        $accommodation->delete();
        return redirect()->route('host.accommodations.index')
            ->with('status', 'اقامتگاه حذف شد.');
    }

    private function validated(Request $request, ?Accommodation $accommodation = null): array
    {
        return $request->validate([
            'city_id'         => ['required', 'exists:cities,id'],
            'name'            => ['required', 'string', 'max:200'],
            'description'     => ['nullable', 'string'],
            'type'            => ['required', 'in:hotel,villa,apartment,hostel,traditional'],
            'price_per_night' => ['required', 'integer', 'min:0'],
            'capacity'        => ['required', 'integer', 'min:1'],
            'rooms'           => ['required', 'integer', 'min:1'],
            'address'         => ['nullable', 'string'],
            'lat'             => ['nullable', 'numeric', 'between:-90,90'],
            'lng'             => ['nullable', 'numeric', 'between:-180,180'],
            'images.*'        => ['nullable', 'image', 'max:4096'],
            'new_images.*'    => ['nullable', 'image', 'max:4096'],
        ]);
    }

    private function parseAmenities(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", $raw))));
    }
}

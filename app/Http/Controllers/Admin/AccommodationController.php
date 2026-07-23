<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accommodation;
use App\Models\AccommodationType;
use Illuminate\Validation\Rule;
use App\Models\Booking;
use App\Models\City;
use App\Models\Province;
use App\Models\User;
use App\Services\AccommodationSalesReportService;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AccommodationController extends Controller
{
    public function index(Request $request)
    {
        $query = Accommodation::with('city.province', 'host')->latest();

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhereHas('host', fn ($q) => $q->where('name', 'like', "%{$s}%"))
                ->orWhereHas('hosts', fn ($q) => $q->where('name', 'like', "%{$s}%"))
            );
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $accommodations = $query->paginate(20)->withQueryString();
        return view('admin.accommodations.index', compact('accommodations'));
    }

    public function create()
    {
        $provinces = Province::with('cities')->orderBy('name')->get();
        $hosts = User::role('host')->orderBy('name')->get();
        return view('admin.accommodations.create', compact('provinces', 'hosts'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['amenities'] = $this->parseAmenities($request->input('amenities_raw', ''));
        $data['images'] = [];

        if ($request->hasFile('images')) {
            $data['images'] = app(ImageUploadService::class)->storeManyWebp($request->file('images', []), 'accommodations');
        }

        $accommodation = Accommodation::create($data);

        if (!empty($data['host_id'])) {
            $accommodation->grantHostAccess(User::find($data['host_id']));
        }
        return redirect()->route('admin.accommodations.index')
            ->with('status', 'اقامتگاه با موفقیت ثبت شد.');
    }

    public function edit(Accommodation $accommodation)
    {
        $provinces = Province::with('cities')->orderBy('name')->get();
        $hosts = User::role('host')->orderBy('name')->get();
        return view('admin.accommodations.edit', compact('accommodation', 'provinces', 'hosts'));
    }

    public function update(Request $request, Accommodation $accommodation)
    {
        $data = $this->validated($request, $accommodation);
        $data['amenities'] = $this->parseAmenities($request->input('amenities_raw', ''));

        $existingImages = $accommodation->images ?? [];
        $keepImages = $request->input('keep_images', []);

        foreach ($existingImages as $img) {
            if (!in_array($img, $keepImages)) {
                Storage::disk('public')->delete($img);
            }
        }

        $finalImages = array_values(array_intersect($existingImages, $keepImages));

        if ($request->hasFile('new_images')) {
            try {
                ImageUploadService::assertTotalImageCount(
                    count($finalImages) + count($request->file('new_images', []))
                );
            } catch (\RuntimeException $e) {
                return back()->withErrors(['new_images' => $e->getMessage()])->withInput();
            }

            $finalImages = array_merge(
                $finalImages,
                app(ImageUploadService::class)->storeManyWebp($request->file('new_images', []), 'accommodations')
            );
        }
        $data['images'] = $finalImages;

        $accommodation->update($data);

        if (!empty($data['host_id'])) {
            $accommodation->grantHostAccess(User::find($data['host_id']));
        }
        return redirect()->route('admin.accommodations.index')
            ->with('status', 'اقامتگاه ویرایش شد.');
    }

    public function destroy(Accommodation $accommodation)
    {
        $accommodation->delete();
        return redirect()->route('admin.accommodations.index')
            ->with('status', 'اقامتگاه حذف شد.');
    }

    public function toggleActive(Accommodation $accommodation)
    {
        $accommodation->update(['is_active' => !$accommodation->is_active]);
        return back()->with('status', $accommodation->is_active ? 'اقامتگاه فعال شد.' : 'اقامتگاه غیرفعال شد.');
    }

    private function validated(Request $request, ?Accommodation $accommodation = null): array
    {
        return $request->validate(array_merge([
            'city_id'        => ['required', 'exists:cities,id'],
            'county_id'      => ['nullable', 'exists:counties,id'],
            'host_id'        => ['nullable', 'exists:users,id'],
            'name'           => ['required', 'string', 'max:200'],
            'description'    => ['nullable', 'string'],
            'type'           => ['required', Rule::exists('accommodation_types', 'key')],
            'price_per_night'=> ['required', 'integer', 'min:0'],
            'capacity'       => ['required', 'integer', 'min:1'],
            'rooms'          => ['required', 'integer', 'min:1'],
            'address'        => ['nullable', 'string'],
            'lat'            => ['nullable', 'numeric'],
            'lng'            => ['nullable', 'numeric'],
            'image'          => ['nullable', 'string'],
            'is_active'      => ['boolean'],
        ], ImageUploadService::manyFileRules('images'), ImageUploadService::manyFileRules('new_images')));
    }

    public function salesReport(Accommodation $accommodation)
    {
        return view('admin.accommodations.report', app(AccommodationSalesReportService::class)->build($accommodation));
    }

    private function parseAmenities(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", $raw))));
    }
}

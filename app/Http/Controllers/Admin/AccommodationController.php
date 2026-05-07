<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accommodation;
use App\Models\City;
use App\Models\Province;
use App\Models\User;
use Illuminate\Http\Request;

class AccommodationController extends Controller
{
    public function index(Request $request)
    {
        $query = Accommodation::with('city.province', 'host')->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where('name', 'like', "%$s%");
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
        Accommodation::create($data);
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
        $accommodation->update($data);
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
        return $request->validate([
            'city_id'        => ['required', 'exists:cities,id'],
            'host_id'        => ['nullable', 'exists:users,id'],
            'name'           => ['required', 'string', 'max:200'],
            'description'    => ['nullable', 'string'],
            'type'           => ['required', 'in:hotel,villa,apartment,hostel,traditional'],
            'price_per_night'=> ['required', 'integer', 'min:0'],
            'capacity'       => ['required', 'integer', 'min:1'],
            'rooms'          => ['required', 'integer', 'min:1'],
            'address'        => ['nullable', 'string'],
            'lat'            => ['nullable', 'numeric'],
            'lng'            => ['nullable', 'numeric'],
            'image'          => ['nullable', 'string'],
            'is_active'      => ['boolean'],
        ]);
    }

    private function parseAmenities(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", $raw))));
    }
}

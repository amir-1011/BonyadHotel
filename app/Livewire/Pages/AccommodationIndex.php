<?php

namespace App\Livewire\Pages;

use App\Models\Accommodation;
use App\Models\City;
use App\Models\Province;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'جستجوی اقامتگاه'])]
class AccommodationIndex extends Component
{
    use WithPagination;

    #[Url(as: 'province_id')]
    public ?int $provinceId = null;

    #[Url(as: 'city_id')]
    public ?int $cityId = null;

    #[Url(as: 'check_in')]
    public ?string $checkIn = null;

    #[Url(as: 'check_out')]
    public ?string $checkOut = null;

    #[Url]
    public ?int $guests = null;

    #[Url]
    public bool $wheelchair = false;

    #[Url]
    public ?float $lat = null;

    #[Url]
    public ?float $lng = null;

    #[Url]
    public int $radius = 30;

    public function updatedProvinceId(): void
    {
        $this->cityId = null;
        $this->resetPage();
    }

    public function updatedCityId(): void { $this->resetPage(); }
    public function updatedCheckIn(): void { $this->resetPage(); }
    public function updatedCheckOut(): void { $this->resetPage(); }
    public function updatedGuests(): void { $this->resetPage(); }
    public function updatedWheelchair(): void { $this->resetPage(); }

    public function render()
    {
        $provinces = Province::orderBy('name')->get();
        $cities    = $this->provinceId
            ? City::where('province_id', $this->provinceId)->orderBy('name')->get()
            : collect();

        $query = Accommodation::with('city.province')->where('is_active', true);

        if ($this->cityId) {
            $query->where('city_id', $this->cityId);
        } elseif ($this->provinceId) {
            $query->whereHas('city', fn($q) => $q->where('province_id', $this->provinceId));
        } elseif ($this->lat && $this->lng) {
            $lat      = (float) $this->lat;
            $lng      = (float) $this->lng;
            $radius   = max(1, min(500, (float) $this->radius));
            $latDelta = $radius / 111.0;
            $lngDelta = $radius / (111.0 * cos(deg2rad($lat)));
            $query->whereNotNull('lat')->whereNotNull('lng')
                ->whereBetween('lat', [$lat - $latDelta, $lat + $latDelta])
                ->whereBetween('lng', [$lng - $lngDelta, $lng + $lngDelta]);
        }

        if ($this->guests) {
            $query->where('capacity', '>=', $this->guests);
        }

        if ($this->wheelchair) {
            $query->whereJsonContains('amenities', 'مناسب ویلچر');
        }

        if ($this->checkIn && $this->checkOut) {
            $ci = $this->checkIn;
            $co = $this->checkOut;
            $query->whereDoesntHave('bookings', function ($q) use ($ci, $co) {
                $q->whereIn('status', ['confirmed', 'pending'])
                    ->where(function ($inner) use ($ci, $co) {
                        $inner->whereBetween('check_in', [$ci, $co])
                            ->orWhereBetween('check_out', [$ci, $co])
                            ->orWhere(fn($i2) => $i2->where('check_in', '<=', $ci)->where('check_out', '>=', $co));
                    });
            });
        }

        // Haversine geo search: fetch all, filter, then manually paginate
        if ($this->lat && $this->lng) {
            $lat    = (float) $this->lat;
            $lng    = (float) $this->lng;
            $radius = max(1, min(500, (float) $this->radius));

            $all = $query->latest()->get()->filter(function ($a) use ($lat, $lng, $radius) {
                $R    = 6371;
                $dLat = deg2rad($a->lat - $lat);
                $dLng = deg2rad($a->lng - $lng);
                $h    = sin($dLat / 2) ** 2 + cos(deg2rad($lat)) * cos(deg2rad($a->lat)) * sin($dLng / 2) ** 2;
                return 2 * $R * asin(sqrt($h)) <= $radius;
            })->values();

            $page           = Paginator::resolveCurrentPage();
            $perPage        = 12;
            $accommodations = new LengthAwarePaginator(
                $all->forPage($page, $perPage)->values(),
                $all->count(),
                $perPage,
                $page,
                ['path' => Paginator::resolveCurrentPath()]
            );
        } else {
            $accommodations = $query->latest()->paginate(12);
        }

        return view('accommodations.index', compact('accommodations', 'provinces', 'cities'));
    }
}

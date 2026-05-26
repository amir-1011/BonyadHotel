<?php

namespace App\Livewire\Host;

use App\Models\Accommodation;
use App\Models\City;
use App\Models\Province;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.host', ['title' => 'ثبت اقامتگاه', 'pageTitle' => 'ثبت اقامتگاه جدید'])]
class AccommodationCreate extends Component
{
    use WithFileUploads;

    public int    $provinceId       = 0;
    public int    $cityId          = 0;
    public string $name            = '';
    public string $description     = '';
    public string $type            = 'hotel';
    public int    $pricePerNight   = 0;
    public int    $capacity        = 1;
    public int    $rooms           = 1;
    public string $address         = '';
    public string $lat             = '';
    public string $lng             = '';
    public string $amenitiesRaw    = '';
    public array  $images          = [];

    public function updatedProvinceId(): void
    {
        $this->cityId = 0;
    }

    protected function rules(): array
    {
        return [
            'cityId'        => ['required', 'exists:cities,id'],
            'name'          => ['required', 'string', 'max:200'],
            'description'   => ['nullable', 'string'],
            'type'          => ['required', 'in:hotel,villa,apartment,hostel,traditional'],
            'pricePerNight' => ['required', 'integer', 'min:0'],
            'capacity'      => ['required', 'integer', 'min:1'],
            'rooms'         => ['required', 'integer', 'min:1'],
            'address'       => ['nullable', 'string'],
            'lat'           => ['nullable', 'numeric'],
            'lng'           => ['nullable', 'numeric'],
            'images.*'      => ['nullable', 'image', 'max:4096'],
        ];
    }

    private function parseAmenities(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function store(): void
    {
        $this->validate();

        $uploadedImages = [];
        foreach ($this->images as $img) {
            $uploadedImages[] = $img->store('accommodations', 'public');
        }

        Accommodation::create([
            'city_id'         => $this->cityId,
            'host_id'         => Auth::id(),
            'name'            => $this->name,
            'description'     => $this->description ?: null,
            'type'            => $this->type,
            'price_per_night' => $this->pricePerNight,
            'capacity'        => $this->capacity,
            'rooms'           => $this->rooms,
            'address'         => $this->address ?: null,
            'lat'             => $this->lat !== '' ? (float) $this->lat : null,
            'lng'             => $this->lng !== '' ? (float) $this->lng : null,
            'is_active'       => false, // awaiting admin approval
            'amenities'       => $this->parseAmenities($this->amenitiesRaw),
            'images'          => $uploadedImages,
        ]);

        session()->flash('status', 'اقامتگاه ثبت شد و پس از تأیید مدیر نمایش داده می‌شود.');
        $this->redirectRoute('host.accommodations.index', navigate: true);
    }

    public function render()
    {
        $provinces = Province::orderBy('name')->get();
        $cities = $this->provinceId
            ? City::where('province_id', $this->provinceId)->orderBy('name')->get()
            : collect();
        return view('host.accommodations.create', compact('provinces', 'cities'));
    }
}

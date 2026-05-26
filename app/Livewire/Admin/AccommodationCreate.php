<?php

namespace App\Livewire\Admin;

use App\Models\Accommodation;
use App\Models\Province;
use App\Models\User;
use App\Services\ImageUploadService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin', ['title' => 'افزودن اقامتگاه', 'pageTitle' => 'افزودن اقامتگاه'])]
class AccommodationCreate extends Component
{
    use WithFileUploads;

    public int    $provinceId      = 0;
    public int    $cityId          = 0;
    public ?int   $hostId          = null;
    public string $name            = '';
    public string $description     = '';
    public string $type            = 'hotel';
    public int    $pricePerNight   = 0;
    public int    $capacity        = 1;
    public int    $rooms           = 1;
    public string $address         = '';
    public string $lat             = '';
    public string $lng             = '';
    public bool   $isActive        = true;
    public string $amenitiesRaw    = '';
    public string $image           = '';
    public array  $images          = [];

    protected function rules(): array
    {
        return [
            'cityId'        => ['required', 'exists:cities,id'],
            'hostId'        => ['nullable', 'exists:users,id'],
            'name'          => ['required', 'string', 'max:200'],
            'description'   => ['nullable', 'string'],
            'type'          => ['required', 'in:hotel,villa,apartment,hostel,traditional'],
            'pricePerNight' => ['required', 'integer', 'min:0'],
            'capacity'      => ['required', 'integer', 'min:1'],
            'rooms'         => ['required', 'integer', 'min:1'],
            'address'       => ['nullable', 'string'],
            'lat'           => ['nullable', 'numeric'],
            'lng'           => ['nullable', 'numeric'],
            'isActive'      => ['boolean'],
            'images.*'      => ['nullable', 'image', 'max:4096'],
        ];
    }

    private function parseAmenities(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function updatedProvinceId(): void
    {
        $this->cityId = 0;
    }

    public function store(): void
    {
        $this->validate();

        $uploadedImages = [];
        if (!empty($this->images)) {
            foreach ($this->images as $img) {
                $uploadedImages[] = $img->store('accommodations', 'public');
            }
        }

        Accommodation::create([
            'city_id'         => $this->cityId,
            'host_id'         => $this->hostId,
            'name'            => $this->name,
            'description'     => $this->description ?: null,
            'type'            => $this->type,
            'price_per_night' => $this->pricePerNight,
            'capacity'        => $this->capacity,
            'rooms'           => $this->rooms,
            'address'         => $this->address ?: null,
            'lat'             => $this->lat !== '' ? (float) $this->lat : null,
            'lng'             => $this->lng !== '' ? (float) $this->lng : null,
            'is_active'       => $this->isActive,
            'amenities'       => $this->parseAmenities($this->amenitiesRaw),
            'image'           => $this->image ?: null,
            'images'          => $uploadedImages,
        ]);

        session()->flash('status', 'اقامتگاه با موفقیت ثبت شد.');
        $this->redirectRoute('admin.accommodations.index', navigate: true);
    }

    public function render()
    {
        $provinces = Province::orderBy('name')->get();
        $cities    = $this->provinceId ? \App\Models\City::where('province_id', $this->provinceId)->orderBy('name')->get() : collect();
        $hosts     = User::role('host')->orderBy('name')->get();
        return view('admin.accommodations.create', compact('provinces', 'cities', 'hosts'));
    }
}

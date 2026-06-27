<?php

namespace App\Livewire\Host;

use App\Models\Accommodation;
use App\Models\City;
use App\Models\County;
use App\Models\Province;
use App\Livewire\Concerns\ManagesAccommodationContactInfo;
use App\Livewire\Concerns\ManagesAccommodationCatalog;
use App\Models\AccommodationType;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.host', ['title' => 'ثبت اقامتگاه', 'pageTitle' => 'ثبت اقامتگاه جدید'])]
class AccommodationCreate extends Component
{
    use WithFileUploads;
    use ManagesAccommodationContactInfo;
    use ManagesAccommodationCatalog;

    public int    $provinceId       = 0;
    public int    $cityId          = 0;
    public int    $countyId        = 0;
    public string $name            = '';
    public string $description     = '';
    public string $type            = 'hotel';
    public int    $pricePerNight   = 0;
    public int    $capacity        = 1;
    public int    $rooms           = 1;
    public bool   $childrenUnder6AllocateBed = true;
    public int    $childrenUnder6DiscountPercentage = 50;
    public string $address         = '';
    public string $lat             = '';
    public string $lng             = '';
    public string $amenitiesRaw    = '';
    public array  $images          = [];

    public function mount(): void
    {
        $this->phoneNumbers = [$this->emptyPhoneRow()];
    }

    protected function rules(): array
    {
        return array_merge([
            'cityId'        => ['required', 'exists:cities,id'],
            'countyId'      => $this->countyIdRules(),
            'name'          => ['required', 'string', 'max:200'],
            'description'   => ['nullable', 'string'],
            'type'          => $this->accommodationTypeRule(),
            'pricePerNight' => ['required', 'integer', 'min:0'],
            'capacity'      => ['required', 'integer', 'min:1'],
            'rooms'         => ['required', 'integer', 'min:1'],
            'childrenUnder6AllocateBed' => ['boolean'],
            'childrenUnder6DiscountPercentage' => ['required', 'integer', 'min:0', 'max:100'],
            'address'       => ['nullable', 'string'],
            'lat'           => ['nullable', 'numeric'],
            'lng'           => ['nullable', 'numeric'],
            'images.*'      => ['nullable', 'image', 'max:4096'],
        ], $this->contactInfoRules());
    }

    private function parseAmenities(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function store(): void
    {
        $this->validate();
        $this->validateContactInfo();

        $uploadedImages = [];
        foreach ($this->images as $img) {
            $uploadedImages[] = $img->store('accommodations', 'public');
        }

        $accommodation = Accommodation::create(array_merge([
            'city_id'         => $this->cityId,
            'county_id'       => $this->normalizedCountyId(),
            'host_id'         => Auth::id(),
            'name'            => $this->name,
            'description'     => $this->description ?: null,
            'type'            => $this->type,
            'price_per_night' => $this->pricePerNight,
            'capacity'        => $this->capacity,
            'rooms'           => $this->rooms,
            'children_under_6_allocate_bed' => $this->childrenUnder6AllocateBed,
            'children_under_6_discount_percentage' => $this->childrenUnder6DiscountPercentage,
            'address'         => $this->address ?: null,
            'lat'             => $this->lat !== '' ? (float) $this->lat : null,
            'lng'             => $this->lng !== '' ? (float) $this->lng : null,
            'is_active'       => false, // awaiting admin approval
            'amenities'       => $this->parseAmenities($this->amenitiesRaw),
            'images'          => $uploadedImages,
        ], $this->contactInfoAttributes()));

        $accommodation->grantHostAccess(Auth::user());
        app(\App\Services\VeteranPolicyProvisioner::class)->seedForAccommodation($accommodation);

        session()->flash('status', 'اقامتگاه ثبت شد و پس از تأیید مدیر نمایش داده می‌شود.');
        $this->redirectRoute('host.accommodations.index', navigate: true);
    }

    public function render()
    {
        $provinces = Province::orderBy('name')->get();
        $cities = $this->provinceId
            ? City::where('province_id', $this->provinceId)->orderBy('name')->get()
            : collect();
        $counties = $this->provinceId
            ? County::where('province_id', $this->provinceId)->orderBy('name')->get()
            : collect();
        $accommodationTypes = AccommodationType::options();
        return view('host.accommodations.create', compact('provinces', 'cities', 'counties', 'accommodationTypes'));
    }
}

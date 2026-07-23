<?php

namespace App\Livewire\Admin;

use App\Models\Accommodation;
use App\Models\County;
use App\Models\Province;
use App\Models\User;
use App\Livewire\Concerns\ManagesAccommodationContactInfo;
use App\Livewire\Concerns\ManagesAccommodationCatalog;
use App\Livewire\Concerns\ManagesLivewireImageUploads;
use App\Models\AccommodationType;
use App\Services\ImageUploadService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin', ['title' => 'افزودن اقامتگاه', 'pageTitle' => 'افزودن اقامتگاه'])]
class AccommodationCreate extends Component
{
    use WithFileUploads;
    use ManagesAccommodationContactInfo;
    use ManagesAccommodationCatalog;
    use ManagesLivewireImageUploads;

    public int    $provinceId      = 0;
    public int    $cityId          = 0;
    public int    $countyId        = 0;
    public ?int   $hostId          = null;
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
    public bool   $isActive        = true;
    public string $amenitiesRaw    = '';
    public string $image           = '';
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
            'hostId'        => ['nullable', 'exists:users,id'],
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
            'isActive'      => ['boolean'],
        ], $this->imageUploadRules('images'), $this->contactInfoRules());
    }

    private function parseAmenities(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function store(): void
    {
        $this->validate();
        $this->validateContactInfo();

        try {
            $uploadedImages = app(ImageUploadService::class)->storeManyWebp($this->images, 'accommodations');
        } catch (\RuntimeException $e) {
            $this->addError('images', $e->getMessage());
            return;
        }

        $accommodation = Accommodation::create(array_merge([
            'city_id'         => $this->cityId,
            'county_id'       => $this->normalizedCountyId(),
            'host_id'         => $this->hostId,
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
            'is_active'       => $this->isActive,
            'amenities'       => $this->parseAmenities($this->amenitiesRaw),
            'image'           => $this->image ?: null,
            'images'          => $uploadedImages,
        ], $this->contactInfoAttributes()));

        if ($this->hostId) {
            $accommodation->grantHostAccess(User::find($this->hostId));
        }

        app(\App\Services\VeteranPolicyProvisioner::class)->seedForAccommodation($accommodation);
        app(\App\Services\CancellationPolicyProvisioner::class)->seedForAccommodation($accommodation);

        session()->flash('status', 'اقامتگاه با موفقیت ثبت شد.');
        $this->redirectRoute('admin.accommodations.index', navigate: true);
    }

    public function render()
    {
        $provinces = Province::orderBy('name')->get();
        $cities    = $this->provinceId ? \App\Models\City::where('province_id', $this->provinceId)->orderBy('name')->get() : collect();
        $counties  = $this->provinceId ? County::where('province_id', $this->provinceId)->orderBy('name')->get() : collect();
        $hosts              = User::role('host')->orderBy('name')->get();
        $accommodationTypes = AccommodationType::options();
        return view('admin.accommodations.create', compact('provinces', 'cities', 'counties', 'hosts', 'accommodationTypes'));
    }
}

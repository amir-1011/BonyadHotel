<?php

namespace App\Livewire\Admin;

use App\Models\Accommodation;
use App\Models\County;
use App\Models\Province;
use App\Models\User;
use App\Livewire\Concerns\ManagesAccommodationImageGallery;
use App\Livewire\Concerns\ManagesAccommodationContactInfo;
use App\Livewire\Concerns\ManagesAccommodationCatalog;
use App\Livewire\Concerns\ManagesLivewireImageUploads;
use App\Models\AccommodationType;
use App\Services\ImageUploadService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin', ['title' => 'ویرایش اقامتگاه', 'pageTitle' => 'ویرایش اقامتگاه'])]
class AccommodationEdit extends Component
{
    use WithFileUploads;
    use ManagesAccommodationImageGallery;
    use ManagesAccommodationContactInfo;
    use ManagesAccommodationCatalog;
    use ManagesLivewireImageUploads;

    public Accommodation $accommodation;

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
    public array  $newImages       = [];

    public function mount(Accommodation $accommodation): void
    {
        $this->accommodation  = $accommodation;
        $this->cityId         = $accommodation->city_id;
        $this->provinceId     = $accommodation->city?->province_id ?? 0;
        $this->countyId       = $accommodation->county_id ?? 0;
        $this->hostId         = $accommodation->host_id;
        $this->name           = $accommodation->name;
        $this->description    = $accommodation->description ?? '';
        $this->type           = $accommodation->type;
        $this->pricePerNight  = $accommodation->price_per_night ?? 0;
        $this->capacity       = $accommodation->capacity ?? 1;
        $this->rooms          = $accommodation->rooms ?? 1;
        $this->childrenUnder6AllocateBed = $accommodation->childrenUnder6AllocateBed();
        $this->childrenUnder6DiscountPercentage = $accommodation->childrenUnder6DiscountPercentage();
        $this->address        = $accommodation->address ?? '';
        $this->lat            = $accommodation->lat !== null ? (string) $accommodation->lat : '';
        $this->lng            = $accommodation->lng !== null ? (string) $accommodation->lng : '';
        $this->isActive       = (bool) $accommodation->is_active;
        $this->amenitiesRaw   = implode(', ', $accommodation->amenities ?? []);
        $this->loadImageGalleryFrom($accommodation);
        $this->loadContactInfoFrom($accommodation);
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
        ], $this->imageUploadRules('newImages'), $this->contactInfoRules());
    }

    private function parseAmenities(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function update(): void
    {
        $this->validate();
        $this->validateContactInfo();

        // Delete removed images
        $existingImages = $this->accommodation->images ?? [];
        foreach ($existingImages as $img) {
            if (!in_array($img, $this->keepImages)) {
                Storage::disk('public')->delete($img);
            }
        }

        $finalImages = array_values(array_intersect($existingImages, $this->keepImages));

        try {
            ImageUploadService::assertTotalImageCount(
                count($finalImages) + count($this->newImages)
            );
        } catch (\RuntimeException $e) {
            $this->addError('newImages', $e->getMessage());

            return;
        }

        if (!empty($this->newImages)) {
            try {
                $finalImages = array_merge(
                    $finalImages,
                    app(ImageUploadService::class)->storeManyWebp($this->newImages, 'accommodations')
                );
            } catch (\RuntimeException $e) {
                $this->addError('newImages', $e->getMessage());
                return;
            }
        }

        $this->accommodation->update(array_merge([
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
            'image'           => $this->resolvedFeaturedImage($finalImages),
            'images'          => $finalImages,
        ], $this->contactInfoAttributes()));

        if ($this->hostId) {
            $this->accommodation->grantHostAccess(User::find($this->hostId));
        }

        session()->flash('status', 'اقامتگاه ویرایش شد.');
        $this->redirectRoute('admin.accommodations.index', navigate: true);
    }

    public function render()
    {
        $provinces     = Province::orderBy('name')->get();
        $cities        = $this->provinceId ? \App\Models\City::where('province_id', $this->provinceId)->orderBy('name')->get() : collect();
        $counties      = $this->provinceId ? County::where('province_id', $this->provinceId)->orderBy('name')->get() : collect();
        $hosts         = User::role('host')->orderBy('name')->get();
        $accommodation = $this->accommodation;
        $keepImages    = $this->keepImages;
        $accommodationTypes = AccommodationType::options();
        return view('admin.accommodations.edit', compact('accommodation', 'provinces', 'cities', 'counties', 'hosts', 'keepImages', 'accommodationTypes'));
    }
}

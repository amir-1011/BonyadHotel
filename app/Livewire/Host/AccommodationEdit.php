<?php

namespace App\Livewire\Host;

use App\Models\Accommodation;
use App\Models\City;
use App\Models\Province;
use App\Livewire\Concerns\ManagesAccommodationContactInfo;
use App\Livewire\Concerns\ManagesAccommodationCatalog;
use App\Models\AccommodationType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.host', ['title' => 'ویرایش اقامتگاه', 'pageTitle' => 'ویرایش اقامتگاه'])]
class AccommodationEdit extends Component
{
    use WithFileUploads;
    use ManagesAccommodationContactInfo;
    use ManagesAccommodationCatalog;

    public Accommodation $accommodation;

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
    public array  $keepImages      = [];
    public array  $newImages       = [];

    public function mount(Accommodation $accommodation): void
    {
        abort_if(! $accommodation->isManagedBy(Auth::user()), 403);

        $this->accommodation = $accommodation;
        $this->cityId        = $accommodation->city_id;
        $this->provinceId    = $accommodation->city?->province_id ?? 0;
        $this->name          = $accommodation->name;
        $this->description   = $accommodation->description ?? '';
        $this->type          = $accommodation->type;
        $this->pricePerNight = $accommodation->price_per_night ?? 0;
        $this->capacity      = $accommodation->capacity ?? 1;
        $this->rooms         = $accommodation->rooms ?? 1;
        $this->address       = $accommodation->address ?? '';
        $this->lat           = $accommodation->lat !== null ? (string) $accommodation->lat : '';
        $this->lng           = $accommodation->lng !== null ? (string) $accommodation->lng : '';
        $this->amenitiesRaw  = implode(', ', $accommodation->amenities ?? []);
        $this->keepImages    = $accommodation->images ?? [];
        $this->loadContactInfoFrom($accommodation);
    }

    public function updatedProvinceId(): void
    {
        $this->cityId = 0;
    }

    protected function rules(): array
    {
        return array_merge([
            'cityId'        => ['required', 'exists:cities,id'],
            'name'          => ['required', 'string', 'max:200'],
            'description'   => ['nullable', 'string'],
            'type'          => $this->accommodationTypeRule(),
            'pricePerNight' => ['required', 'integer', 'min:0'],
            'capacity'      => ['required', 'integer', 'min:1'],
            'rooms'         => ['required', 'integer', 'min:1'],
            'address'       => ['nullable', 'string'],
            'lat'           => ['nullable', 'numeric'],
            'lng'           => ['nullable', 'numeric'],
            'newImages.*'   => ['nullable', 'image', 'max:4096'],
        ], $this->contactInfoRules());
    }

    private function parseAmenities(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function removeExistingImage(string $path): void
    {
        $this->keepImages = array_values(array_filter(
            $this->keepImages, fn($img) => $img !== $path
        ));
    }

    public function update(): void
    {
        $this->validate();
        $this->validateContactInfo();

        $existingImages = $this->accommodation->images ?? [];
        foreach ($existingImages as $img) {
            if (!in_array($img, $this->keepImages)) {
                Storage::disk('public')->delete($img);
            }
        }
        $finalImages = array_values(array_intersect($existingImages, $this->keepImages));

        foreach ($this->newImages as $img) {
            $finalImages[] = $img->store('accommodations', 'public');
        }

        $this->accommodation->update(array_merge([
            'city_id'         => $this->cityId,
            'name'            => $this->name,
            'description'     => $this->description ?: null,
            'type'            => $this->type,
            'price_per_night' => $this->pricePerNight,
            'capacity'        => $this->capacity,
            'rooms'           => $this->rooms,
            'address'         => $this->address ?: null,
            'lat'             => $this->lat !== '' ? (float) $this->lat : null,
            'lng'             => $this->lng !== '' ? (float) $this->lng : null,
            'amenities'       => $this->parseAmenities($this->amenitiesRaw),
            'images'          => $finalImages,
        ], $this->contactInfoAttributes()));

        session()->flash('status', 'اقامتگاه با موفقیت ویرایش شد.');
        $this->redirectRoute('host.accommodations.index', navigate: true);
    }

    public function render()
    {
        $provinces     = Province::orderBy('name')->get();
        $cities        = $this->provinceId
            ? City::where('province_id', $this->provinceId)->orderBy('name')->get()
            : City::where('province_id', $this->accommodation->city?->province_id ?? 0)->orderBy('name')->get();
        $accommodation = $this->accommodation;
        $keepImages    = $this->keepImages;
        $accommodationTypes = AccommodationType::options();
        return view('host.accommodations.edit', compact('accommodation', 'provinces', 'cities', 'keepImages', 'accommodationTypes'));
    }
}

<?php

namespace App\Livewire\Admin;

use App\Models\Accommodation;
use App\Models\Province;
use App\Models\User;
use App\Livewire\Concerns\ManagesAccommodationContactInfo;
use App\Livewire\Concerns\ManagesAccommodationCatalog;
use App\Models\AccommodationType;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin', ['title' => 'ویرایش اقامتگاه', 'pageTitle' => 'ویرایش اقامتگاه'])]
class AccommodationEdit extends Component
{
    use WithFileUploads;
    use ManagesAccommodationContactInfo;
    use ManagesAccommodationCatalog;

    public Accommodation $accommodation;

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
    public array  $keepImages      = [];
    public array  $newImages       = [];

    public function mount(Accommodation $accommodation): void
    {
        $this->accommodation  = $accommodation;
        $this->cityId         = $accommodation->city_id;
        $this->provinceId     = $accommodation->city?->province_id ?? 0;
        $this->hostId         = $accommodation->host_id;
        $this->name           = $accommodation->name;
        $this->description    = $accommodation->description ?? '';
        $this->type           = $accommodation->type;
        $this->pricePerNight  = $accommodation->price_per_night ?? 0;
        $this->capacity       = $accommodation->capacity ?? 1;
        $this->rooms          = $accommodation->rooms ?? 1;
        $this->address        = $accommodation->address ?? '';
        $this->lat            = $accommodation->lat !== null ? (string) $accommodation->lat : '';
        $this->lng            = $accommodation->lng !== null ? (string) $accommodation->lng : '';
        $this->isActive       = (bool) $accommodation->is_active;
        $this->amenitiesRaw   = implode(', ', $accommodation->amenities ?? []);
        $this->image          = $accommodation->image ?? '';
        $this->keepImages     = $accommodation->images ?? [];
        $this->loadContactInfoFrom($accommodation);
    }

    protected function rules(): array
    {
        return array_merge([
            'cityId'        => ['required', 'exists:cities,id'],
            'hostId'        => ['nullable', 'exists:users,id'],
            'name'          => ['required', 'string', 'max:200'],
            'description'   => ['nullable', 'string'],
            'type'          => $this->accommodationTypeRule(),
            'pricePerNight' => ['required', 'integer', 'min:0'],
            'capacity'      => ['required', 'integer', 'min:1'],
            'rooms'         => ['required', 'integer', 'min:1'],
            'address'       => ['nullable', 'string'],
            'lat'           => ['nullable', 'numeric'],
            'lng'           => ['nullable', 'numeric'],
            'isActive'      => ['boolean'],
            'newImages.*'   => ['nullable', 'image', 'max:4096'],
        ], $this->contactInfoRules());
    }

    private function parseAmenities(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function updatedProvinceId(): void
    {
        $this->cityId = 0;
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

        // Delete removed images
        $existingImages = $this->accommodation->images ?? [];
        foreach ($existingImages as $img) {
            if (!in_array($img, $this->keepImages)) {
                Storage::disk('public')->delete($img);
            }
        }

        $finalImages = array_values(array_intersect($existingImages, $this->keepImages));

        if (!empty($this->newImages)) {
            foreach ($this->newImages as $img) {
                $finalImages[] = $img->store('accommodations', 'public');
            }
        }

        $this->accommodation->update(array_merge([
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
        $hosts         = User::role('host')->orderBy('name')->get();
        $accommodation = $this->accommodation;
        $keepImages    = $this->keepImages;
        $accommodationTypes = AccommodationType::options();
        return view('admin.accommodations.edit', compact('accommodation', 'provinces', 'cities', 'hosts', 'keepImages', 'accommodationTypes'));
    }
}

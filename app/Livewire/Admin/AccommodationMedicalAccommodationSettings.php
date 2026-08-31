<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ManagesMedicalAccommodationSettings;
use App\Models\Accommodation;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'اسکان درمانی', 'pageTitle' => 'اسکان درمانی'])]
class AccommodationMedicalAccommodationSettings extends Component
{
    use ManagesMedicalAccommodationSettings;

    public function mount(Accommodation $accommodation): void
    {
        $this->bootMedicalAccommodationSettings($accommodation);
    }

    public function render()
    {
        return view('livewire.concerns.medical-accommodation-settings', [
            'backRoute' => route('admin.accommodations.index'),
            'panel'     => 'admin',
            'employers' => $this->employerOptions(),
            'provinces' => \App\Models\Province::query()->orderBy('name')->get(),
            'templates' => \App\Support\MedicalAccommodationTariffs::templateOptions(),
        ]);
    }
}

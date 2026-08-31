<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\ManagesMedicalAccommodationSettings;
use App\Models\Accommodation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'اسکان درمانی', 'pageTitle' => 'اسکان درمانی'])]
class AccommodationMedicalAccommodationSettings extends Component
{
    use ManagesMedicalAccommodationSettings;

    public function mount(Accommodation $accommodation): void
    {
        abort_unless($accommodation->isManagedBy(Auth::user()), 403);
        $this->bootMedicalAccommodationSettings($accommodation);
    }

    public function render()
    {
        return view('livewire.concerns.medical-accommodation-settings', [
            'backRoute' => route('host.accommodations.index'),
            'panel'     => 'host',
            'employers' => $this->employerOptions(),
            'provinces' => \App\Models\Province::query()->orderBy('name')->get(),
            'templates' => \App\Support\MedicalAccommodationTariffs::templateOptions(),
        ]);
    }
}

<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\ManagesVeteranPolicySettings;
use App\Models\Accommodation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'تعاریف اولیه', 'pageTitle' => 'تعاریف اولیه'])]
class AccommodationVeteranPolicySettings extends Component
{
    use ManagesVeteranPolicySettings;

    public function mount(Accommodation $accommodation): void
    {
        abort_unless($accommodation->isManagedBy(Auth::user()), 403);
        $this->bootVeteranPolicySettings($accommodation);
    }

    public function render()
    {
        return view('livewire.concerns.veteran-policy-settings', [
            'backRoute' => route('host.accommodations.index'),
            'panel'     => 'host',
        ]);
    }
}

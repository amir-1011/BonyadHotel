<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ManagesVeteranPolicySettings;
use App\Models\Accommodation;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'تنظیمات ایثارگری', 'pageTitle' => 'تنظیمات ایثارگری و خدمات'])]
class AccommodationVeteranPolicySettings extends Component
{
    use ManagesVeteranPolicySettings;

    public function mount(Accommodation $accommodation): void
    {
        $this->bootVeteranPolicySettings($accommodation);
    }

    public function render()
    {
        return view('livewire.concerns.veteran-policy-settings', [
            'backRoute' => route('admin.accommodations.index'),
            'panel'     => 'admin',
        ]);
    }
}

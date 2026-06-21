<?php

namespace App\Livewire\Admin;

use App\Models\Accommodation;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'رزرو دستی', 'pageTitle' => 'رزرو دستی اتاق'])]
class ManualBooking extends Component
{
    public Accommodation $accommodation;

    public function mount(Accommodation $accommodation): void
    {
        $this->accommodation = $accommodation;
    }

    public function render()
    {
        return view('admin.accommodations.manual-booking', [
            'accommodation' => $this->accommodation,
        ]);
    }
}

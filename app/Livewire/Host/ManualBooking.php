<?php

namespace App\Livewire\Host;

use App\Models\Accommodation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'رزرو دستی', 'pageTitle' => 'رزرو دستی اتاق'])]
class ManualBooking extends Component
{
    public Accommodation $accommodation;

    public function mount(Accommodation $accommodation): void
    {
        abort_if(! $accommodation->isManagedBy(Auth::user()), 403);
        $this->accommodation = $accommodation;
    }

    public function render()
    {
        return view('host.accommodations.manual-booking', [
            'accommodation' => $this->accommodation,
        ]);
    }
}

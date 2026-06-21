<?php

namespace App\Livewire\Host;

use App\Models\Accommodation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'اقامتگاه‌های من', 'pageTitle' => 'اقامتگاه‌های من'])]
class AccommodationIndex extends Component
{
    public function destroy(int $id): void
    {
        abort_unless(Auth::user()->managesAccommodation($id), 403);
        $accommodation = Accommodation::findOrFail($id);
        $accommodation->delete();
        session()->flash('status', 'اقامتگاه حذف شد.');
        $this->dispatch('toast', type: 'success', message: 'اقامتگاه حذف شد.');
    }

    public function render()
    {
        $accommodations = Auth::user()->accommodations()
            ->with('city')
            ->withCount('bookings')
            ->latest()
            ->get();

        return view('host.accommodations.index', compact('accommodations'));
    }
}

<?php

namespace App\Livewire\Host;

use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'جزئیات رزرو', 'pageTitle' => 'جزئیات رزرو'])]
class BookingShow extends Component
{
    public Booking $booking;

    public function mount(Booking $booking): void
    {
        // Ensure this booking belongs to host's accommodation
        abort_unless(
            Auth::user()->accommodations()->where('id', $booking->accommodation_id)->exists(),
            403
        );
        $this->booking = $booking;
        $this->booking->load('user', 'accommodation.city.province', 'roomType');
    }

    public function confirm(): void
    {
        $this->booking->update(['status' => 'confirmed']);
        session()->flash('status', 'رزرو تأیید شد.');
        $this->dispatch('toast', type: 'success', message: 'رزرو تأیید شد.');
    }

    public function cancel(): void
    {
        abort_if($this->booking->status !== 'confirmed' && $this->booking->status !== 'pending', 422);
        $this->booking->update(['status' => 'cancelled']);
        session()->flash('status', 'رزرو لغو شد.');
        $this->dispatch('toast', type: 'success', message: 'رزرو لغو شد.');
    }

    public function render()
    {
        $booking = $this->booking;
        return view('host.bookings.show', compact('booking'));
    }
}

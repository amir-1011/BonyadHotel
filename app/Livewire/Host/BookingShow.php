<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\ManagesBookingDetails;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'جزئیات رزرو', 'pageTitle' => 'جزئیات رزرو'])]
class BookingShow extends Component
{
    use ManagesBookingDetails;

    public Booking $booking;

    public function mount(Booking $booking): void
    {
        abort_unless(
            Auth::user()->managesAccommodation($booking->accommodation_id),
            403
        );
        $this->booking = $booking->load([
            'user', 'accommodation.city.province', 'roomType', 'roomRate',
            'services.serviceCatalog', 'guestDetails', 'createdBy',
            'bookingRooms.roomType', 'bookingRooms.roomRate',
        ]);
        $this->bootBookingDetails($booking);
    }

    public function confirm(): void
    {
        $this->booking->update(['status' => 'confirmed']);
        $this->dispatch('toast', type: 'success', message: 'رزرو تأیید شد.');
    }

    public function cancel(): void
    {
        abort_if($this->booking->status !== 'confirmed' && $this->booking->status !== 'pending', 422);
        $this->booking->update(['status' => 'cancelled']);
        $this->dispatch('toast', type: 'success', message: 'رزرو لغو شد.');
    }

    public function render()
    {
        return view('host.bookings.show', [
            'booking' => $this->booking,
            'panel'   => 'host',
        ]);
    }
}

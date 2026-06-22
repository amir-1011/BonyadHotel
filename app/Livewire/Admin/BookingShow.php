<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ManagesBookingDetails;
use App\Models\Booking;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'جزئیات رزرو', 'pageTitle' => 'جزئیات رزرو'])]
class BookingShow extends Component
{
    use ManagesBookingDetails;

    public Booking $booking;

    public function mount(Booking $booking): void
    {
        $this->booking = $booking->load([
            'user', 'accommodation.city.province', 'roomType', 'roomRate',
            'services.serviceCatalog', 'guestDetails', 'createdBy',
            'bookingRooms.roomType', 'bookingRooms.roomRate',
        ]);
        $this->bootBookingDetails($booking);
    }

    public function updateStatus(): void
    {
        $allowed = ['pending', 'confirmed', 'cancelled'];
        if (!in_array($this->selectedStatus, $allowed, true)) {
            return;
        }

        $this->booking->update(['status' => $this->selectedStatus]);
        $this->dispatch('toast', type: 'success', message: 'وضعیت رزرو به‌روز شد.');
    }

    public function render()
    {
        return view('admin.bookings.show', [
            'booking' => $this->booking,
            'panel'   => 'admin',
        ]);
    }
}

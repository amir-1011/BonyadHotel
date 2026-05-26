<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'جزئیات رزرو', 'pageTitle' => 'جزئیات رزرو'])]
class BookingShow extends Component
{
    public Booking $booking;
    public string $selectedStatus = '';

    public function mount(Booking $booking): void
    {
        $this->booking = $booking;
        $this->booking->load('user', 'accommodation.city.province');
        $this->selectedStatus = $booking->status;
    }

    public function updateStatus(): void
    {
        $allowed = ['pending', 'confirmed', 'cancelled'];
        if (!in_array($this->selectedStatus, $allowed, true)) return;

        $this->booking->update(['status' => $this->selectedStatus]);
        session()->flash('status', 'وضعیت رزرو به‌روز شد.');
        $this->dispatch('toast', type: 'success', message: 'وضعیت رزرو به‌روز شد.');
    }

    public function render()
    {
        $booking = $this->booking;
        return view('admin.bookings.show', compact('booking'));
    }
}

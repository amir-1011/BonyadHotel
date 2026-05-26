<?php

namespace App\Livewire\Host;

use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.host', ['title' => 'رزروها', 'pageTitle' => 'رزروها'])]
class BookingIndex extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $status = '';
    #[Url] public int $accommodationId = 0;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }
    public function updatedAccommodationId(): void { $this->resetPage(); }

    public function confirm(int $bookingId): void
    {
        $booking = Booking::whereHas(
            'accommodation', fn($q) => $q->where('host_id', Auth::id())
        )->findOrFail($bookingId);

        $booking->update(['status' => 'confirmed']);
        session()->flash('status', 'رزرو تأیید شد.');
        $this->dispatch('toast', type: 'success', message: 'رزرو تأیید شد.');
    }

    public function cancel(int $bookingId): void
    {
        $booking = Booking::whereHas(
            'accommodation', fn($q) => $q->where('host_id', Auth::id())
        )->findOrFail($bookingId);

        abort_if($booking->status !== 'confirmed' && $booking->status !== 'pending', 422);
        $booking->update(['status' => 'cancelled']);
        session()->flash('status', 'رزرو لغو شد.');
        $this->dispatch('toast', type: 'success', message: 'رزرو لغو شد.');
    }

    public function render()
    {
        $accommodationIds = Auth::user()->accommodations()->pluck('id');

        $query = Booking::whereIn('accommodation_id', $accommodationIds)
            ->with('user', 'accommodation');

        if ($this->search) {
            $s = $this->search;
            $query->where(fn($w) =>
                $w->where('tracking_code', 'like', "%$s%")
                    ->orWhereHas('user', fn($q) => $q->where('name', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%"))
            );
        }
        if ($this->status) {
            $query->where('status', $this->status);
        }
        if ($this->accommodationId) {
            $query->where('accommodation_id', $this->accommodationId);
        }

        $myAccommodations = Auth::user()->accommodations()->get(['id', 'name']);
        $bookings = $query->latest()->paginate(20);
        return view('host.bookings.index', compact('bookings', 'myAccommodations'));
    }
}

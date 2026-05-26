<?php

namespace App\Livewire\Host;

use App\Models\Booking;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'داشبورد میزبان', 'pageTitle' => 'داشبورد'])]
class Dashboard extends Component
{
    public function confirm(int $bookingId): void
    {
        $booking = Booking::findOrFail($bookingId);
        abort_unless(Auth::user()->accommodations()->pluck('id')->contains($booking->accommodation_id), 403);
        $booking->update(['status' => 'confirmed']);
        session()->flash('status', 'رزرو تأیید شد.');
        $this->dispatch('toast', type: 'success', message: 'رزرو تأیید شد.');
    }

    public function cancel(int $bookingId): void
    {
        $booking = Booking::findOrFail($bookingId);
        abort_unless(Auth::user()->accommodations()->pluck('id')->contains($booking->accommodation_id), 403);
        $booking->update(['status' => 'cancelled']);
        session()->flash('status', 'رزرو لغو شد.');
        $this->dispatch('toast', type: 'success', message: 'رزرو لغو شد.');
    }
    public function render()
    {
        $user             = Auth::user();
        $accommodationIds = $user->accommodations()->pluck('id');

        $stats = [
            'accommodations'  => $accommodationIds->count(),
            'active_acc'      => $user->accommodations()->where('is_active', true)->count(),
            'total_bookings'  => Booking::whereIn('accommodation_id', $accommodationIds)->count(),
            'confirmed'       => Booking::whereIn('accommodation_id', $accommodationIds)->where('status', 'confirmed')->count(),
            'pending'         => Booking::whereIn('accommodation_id', $accommodationIds)->where('status', 'pending')->count(),
            'revenue'         => Booking::whereIn('accommodation_id', $accommodationIds)->where('status', 'confirmed')->sum('total_price'),
            'pending_reviews' => Review::whereIn('accommodation_id', $accommodationIds)->whereNull('host_reply')->count(),
        ];

        $recentBookings   = Booking::whereIn('accommodation_id', $accommodationIds)
            ->with('user', 'accommodation')->latest()->limit(8)->get();

        $myAccommodations = $user->accommodations()
            ->withCount(['bookings' => fn($q) => $q->where('status', 'confirmed')])
            ->with('city')->get();

        return view('host.dashboard', compact('stats', 'recentBookings', 'myAccommodations'));
    }
}

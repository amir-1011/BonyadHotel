<?php

namespace App\Livewire\Host;

use App\Models\Booking;
use App\Services\HostDashboardDataService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'داشبورد میزبان', 'pageTitle' => 'داشبورد'])]
class Dashboard extends Component
{
    public function confirm(int $bookingId): void
    {
        $booking = Booking::findOrFail($bookingId);
        abort_unless(Auth::user()->managesAccommodation($booking->accommodation_id), 403);
        $booking->update(['status' => 'confirmed']);
        session()->flash('status', 'رزرو تأیید شد.');
        $this->dispatch('toast', type: 'success', message: 'رزرو تأیید شد.');
    }

    public function cancel(int $bookingId): void
    {
        $booking = Booking::findOrFail($bookingId);
        abort_unless(Auth::user()->managesAccommodation($booking->accommodation_id), 403);
        $booking->update(['status' => 'cancelled']);
        session()->flash('status', 'رزرو لغو شد.');
        $this->dispatch('toast', type: 'success', message: 'رزرو لغو شد.');
    }

    public function render()
    {
        $user = Auth::user();
        $data = app(HostDashboardDataService::class)->build($user);

        return view('host.dashboard', array_merge($data, ['hostUser' => $user]));
    }
}

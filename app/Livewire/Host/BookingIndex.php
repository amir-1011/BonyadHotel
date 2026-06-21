<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\ManagesBookingFilters;
use App\Models\Booking;
use App\Models\City;
use App\Support\AdminBookingFilter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'رزروها', 'pageTitle' => 'رزروها'])]
class BookingIndex extends Component
{
    use ManagesBookingFilters;

    public function mount(): void
    {
        $this->mountBookingFilters();
    }

    public function confirm(int $bookingId): void
    {
        $accommodationIds = Auth::user()->managedAccommodationIds();

        $booking = Booking::whereIn('accommodation_id', $accommodationIds)->findOrFail($bookingId);

        $booking->update(['status' => 'confirmed']);
        session()->flash('status', 'رزرو تأیید شد.');
        $this->dispatch('toast', type: 'success', message: 'رزرو تأیید شد.');
    }

    public function cancel(int $bookingId): void
    {
        $accommodationIds = Auth::user()->managedAccommodationIds();

        $booking = Booking::whereIn('accommodation_id', $accommodationIds)->findOrFail($bookingId);

        abort_if($booking->status !== 'confirmed' && $booking->status !== 'pending', 422);
        $booking->update(['status' => 'cancelled']);
        session()->flash('status', 'رزرو لغو شد.');
        $this->dispatch('toast', type: 'success', message: 'رزرو لغو شد.');
    }

    public function render()
    {
        $accommodationIds = Auth::user()->managedAccommodationIds()->all();
        $filter = AdminBookingFilter::make($this->bookingFilterParams(), $accommodationIds);

        $query = Booking::with('user', 'accommodation.city', 'roomType');
        $filter->apply($query);

        $totalFiltered = (clone $query)->sum('total_price');
        $countFiltered = (clone $query)->count();
        $bookings = $query->paginate(25);

        $accommodations = Auth::user()->managedAccommodationOptions();
        $cities = City::query()
            ->whereHas('accommodations', fn ($q) => $q->whereIn('id', $accommodationIds))
            ->orderBy('name')
            ->get(['id', 'name']);
        $hasActiveFilters = $filter->hasActiveFilters();
        $exportQuery = $filter->exportQuery();
        extract($this->resolvedBookingSort());

        return view('host.bookings.index', compact(
            'bookings', 'accommodations', 'cities',
            'totalFiltered', 'countFiltered', 'sort', 'dir',
            'hasActiveFilters', 'exportQuery',
        ));
    }
}

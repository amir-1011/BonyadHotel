<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ManagesBookingFilters;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\City;
use App\Support\AdminBookingFilter;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'مدیریت رزروها', 'pageTitle' => 'رزروها'])]
class BookingIndex extends Component
{
    use ManagesBookingFilters;

    public function mount(): void
    {
        $this->mountBookingFilters();
    }

    public function updateStatus(int $bookingId, string $newStatus): void
    {
        $allowed = ['pending', 'confirmed', 'cancelled'];
        if (!in_array($newStatus, $allowed, true)) {
            return;
        }

        Booking::findOrFail($bookingId)->update(['status' => $newStatus]);
        session()->flash('status', 'وضعیت رزرو به‌روز شد.');
        $this->dispatch('toast', type: 'success', message: 'وضعیت رزرو به‌روز شد.');
    }

    public function render()
    {
        $filter = AdminBookingFilter::make($this->bookingFilterParams());

        $query = Booking::with('user', 'accommodation.city', 'roomType');
        $filter->apply($query);

        $totalFiltered = (clone $query)->sum('total_price');
        $countFiltered = (clone $query)->count();
        $bookings = $query->paginate(25);

        $accommodations = Accommodation::orderBy('name')->get(['id', 'name']);
        $cities = City::orderBy('name')->get(['id', 'name']);
        $hasActiveFilters = $filter->hasActiveFilters();
        $exportQuery = $filter->exportQuery();
        extract($this->resolvedBookingSort());

        return view('admin.bookings.index', compact(
            'bookings', 'accommodations', 'cities',
            'totalFiltered', 'countFiltered', 'sort', 'dir',
            'hasActiveFilters', 'exportQuery',
        ));
    }
}

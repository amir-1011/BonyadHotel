<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ManagesBookingFilters;
use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\ProgramEmployer;
use App\Support\AdminBookingFilter;
use App\Support\BookingLocationFilterCatalog;
use App\Support\BookingReserverFilterCatalog;
use App\Support\BookingRoomFilterCatalog;
use App\Support\BookingServiceFilterCatalog;
use App\Support\VeteranGroups;
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

        $booking = Booking::findOrFail($bookingId);

        if ($newStatus === 'cancelled' && $booking->status === 'confirmed' && $booking->canRequestCancellation()) {
            $this->redirect(route('admin.bookings.show', $booking) . '?cancel=1');
            return;
        }

        $booking->update(['status' => $newStatus]);
        session()->flash('status', 'وضعیت رزرو به‌روز شد.');
        $this->dispatch('toast', type: 'success', message: 'وضعیت رزرو به‌روز شد.');
    }

    public function render()
    {
        $filter = AdminBookingFilter::make($this->bookingFilterParams());

        $query = Booking::with('user', 'createdBy', 'accommodation.city', 'roomType', 'bookingRooms.room', 'bookingRooms.roomType', 'program');
        $filter->apply($query);

        $totalFiltered = (clone $query)->sum('total_price');
        $countFiltered = (clone $query)->count();
        $bookings = $query->paginate(25);

        $locationCatalog = app(BookingLocationFilterCatalog::class);
        $provinces = $locationCatalog->provinces();
        $cities = $locationCatalog->cities($this->draftProvinceId);
        $counties = $locationCatalog->counties($this->draftProvinceId);

        $serviceCatalog = app(BookingServiceFilterCatalog::class);
        $serviceCatalogs = $serviceCatalog->parentServices(
            $this->draftAccommodationId,
            $this->draftProvinceId,
            $this->draftCityId,
            $this->draftCountyId,
        );
        $serviceVariants = $serviceCatalog->variants($this->draftServiceCatalogId);
        $showServiceAccommodation = $serviceCatalog->shouldShowAccommodationInLabels($this->draftAccommodationId);

        $roomCatalog = app(BookingRoomFilterCatalog::class);
        $roomCategories = $roomCatalog->categories(
            $this->draftAccommodationId,
            $this->draftProvinceId,
            $this->draftCityId,
            $this->draftCountyId,
        );
        $rooms = $roomCatalog->rooms(
            $this->draftAccommodationId,
            $this->draftRoomCategory,
            $this->draftProvinceId,
            $this->draftCityId,
            $this->draftCountyId,
        );
        $showRoomAccommodation = $roomCatalog->shouldShowAccommodationInLabels($this->draftAccommodationId);
        $veteranAccommodationId = $this->draftAccommodationId !== '' ? (int) $this->draftAccommodationId : null;
        $veteranOptions = VeteranGroups::options($veteranAccommodationId);

        $accommodations = Accommodation::orderBy('name')->get(['id', 'name']);
        $employers = ProgramEmployer::orderBy('name')->get();
        $reservers = app(BookingReserverFilterCatalog::class)->reservers($this->draftAccommodationId);
        $hasActiveFilters = $filter->hasActiveFilters();
        $exportQuery = $filter->exportQuery();
        extract($this->resolvedBookingSort());

        return view('admin.bookings.index', compact(
            'bookings', 'accommodations', 'employers', 'reservers', 'provinces', 'cities', 'counties',
            'serviceCatalogs', 'serviceVariants', 'showServiceAccommodation',
            'roomCategories', 'rooms', 'showRoomAccommodation', 'veteranOptions',
            'totalFiltered', 'countFiltered', 'sort', 'dir',
            'hasActiveFilters', 'exportQuery',
        ));
    }
}

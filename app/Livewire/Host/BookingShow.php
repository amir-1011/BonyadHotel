<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\AssertsHostPermissions;
use App\Livewire\Concerns\ManagesBookingDetails;
use App\Livewire\Concerns\ManagesCancellationRequests;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'جزئیات رزرو', 'pageTitle' => 'جزئیات رزرو'])]
class BookingShow extends Component
{
    use ManagesBookingDetails;
    use ManagesCancellationRequests;
    use AssertsHostPermissions;

    public Booking $booking;

    public function mount(Booking $booking): void
    {
        abort_unless(
            Auth::user()->managesAccommodation($booking->accommodation_id),
            403
        );
        $this->booking = $booking->load([
            'user.country', 'user.residenceCity', 'accommodation.city.province', 'roomType', 'roomRate',
            'services.serviceCatalog', 'services.serviceCatalogVariant',
            'guestDetails.country', 'guestDetails.residenceCity',
            'guestDetails.bookingRoom.room', 'guestDetails.bookingRoom.roomType',
            'createdBy',
            'bookingRooms.roomType', 'bookingRooms.roomRate', 'bookingRooms.room',
            'beneficiaryCosts.beneficiary.user', 'beneficiaryCosts.user',
        ]);
        $this->bootBookingDetails($booking);
        $this->initCancellationRequestsData();
        $this->maybeAutoOpenCancellationRequestModal();
    }

    #[On('booking-services-updated')]
    public function refreshBookingDetails(): void
    {
        $this->booking->refresh()->load([
            'user.country', 'user.residenceCity', 'accommodation.city.province', 'roomType', 'roomRate',
            'services.serviceCatalog', 'services.serviceCatalogVariant',
            'guestDetails.country', 'guestDetails.residenceCity',
            'guestDetails.bookingRoom.room', 'guestDetails.bookingRoom.roomType',
            'createdBy',
            'bookingRooms.roomType', 'bookingRooms.roomRate', 'bookingRooms.room',
            'beneficiaryCosts.beneficiary.user', 'beneficiaryCosts.user',
        ]);
        $this->loadEditableGuests();
    }

    public function confirm(): void
    {
        $this->assertHostCan('bookings.show', 'edit');
        abort_unless($this->booking->canEditBookingDetails(Auth::user()), 403, 'پس از پایان دوره رزرو امکان ویرایش وجود ندارد.');
        $this->booking->update(['status' => 'confirmed']);
        $this->dispatch('toast', type: 'success', message: 'رزرو تأیید شد.');
    }

    public function cancel(): void
    {
        $this->assertHostCan('bookings.show', 'edit');
        abort_unless($this->booking->canEditBookingDetails(Auth::user()), 403, 'پس از پایان دوره رزرو امکان ویرایش وجود ندارد.');
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

<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ManagesBookingDetails;
use App\Livewire\Concerns\ManagesCancellationRequests;
use App\Models\Booking;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'جزئیات رزرو', 'pageTitle' => 'جزئیات رزرو'])]
class BookingShow extends Component
{
    use ManagesBookingDetails;
    use ManagesCancellationRequests;

    public Booking $booking;

    public function mount(Booking $booking): void
    {
        $this->booking = $booking->load([
            'user.country', 'user.residenceCity', 'accommodation.city.province', 'roomType', 'roomRate',
            'services.serviceCatalog', 'services.serviceCatalogVariant',
            'guestDetails.country', 'guestDetails.residenceCity',
            'guestDetails.bookingRoom.room', 'guestDetails.bookingRoom.roomType',
            'createdBy',
            'bookingRooms.roomType', 'bookingRooms.roomRate', 'bookingRooms.room',
            'beneficiaryCosts.beneficiary.user', 'beneficiaryCosts.user',
            'employer', 'medicalTariff', 'medicalContract', 'accommodation.medicalAccommodationSetting',
            'paymentRecords.posTerminal', 'paymentRecords.recordedBy',
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
            'employer', 'medicalTariff', 'medicalContract', 'accommodation.medicalAccommodationSetting',
            'paymentRecords.posTerminal', 'paymentRecords.recordedBy',
        ]);
        $this->loadEditableGuests();
    }

    public function updateStatus(): void
    {
        $allowed = ['pending', 'confirmed', 'cancelled'];
        if (!in_array($this->selectedStatus, $allowed, true)) {
            return;
        }

        if (
            $this->selectedStatus === 'cancelled'
            && $this->booking->status !== 'cancelled'
            && $this->booking->status === 'confirmed'
        ) {
            $this->selectedStatus = $this->booking->status;
            $this->openCancellationRequestModal();
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

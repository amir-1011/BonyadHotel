<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesBookingDetails;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class BookingServicesEditor extends Component
{
    use ManagesBookingDetails;

    public Booking $booking;

    public string $panel = 'host';

    public ?int $guestSortOrder = null;

    public function mount(int $bookingId, string $panel = 'host', ?int $guestSortOrder = null): void
    {
        $this->panel = $panel;
        $this->guestSortOrder = $guestSortOrder;
        $this->booking = Booking::query()
            ->with(['services.serviceCatalog', 'services.serviceCatalogVariant', 'accommodation'])
            ->findOrFail($bookingId);

        if ($this->panel === 'host') {
            abort_unless(Auth::user()?->managesAccommodation($this->booking->accommodation_id), 403);
        }

        abort_unless($this->booking->canEditBookingDetails(Auth::user()), 422);
        $this->bootBookingDetails($this->booking);
    }

    public function render()
    {
        return view('livewire.booking-services-editor');
    }
}

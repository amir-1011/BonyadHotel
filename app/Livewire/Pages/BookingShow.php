<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\ManagesCancellationRequests;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'جزئیات رزرو'])]
class BookingShow extends Component
{
    use ManagesCancellationRequests;

    public Booking $booking;

    // Review form
    public int $rating    = 5;
    public string $comment = '';

    public function mount(Booking $booking): void
    {
        abort_if($booking->user_id !== Auth::id(), 403);
        $this->booking = $booking;
        $this->booking->load([
            'accommodation.city.province',
            'user',
            'roomType',
            'roomRate',
            'bookingRooms.roomType',
            'bookingRooms.roomRate',
            'bookingRooms.room',
            'employer',
            'medicalTariff',
            'medicalContract',
            'accommodation.medicalAccommodationSetting',
        ]);
        $this->initCancellationRequestsData();
        $this->maybeAutoOpenCancellationRequestModal();
    }

    public function submitReview(): void
    {
        $this->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $canReview = $this->booking->status === 'confirmed'
            && $this->booking->check_out < now()->toDateString();

        if (!$canReview) {
            $this->addError('rating', 'فقط مهمانانی که اقامت خود را تکمیل کرده‌اند می‌توانند نظر ثبت کنند.');
            return;
        }

        Review::updateOrCreate(
            ['user_id' => Auth::id(), 'accommodation_id' => $this->booking->accommodation_id],
            ['rating' => $this->rating, 'comment' => $this->comment, 'is_visible' => true, 'booking_id' => $this->booking->id]
        );

        $this->rating  = 5;
        $this->comment = '';
        session()->flash('status', 'نظر شما با موفقیت ثبت شد.');
        $this->dispatch('toast', type: 'success', message: 'نظر شما با موفقیت ثبت شد.');
    }

    public function render()
    {
        $booking = $this->booking;

        $canReview = $booking->status === 'confirmed'
            && $booking->check_out < now()->toDateString();

        $userReview = $canReview
            ? Review::where('user_id', Auth::id())
                ->where('accommodation_id', $booking->accommodation_id)
                ->first()
            : null;

        return view('bookings.show', compact('booking', 'canReview', 'userReview'));
    }
}

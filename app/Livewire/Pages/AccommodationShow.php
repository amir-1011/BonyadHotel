<?php

namespace App\Livewire\Pages;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class AccommodationShow extends Component
{
    public Accommodation $accommodation;

    // Review form
    public int $rating    = 5;
    public string $comment = '';

    public function mount(Accommodation $accommodation): void
    {
        $this->accommodation = $accommodation;
        $this->accommodation->load('city.province');
    }

    public function toggleFavorite(): void
    {
        if (!Auth::check()) {
            $this->redirectRoute('auth.mobile', navigate: true);
            return;
        }
        $user = Auth::user();
        if ($user->favorites()->where('accommodation_id', $this->accommodation->id)->exists()) {
            $user->favorites()->detach($this->accommodation->id);
        } else {
            $user->favorites()->attach($this->accommodation->id);
        }
    }

    public function submitReview(): void
    {
        if (!Auth::check()) {
            $this->redirectRoute('auth.mobile', navigate: true);
            return;
        }

        $this->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $hasBooking = Booking::where('user_id', Auth::id())
            ->where('accommodation_id', $this->accommodation->id)
            ->where('status', 'confirmed')
            ->where('check_out', '<', now()->toDateString())
            ->exists();

        if (!$hasBooking) {
            $this->addError('rating', 'فقط مهمانانی که اقامت خود را تکمیل کرده‌اند می‌توانند نظر ثبت کنند.');
            return;
        }

        Review::updateOrCreate(
            ['user_id' => Auth::id(), 'accommodation_id' => $this->accommodation->id],
            ['rating' => $this->rating, 'comment' => $this->comment, 'is_visible' => true]
        );

        $this->rating  = 5;
        $this->comment = '';
        session()->flash('status', 'نظر شما با موفقیت ثبت شد.');
        $this->dispatch('toast', type: 'success', message: 'نظر شما با موفقیت ثبت شد.');
    }

    public function render()
    {
        $reviews = $this->accommodation->reviews()->where('is_visible', true)
            ->with('user')->latest()->get();

        $roomTypes = $this->accommodation->roomTypes()
            ->where('is_active', true)
            ->with(['rates' => fn($q) => $q->where('is_active', true)->orderBy('price_per_night')])
            ->get();

        $canReview  = false;
        $userReview = null;

        if (Auth::check()) {
            $canReview = Booking::where('user_id', Auth::id())
                ->where('accommodation_id', $this->accommodation->id)
                ->where('status', 'confirmed')
                ->where('check_out', '<', now()->toDateString())
                ->exists();

            $userReview = $this->accommodation->reviews()
                ->where('user_id', Auth::id())->first();
        }

        $isFavorited = Auth::check() &&
            Auth::user()->favorites()->where('accommodation_id', $this->accommodation->id)->exists();

        $title = $this->accommodation->name . ' | ایثار';

        return view('accommodations.show', compact(
            'reviews', 'roomTypes', 'canReview', 'userReview', 'isFavorited', 'title'
        ))->title($title);
    }
}

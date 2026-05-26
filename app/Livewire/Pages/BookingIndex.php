<?php

namespace App\Livewire\Pages;

use App\Models\Review;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'رزروهای من'])]
class BookingIndex extends Component
{
    use WithPagination;

    public function render()
    {
        $bookings = Auth::user()->bookings()
            ->with('accommodation.city.province')
            ->latest()
            ->paginate(10);

        $reviewedAccIds = Review::where('user_id', Auth::id())
            ->pluck('accommodation_id')
            ->flip();

        return view('bookings.index', compact('bookings', 'reviewedAccIds'));
    }
}

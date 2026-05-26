<?php

namespace App\Livewire\Pages;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'علاقه‌مندی‌ها'])]
class FavoriteIndex extends Component
{
    public function toggleFavorite(int $accommodationId): void
    {
        $user = Auth::user();
        if ($user->favorites()->where('accommodation_id', $accommodationId)->exists()) {
            $user->favorites()->detach($accommodationId);
        } else {
            $user->favorites()->attach($accommodationId);
        }
    }

    public function render()
    {
        $favorites = Auth::user()
            ->favorites()
            ->with(['city.province'])
            ->where('is_active', true)
            ->latest('user_favorites.created_at')
            ->get();

        return view('favorites.index', compact('favorites'));
    }
}

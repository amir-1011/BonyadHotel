<?php

namespace App\Http\Controllers;

use App\Models\Accommodation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    /**
     * Show the user's favorite accommodations.
     */
    public function index()
    {
        $favorites = Auth::user()
            ->favorites()
            ->with(['city.province'])
            ->where('is_active', true)
            ->latest('user_favorites.created_at')
            ->get();

        return view('favorites.index', compact('favorites'));
    }

    /**
     * Toggle favorite status for an accommodation.
     * Returns JSON: { favorited: bool }
     */
    public function toggle(Accommodation $accommodation)
    {
        $user = Auth::user();

        if ($user->favorites()->where('accommodation_id', $accommodation->id)->exists()) {
            $user->favorites()->detach($accommodation->id);
            $favorited = false;
        } else {
            $user->favorites()->attach($accommodation->id);
            $favorited = true;
        }

        return response()->json(['favorited' => $favorited]);
    }
}

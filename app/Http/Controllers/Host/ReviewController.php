<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $accIds = Auth::user()->accommodations()->pluck('id');

        $query = Review::whereIn('accommodation_id', $accIds)
            ->with('user', 'accommodation', 'booking')
            ->latest();

        if ($request->filled('accommodation_id') && $accIds->contains($request->accommodation_id)) {
            $query->where('accommodation_id', $request->accommodation_id);
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        if ($request->filled('replied')) {
            $query->when($request->replied === '1', fn($q) => $q->whereNotNull('host_reply'))
                  ->when($request->replied === '0', fn($q) => $q->whereNull('host_reply'));
        }

        $reviews = $query->paginate(20)->withQueryString();
        $myAccommodations = Auth::user()->accommodations()->orderBy('name')->get(['id', 'name']);

        $stats = [
            'total'    => Review::whereIn('accommodation_id', $accIds)->count(),
            'replied'  => Review::whereIn('accommodation_id', $accIds)->whereNotNull('host_reply')->count(),
            'pending'  => Review::whereIn('accommodation_id', $accIds)->whereNull('host_reply')->count(),
            'avg'      => Review::whereIn('accommodation_id', $accIds)->avg('rating'),
        ];

        return view('host.reviews.index', compact('reviews', 'myAccommodations', 'stats'));
    }

    public function reply(Request $request, Review $review)
    {
        // Ensure review belongs to host's accommodation
        $accIds = Auth::user()->accommodations()->pluck('id');
        abort_if(!$accIds->contains($review->accommodation_id), 403);

        $request->validate([
            'host_reply' => ['required', 'string', 'max:1000'],
        ]);

        $review->update([
            'host_reply'       => $request->host_reply,
            'host_replied_at'  => now(),
        ]);

        return back()->with('status', 'پاسخ شما با موفقیت ثبت شد.');
    }

    public function deleteReply(Review $review)
    {
        $accIds = Auth::user()->accommodations()->pluck('id');
        abort_if(!$accIds->contains($review->accommodation_id), 403);

        $review->update(['host_reply' => null, 'host_replied_at' => null]);

        return back()->with('status', 'پاسخ حذف شد.');
    }
}

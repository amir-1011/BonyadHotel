<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = Review::with('user', 'accommodation', 'booking')->latest();

        if ($request->filled('visible')) {
            $query->where('is_visible', $request->visible === '1');
        }

        $reviews = $query->paginate(25)->withQueryString();
        return view('admin.reviews.index', compact('reviews'));
    }

    public function toggle(Review $review)
    {
        $review->update(['is_visible' => !$review->is_visible]);
        return back()->with('status', $review->is_visible ? 'نظر نمایش داده شد.' : 'نظر مخفی شد.');
    }

    public function destroy(Review $review)
    {
        $review->delete();
        return back()->with('status', 'نظر حذف شد.');
    }
}

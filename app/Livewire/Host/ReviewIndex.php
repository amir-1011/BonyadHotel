<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\AssertsHostPermissions;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.host', ['title' => 'نظرات مهمانان', 'pageTitle' => 'نظرات مهمانان'])]
class ReviewIndex extends Component
{
    use WithPagination;
    use AssertsHostPermissions;

    #[Url] public string $search = '';
    #[Url] public int    $accommodationId = 0;
    #[Url] public int    $rating = 0;
    #[Url] public string $replied = '';

    // Reply form state: reviewId → reply text
    public ?int   $replyingTo = null;
    public string $replyText  = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedAccommodationId(): void { $this->resetPage(); }
    public function updatedRating(): void { $this->resetPage(); }
    public function updatedReplied(): void { $this->resetPage(); }

    public function startReply(int $reviewId): void
    {
        $this->assertHostCan('reviews.list', 'edit');
        $this->replyingTo = $reviewId;
        $review = Review::findOrFail($reviewId);
        $this->replyText = $review->host_reply ?? '';
    }

    public function submitReply(): void
    {
        $this->assertHostCan('reviews.list', 'edit');
        $this->validate([
            'replyText' => ['required', 'string', 'max:1000'],
        ]);

        $ids    = Auth::user()->managedAccommodationIds();
        $review = Review::whereIn('accommodation_id', $ids)->findOrFail($this->replyingTo);
        $review->update(['host_reply' => $this->replyText, 'host_replied_at' => now()]);

        $this->replyingTo = null;
        $this->replyText  = '';
        session()->flash('status', 'پاسخ با موفقیت ثبت شد.');
        $this->dispatch('toast', type: 'success', message: 'پاسخ با موفقیت ثبت شد.');
    }

    public function deleteReply(int $reviewId): void
    {
        $this->assertHostCan('reviews.list', 'delete');
        $ids    = Auth::user()->managedAccommodationIds();
        $review = Review::whereIn('accommodation_id', $ids)->findOrFail($reviewId);
        $review->update(['host_reply' => null, 'host_replied_at' => null]);
        session()->flash('status', 'پاسخ حذف شد.');
        $this->dispatch('toast', type: 'success', message: 'پاسخ حذف شد.');
    }

    public function render()
    {
        $ids   = Auth::user()->managedAccommodationIds();
        $query = Review::whereIn('accommodation_id', $ids)->with('user', 'accommodation');

        if ($this->search) {
            $s = $this->search;
            $query->where(fn($w) =>
                $w->whereHas('user', fn($q) => $q->where('name', 'like', "%$s%"))
                    ->orWhere('comment', 'like', "%$s%")
            );
        }
        if ($this->accommodationId) {
            $query->where('accommodation_id', $this->accommodationId);
        }
        if ($this->rating) {
            $query->where('rating', $this->rating);
        }
        if ($this->replied === '0') {
            $query->whereNull('host_reply');
        } elseif ($this->replied === '1') {
            $query->whereNotNull('host_reply');
        }

        $reviews     = $query->latest()->paginate(20);
        $replyingTo  = $this->replyingTo;
        $replyText   = $this->replyText;

        $allReviews  = Review::whereIn('accommodation_id', $ids);
        $stats = [
            'total'   => $allReviews->count(),
            'pending' => (clone $allReviews)->whereNull('host_reply')->count(),
            'replied' => (clone $allReviews)->whereNotNull('host_reply')->count(),
            'avg'     => $allReviews->avg('rating'),
        ];

        $myAccommodations = Auth::user()->managedAccommodationOptions();
        return view('host.reviews.index', compact('reviews', 'replyingTo', 'replyText', 'stats', 'myAccommodations'));
    }
}

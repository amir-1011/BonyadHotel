<?php

namespace App\Livewire\Admin;

use App\Models\Review;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin', ['title' => 'مدیریت نظرات', 'pageTitle' => 'نظرات'])]
class ReviewIndex extends Component
{
    use WithPagination;

    #[Url] public string $search   = '';
    #[Url] public string $visible  = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedVisible(): void { $this->resetPage(); }

    public function toggle(int $reviewId): void
    {
        $review = Review::findOrFail($reviewId);
        $review->update(['is_visible' => !$review->is_visible]);
        session()->flash('status', 'وضعیت نظر تغییر کرد.');
        $this->dispatch('toast', type: 'success', message: 'وضعیت نظر تغییر کرد.');
    }

    public function destroy(int $reviewId): void
    {
        Review::findOrFail($reviewId)->delete();
        session()->flash('status', 'نظر حذف شد.');
        $this->dispatch('toast', type: 'success', message: 'نظر حذف شد.');
        $this->resetPage();
    }

    public function render()
    {
        $query = Review::with('user', 'accommodation');

        if ($this->search) {
            $s = $this->search;
            $query->where(fn($w) =>
                $w->whereHas('user', fn($q) => $q->where('name', 'like', "%$s%"))
                    ->orWhereHas('accommodation', fn($q) => $q->where('name', 'like', "%$s%"))
            );
        }
        if ($this->visible !== '') {
            $query->where('is_visible', (bool) $this->visible);
        }

        $reviews = $query->latest()->paginate(20);
        return view('admin.reviews.index', compact('reviews'));
    }
}

<?php

namespace App\Livewire\Admin;

use App\Models\Accommodation;
use App\Models\Program;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin', ['title' => 'برنامه‌ها و اردوها', 'pageTitle' => 'برنامه‌ها'])]
class ProgramIndex extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $status = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }

    public function updateStatus(int $programId, string $newStatus): void
    {
        $allowed = ['active', 'completed', 'cancelled'];
        if (!in_array($newStatus, $allowed, true)) return;

        Program::findOrFail($programId)->update(['status' => $newStatus]);
        session()->flash('status', 'وضعیت برنامه به‌روز شد.');
        $this->dispatch('toast', type: 'success', message: 'وضعیت برنامه به‌روز شد.');
    }

    public function render()
    {
        $query = Program::with('accommodation.city');

        if ($this->search) {
            $s = $this->search;
            $query->where(fn($w) =>
                $w->where('title', 'like', "%$s%")
                    ->orWhereHas('accommodation', fn($q) => $q->where('name', 'like', "%$s%"))
            );
        }
        if ($this->status) {
            $query->where('status', $this->status);
        }

        $programs       = $query->latest()->paginate(20);
        $accommodations = Accommodation::orderBy('name')->get(['id', 'name']);
        return view('admin.programs.index', compact('programs', 'accommodations'));
    }
}

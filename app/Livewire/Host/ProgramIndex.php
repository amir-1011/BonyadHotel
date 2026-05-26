<?php

namespace App\Livewire\Host;

use App\Models\Program;
use App\Models\RoomType;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.host', ['title' => 'برنامه‌ها و اردوها', 'pageTitle' => 'برنامه‌ها'])]
class ProgramIndex extends Component
{
    use WithPagination;

    #[Url] public string $status          = '';
    #[Url] public string $supportive      = '';
    #[Url] public int    $accommodationId = 0;

    public function updatedStatus(): void          { $this->resetPage(); }
    public function updatedSupportive(): void      { $this->resetPage(); }
    public function updatedAccommodationId(): void { $this->resetPage(); }

    public function destroy(int $id): void
    {
        $accIds = Auth::user()->accommodations()->pluck('id');
        $program = Program::whereIn('accommodation_id', $accIds)->findOrFail($id);
        $program->delete();
        session()->flash('status', 'برنامه حذف شد.');
        $this->dispatch('toast', type: 'success', message: 'برنامه حذف شد.');
        $this->resetPage();
    }

    public function render()
    {
        $accIds = Auth::user()->accommodations()->pluck('id');
        $query  = Program::whereIn('accommodation_id', $accIds)
            ->with('accommodation')->latest();

        if ($this->status) {
            $query->where('status', $this->status);
        }
        if ($this->supportive !== '') {
            $query->where('is_supportive_service', (bool) $this->supportive);
        }
        if ($this->accommodationId && $accIds->contains($this->accommodationId)) {
            $query->where('accommodation_id', $this->accommodationId);
        }

        $programs         = $query->paginate(20);
        $myAccommodations = Auth::user()->accommodations()->orderBy('name')->get(['id', 'name']);

        return view('host.programs.index', compact('programs', 'myAccommodations'));
    }
}

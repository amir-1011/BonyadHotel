<?php

namespace App\Livewire\Admin;

use App\Models\Accommodation;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin', ['title' => 'مدیریت اقامتگاه‌ها', 'pageTitle' => 'اقامتگاه‌ها'])]
class AccommodationIndex extends Component
{
    use WithPagination;

    #[Url] public string $search = '';

    public function updatedSearch(): void { $this->resetPage(); }

    public function toggleActive(int $id): void
    {
        $acc = Accommodation::findOrFail($id);
        $acc->update(['is_active' => !$acc->is_active]);
        session()->flash('status', 'وضعیت اقامتگاه تغییر کرد.');
        $this->dispatch('toast', type: 'success', message: 'وضعیت اقامتگاه تغییر کرد.');
    }

    public function destroy(int $id): void
    {
        Accommodation::findOrFail($id)->delete();
        session()->flash('status', 'اقامتگاه حذف شد.');
        $this->dispatch('toast', type: 'success', message: 'اقامتگاه حذف شد.');
        $this->resetPage();
    }

    public function render()
    {
        $query = Accommodation::with('city.province', 'host');

        if ($this->search) {
            $s = trim($this->search);
            $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhereHas('city', fn ($q) => $q->where('name', 'like', "%{$s}%"))
                ->orWhereHas('host', fn ($q) => $q->where('name', 'like', "%{$s}%"))
                ->orWhereHas('hosts', fn ($q) => $q->where('name', 'like', "%{$s}%"))
            );
        }

        $accommodations = $query->latest()->paginate(20);
        return view('admin.accommodations.index', compact('accommodations'));
    }
}

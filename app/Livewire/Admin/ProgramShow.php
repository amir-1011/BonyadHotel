<?php

namespace App\Livewire\Admin;

use App\Models\Program;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'جزئیات برنامه', 'pageTitle' => 'جزئیات برنامه'])]
class ProgramShow extends Component
{
    public Program $program;
    public string $newStatus = '';

    public function mount(Program $program): void
    {
        $this->program = $program;
        $this->program->load('accommodation.city', 'roomTypes');
        $this->newStatus = $program->status;
    }

    public function updateStatus(): void
    {
        $allowed = ['active', 'completed', 'cancelled'];
        if (!in_array($this->newStatus, $allowed, true)) return;

        $this->program->update(['status' => $this->newStatus]);
        session()->flash('status', 'وضعیت برنامه به‌روز شد.');
        $this->dispatch('toast', type: 'success', message: 'وضعیت برنامه به‌روز شد.');
    }

    public function render()
    {
        $program = $this->program;
        return view('admin.programs.show', compact('program'));
    }
}

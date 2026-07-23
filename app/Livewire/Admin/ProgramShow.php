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
        $this->program = $program->load([
            'accommodation.city',
            'booking.bookingRooms.room',
            'booking.bookingRooms.roomType',
            'booking.services',
            'booking.guestDetails.bookingRoom.room',
            'beneficiaryCosts.beneficiary',
        ]);
        $this->newStatus = $program->status;
    }

    public function updateStatus(): void
    {
        $allowed = [Program::STATUS_ACTIVE, Program::STATUS_COMPLETED, Program::STATUS_CANCELLED];
        if (!in_array($this->newStatus, $allowed, true)) {
            return;
        }

        $this->program->update(['status' => $this->newStatus]);

        if ($this->newStatus === Program::STATUS_CANCELLED && $this->program->booking) {
            $this->program->booking->update(['status' => 'cancelled']);
        }

        session()->flash('status', 'وضعیت برنامه به‌روز شد.');
        $this->dispatch('toast', type: 'success', message: 'وضعیت برنامه به‌روز شد.');
    }

    public function render()
    {
        return view('admin.programs.show', ['program' => $this->program]);
    }
}

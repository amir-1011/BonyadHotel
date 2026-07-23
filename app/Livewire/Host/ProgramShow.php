<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\AssertsHostPermissions;
use App\Models\Program;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'جزئیات برنامه', 'pageTitle' => 'جزئیات برنامه'])]
class ProgramShow extends Component
{
    use AssertsHostPermissions;

    public Program $program;

    public function mount(Program $program): void
    {
        abort_unless(
            Auth::user()->managesAccommodation($program->accommodation_id),
            403
        );

        $this->program = $program->load([
            'accommodation',
            'booking.bookingRooms.room',
            'booking.bookingRooms.roomType',
            'booking.services',
            'booking.guestDetails.bookingRoom.room',
            'beneficiaryCosts.beneficiary',
        ]);
    }

    public function destroy(): void
    {
        $this->assertHostCan('programs.show', 'delete');
        if ($this->program->booking) {
            $this->program->booking->update(['status' => 'cancelled']);
        }

        $this->program->update(['status' => Program::STATUS_CANCELLED]);
        session()->flash('status', 'برنامه لغو شد.');
        $this->redirectRoute('host.programs.index', navigate: true);
    }

    public function render()
    {
        return view('host.programs.show', ['program' => $this->program]);
    }
}

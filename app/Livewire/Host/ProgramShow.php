<?php

namespace App\Livewire\Host;

use App\Models\Program;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'جزئیات برنامه', 'pageTitle' => 'جزئیات برنامه'])]
class ProgramShow extends Component
{
    public Program $program;

    public function mount(Program $program): void
    {
        abort_unless(
            Auth::user()->accommodations()->where('id', $program->accommodation_id)->exists(),
            403
        );
        $this->program = $program;
        $this->program->load('accommodation', 'roomTypes');
    }

    public function destroy(): void
    {
        $this->program->delete();
        session()->flash('status', 'برنامه حذف شد.');
        $this->redirectRoute('host.programs.index', navigate: true);
    }

    public function render()
    {
        $program = $this->program;
        return view('host.programs.show', compact('program'));
    }
}

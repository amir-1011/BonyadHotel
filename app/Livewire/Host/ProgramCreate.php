<?php

namespace App\Livewire\Host;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'ثبت برنامه جدید', 'pageTitle' => 'ثبت برنامه / اردو'])]
class ProgramCreate extends Component
{
    public function render()
    {
        return view('host.programs.create');
    }
}

<?php

namespace App\Livewire\Admin;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'ثبت برنامه جدید', 'pageTitle' => 'ثبت برنامه / اردو'])]
class ProgramCreate extends Component
{
    public function render()
    {
        return view('admin.programs.create');
    }
}

<?php

namespace App\Livewire\Admin;

use App\Models\Accommodation;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'فروش دستی خدمات', 'pageTitle' => 'فروش دستی خدمات'])]
class ManualServiceSale extends Component
{
    public Accommodation $accommodation;

    public function mount(Accommodation $accommodation): void
    {
        $this->accommodation = $accommodation;
    }

    public function render()
    {
        return view('admin.accommodations.manual-service-sale', [
            'accommodation' => $this->accommodation,
        ]);
    }
}

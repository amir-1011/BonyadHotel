<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ManagesCancellationPolicySettings;
use App\Models\Accommodation;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'سیاست کنسلی', 'pageTitle' => 'سیاست کنسلی و استرداد وجه'])]
class AccommodationCancellationPolicySettings extends Component
{
  use ManagesCancellationPolicySettings;

  public function mount(Accommodation $accommodation): void
  {
    $this->bootCancellationPolicySettings($accommodation);
  }

  public function render()
  {
    return view('livewire.concerns.cancellation-policy-settings', [
      'backRoute' => route('admin.accommodations.index'),
      'panel'     => 'admin',
    ]);
  }
}

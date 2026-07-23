<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\ManagesCancellationPolicySettings;
use App\Models\Accommodation;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'سیاست کنسلی', 'pageTitle' => 'سیاست کنسلی و استرداد وجه'])]
class AccommodationCancellationPolicySettings extends Component
{
  use ManagesCancellationPolicySettings;

  public function mount(Accommodation $accommodation): void
  {
    abort_unless($accommodation->isManagedBy(Auth::user()), 403);
    $this->bootCancellationPolicySettings($accommodation);
  }

  public function render()
  {
    return view('livewire.concerns.cancellation-policy-settings', [
      'backRoute' => route('host.accommodations.index'),
      'panel'     => 'host',
    ]);
  }
}

<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\ManagesMedicalAccommodationReport;
use App\Services\MedicalAccommodationReportService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'اسکان درمانی', 'pageTitle' => 'اسکان درمانی'])]
class MedicalAccommodationReport extends Component
{
    use ManagesMedicalAccommodationReport;

    public function mount(): void
    {
        $this->mountMedicalAccommodationReport();
    }

    protected function medicalAccommodationReportPanel(): string
    {
        return 'host';
    }

    protected function medicalAccommodationReportRouteName(): string
    {
        return 'host.medical-accommodation-report';
    }

    protected function resolveDashboardAccommodationOptions(): array
    {
        return Auth::user()
            ->managedAccommodationOptions()
            ->map(fn ($acc) => ['id' => (int) $acc->id, 'name' => (string) $acc->name])
            ->values()
            ->all();
    }

    public function render(MedicalAccommodationReportService $service)
    {
        return $this->renderMedicalAccommodationReport($service);
    }
}

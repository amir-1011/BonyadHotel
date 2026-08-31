<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ManagesMedicalAccommodationReport;
use App\Models\Accommodation;
use App\Services\MedicalAccommodationReportService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'اسکان درمانی', 'pageTitle' => 'اسکان درمانی'])]
class MedicalAccommodationReport extends Component
{
    use ManagesMedicalAccommodationReport;

    public function mount(): void
    {
        $this->mountMedicalAccommodationReport();
    }

    protected function medicalAccommodationReportPanel(): string
    {
        return 'admin';
    }

    protected function medicalAccommodationReportRouteName(): string
    {
        return 'admin.medical-accommodation-report';
    }

    protected function resolveDashboardAccommodationOptions(): array
    {
        return Accommodation::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Accommodation $acc) => ['id' => (int) $acc->id, 'name' => (string) $acc->name])
            ->values()
            ->all();
    }

    public function render(MedicalAccommodationReportService $service)
    {
        return $this->renderMedicalAccommodationReport($service);
    }
}

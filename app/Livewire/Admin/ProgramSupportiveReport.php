<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ManagesSupportiveServicesReport;
use App\Models\Accommodation;
use App\Services\SupportiveServicesReportService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'گزارش خدمات حمایتی', 'pageTitle' => 'گزارش خدمات حمایتی'])]
class ProgramSupportiveReport extends Component
{
    use ManagesSupportiveServicesReport;

    public function mount(): void
    {
        $this->mountSupportiveServicesReport();
    }

    protected function supportiveServicesReportPanel(): string
    {
        return 'admin';
    }

    protected function supportiveServicesReportRouteName(): string
    {
        return 'admin.programs.supportive-report';
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

    public function render(SupportiveServicesReportService $service)
    {
        return $this->renderSupportiveServicesReport($service);
    }
}

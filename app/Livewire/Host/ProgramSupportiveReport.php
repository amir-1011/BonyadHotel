<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\ManagesSupportiveServicesReport;
use App\Services\SupportiveServicesReportService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'گزارش خدمات حمایتی', 'pageTitle' => 'گزارش خدمات حمایتی'])]
class ProgramSupportiveReport extends Component
{
    use ManagesSupportiveServicesReport;

    public function mount(): void
    {
        $this->mountSupportiveServicesReport();
    }

    protected function supportiveServicesReportPanel(): string
    {
        return 'host';
    }

    protected function supportiveServicesReportRouteName(): string
    {
        return 'host.programs.supportive-report';
    }

    protected function resolveDashboardAccommodationOptions(): array
    {
        return Auth::user()
            ->managedAccommodationOptions()
            ->map(fn ($acc) => ['id' => (int) $acc->id, 'name' => (string) $acc->name])
            ->values()
            ->all();
    }

    public function render(SupportiveServicesReportService $service)
    {
        return $this->renderSupportiveServicesReport($service);
    }
}

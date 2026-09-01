<?php

namespace App\Livewire\Concerns;

use App\Models\City;
use App\Models\Province;
use App\Services\SupportiveServicesReportService;
use Livewire\Attributes\Url;
use Morilog\Jalali\Jalalian;

trait ManagesSupportiveServicesReport
{
    use ManagesDashboardAccommodationFilter;

    #[Url(as: 'period')]
    public string $period = 'all';

    #[Url(as: 'jy')]
    public int $jalaliYear = 0;

    #[Url(as: 'jm')]
    public int $jalaliMonth = 0;

    public bool $showCityModal = false;

    public ?int $selectedCityId = null;

    public ?int $selectedProvinceId = null;

    public string $modalTitle = '';

    abstract protected function supportiveServicesReportPanel(): string;

    abstract protected function supportiveServicesReportRouteName(): string;

    public function mountSupportiveServicesReport(): void
    {
        $now = Jalalian::now();
        if ($this->jalaliYear < 1300) {
            $this->jalaliYear = $now->getYear();
        }
        if ($this->jalaliMonth < 1 || $this->jalaliMonth > 12) {
            $this->jalaliMonth = $now->getMonth();
        }
        if (! in_array($this->period, ['all', 'month', 'year'], true)) {
            $this->period = 'all';
        }

        $this->bootDashboardAccommodationFilter();
    }

    protected function dashboardAccommodationFilterPageUrl(): string
    {
        $query = ['period' => $this->period];
        if ($this->period !== 'all') {
            $query['jy'] = $this->jalaliYear;
            if ($this->period === 'month') {
                $query['jm'] = $this->jalaliMonth;
            }
        }

        return route($this->supportiveServicesReportRouteName(), $query);
    }

    public function setPeriod(string $period): void
    {
        if (! in_array($period, ['all', 'month', 'year'], true)) {
            return;
        }

        $this->period = $period;
        $this->closeModal();
        $this->dispatch('supportive-report-map-refresh');
    }

    public function prevPeriod(): void
    {
        if ($this->period === 'year') {
            $this->jalaliYear--;
        } elseif ($this->period === 'month') {
            if ($this->jalaliMonth <= 1) {
                $this->jalaliMonth = 12;
                $this->jalaliYear--;
            } else {
                $this->jalaliMonth--;
            }
        }
        $this->closeModal();
        $this->dispatch('supportive-report-map-refresh');
    }

    public function nextPeriod(): void
    {
        if ($this->period === 'year') {
            $this->jalaliYear++;
        } elseif ($this->period === 'month') {
            if ($this->jalaliMonth >= 12) {
                $this->jalaliMonth = 1;
                $this->jalaliYear++;
            } else {
                $this->jalaliMonth++;
            }
        }
        $this->closeModal();
        $this->dispatch('supportive-report-map-refresh');
    }

    public function goToCurrentPeriod(): void
    {
        $now = Jalalian::now();
        $this->jalaliYear = $now->getYear();
        $this->jalaliMonth = $now->getMonth();
        $this->closeModal();
        $this->dispatch('supportive-report-map-refresh');
    }

    public function openCity(int $cityId): void
    {
        $city = City::query()->with('province')->find($cityId);
        if (! $city) {
            return;
        }

        $this->selectedCityId = $cityId;
        $this->selectedProvinceId = null;
        $this->modalTitle = $city->name.($city->province ? ' ('.$city->province->name.')' : '');
        $this->showCityModal = true;
    }

    public function openProvince(int $provinceId): void
    {
        $province = Province::query()->find($provinceId);
        if (! $province) {
            return;
        }

        $this->selectedProvinceId = $provinceId;
        $this->selectedCityId = null;
        $this->modalTitle = 'استان '.$province->name;
        $this->showCityModal = true;
    }

    public function closeModal(): void
    {
        $this->showCityModal = false;
        $this->selectedCityId = null;
        $this->selectedProvinceId = null;
        $this->modalTitle = '';
    }

    protected function renderSupportiveServicesReport(SupportiveServicesReportService $service)
    {
        $ids = $this->effectiveDashboardAccommodationIds();
        $report = $service->build($ids, $this->period, $this->jalaliYear, $this->jalaliMonth);
        $range = $report['range'];

        $modalBookings = collect();
        if ($this->showCityModal && $this->selectedCityId) {
            $modalBookings = $service->cityBookings($ids, $range, $this->selectedCityId);
        } elseif ($this->showCityModal && $this->selectedProvinceId) {
            $modalBookings = $service->provinceBookings($ids, $range, $this->selectedProvinceId);
        }

        $now = Jalalian::now();
        $isCurrent = $this->jalaliYear === $now->getYear()
            && ($this->period !== 'month' || $this->jalaliMonth === $now->getMonth());

        return view('admin.supportive-services-report', array_merge($report, [
            'panel' => $this->supportiveServicesReportPanel(),
            'filterKey' => $this->dashboardAccommodationFilterKey(),
            'dashboardAccommodationOptions' => $this->dashboardAccommodationOptionList(),
            'modalBookings' => $modalBookings,
            'isCurrentPeriod' => $isCurrent,
            'componentId' => $this->getId(),
            'reportService' => $service,
        ]));
    }
}

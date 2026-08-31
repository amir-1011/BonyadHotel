<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\ManagesHostUserFilters;
use App\Models\Province;
use App\Models\User;
use App\Support\HostUserFilter;
use App\Support\HostUserFilterCatalog;
use App\Support\VeteranGroups;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'کاربران', 'pageTitle' => 'کاربران'])]
class UserIndex extends Component
{
    use ManagesHostUserFilters;

    public function mount(): void
    {
        $this->mountHostUserFilters();
    }

    public function render()
    {
        $accommodationIds = Auth::user()->managedAccommodationIds()->all();
        $filter = HostUserFilter::make($this->hostUserFilterParams(), $accommodationIds);

        $query = User::query();
        $filter->apply($query);

        $countFiltered = (clone $query)->count();
        $users = $query->paginate(20);

        $accommodations = Auth::user()->managedAccommodationOptions();
        $veteranOptions = VeteranGroups::options();
        $userTypeOptions = HostUserFilterCatalog::userTypeOptions();
        $hasBookingsOptions = HostUserFilterCatalog::hasBookingsOptions();
        $sortOptions = HostUserFilterCatalog::sortOptions();
        $provinceIds = HostUserFilter::resolveAccountingProvinceIds($accommodationIds);
        $provinces = $provinceIds === []
            ? collect()
            : Province::query()->whereIn('id', $provinceIds)->orderBy('name')->get();
        $hasActiveFilters = $filter->hasActiveFilters();
        $exportQuery = $filter->exportQuery();

        return view('host.users.index', compact(
            'users', 'accommodations', 'veteranOptions', 'userTypeOptions',
            'hasBookingsOptions', 'sortOptions', 'provinces',
            'countFiltered', 'hasActiveFilters', 'exportQuery',
        ));
    }
}

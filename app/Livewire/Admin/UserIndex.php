<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ManagesProgramBeneficiaries;
use App\Livewire\Concerns\ManagesProgramEmployers;
use App\Livewire\Concerns\ResolvesAccountingProvince;
use App\Models\ProgramBeneficiary;
use App\Models\ProgramEmployer;
use App\Models\Province;
use App\Models\User;
use App\Support\AdminUserFilter;
use App\Support\AdminUserProvinceGrouper;
use App\Support\AdminUserRoleFilterCatalog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('layouts.admin', ['title' => 'مدیریت کاربران', 'pageTitle' => 'کاربران'])]
class UserIndex extends Component
{
    use ManagesProgramBeneficiaries;
    use ManagesProgramEmployers;
    use ResolvesAccountingProvince;
    use WithPagination;

    #[Url] public string $search  = '';
    #[Url] public string $section = 'all';
    #[Url] public string $role    = '';

    public string $searchInput = '';

    public function mount(): void
    {
        $this->normalizeLegacySection();
        $this->searchInput = $this->search;
        $this->syncSectionRole();
    }

    public function applySearch(): void
    {
        $this->search = trim($this->searchInput);
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->searchInput = '';
        $this->search      = '';
        $this->resetPage();
    }

    public function setSection(string $section): void
    {
        if (!in_array($section, self::sectionKeys(), true)) {
            return;
        }

        $this->section = $section;
        $this->syncSectionRole();
        $this->resetPage();
    }

    public function setRoleTab(string $role): void
    {
        $this->section = 'personnel';
        $this->role    = $role;
        $this->resetPage();
    }

    private function normalizeLegacySection(): void
    {
        if ($this->section === 'roles') {
            $this->section = match ($this->role) {
                'employer'    => 'employers',
                'beneficiary' => 'beneficiaries',
                default       => 'personnel',
            };

            if ($this->section !== 'personnel') {
                $this->role = '';
            }

            return;
        }

        if (in_array($this->section, ['all', 'users'], true) && $this->role !== '' && $this->role !== 'guest') {
            $this->section = match ($this->role) {
                'employer'    => 'employers',
                'beneficiary' => 'beneficiaries',
                default       => 'personnel',
            };

            if ($this->section === 'personnel' && in_array($this->role, ['guest', 'super_admin'], true)) {
                $this->role = '';
            }
        }
    }

    private function syncSectionRole(): void
    {
        if ($this->section !== 'personnel') {
            $this->role = '';

            return;
        }

        $personnelTabOptions = AdminUserRoleFilterCatalog::personnelTabOptions();
        $validValues = array_column($personnelTabOptions, 'value');

        if ($this->role === '' || !in_array($this->role, $validValues, true)) {
            $this->role = $personnelTabOptions[0]['value'] ?? '';
        }
    }

    private function effectiveRole(): ?string
    {
        return match ($this->section) {
            'users'         => 'guest',
            'personnel'     => $this->role !== '' ? $this->role : null,
            'employers'     => 'employer',
            'beneficiaries' => 'beneficiary',
            default         => null,
        };
    }

    /** @return list<string> */
    public static function sectionKeys(): array
    {
        return ['all', 'users', 'personnel', 'employers', 'beneficiaries'];
    }

    public function toggleStatus(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->update(['is_active' => !$user->is_active]);
        session()->flash('status', 'وضعیت کاربر تغییر کرد.');
        $this->dispatch('toast', type: 'success', message: 'وضعیت کاربر تغییر کرد.');
    }

    public function assignRole(int $userId, string $role): void
    {
        $allowed = Role::pluck('name')->all();
        if (!in_array($role, $allowed, true)) {
            return;
        }

        $user = User::findOrFail($userId);
        $user->syncRoles([$role]);
        session()->flash('status', 'نقش کاربر تغییر کرد.');
        $this->dispatch('toast', type: 'success', message: 'نقش کاربر تغییر کرد.');
    }

    public function destroy(int $userId): void
    {
        User::findOrFail($userId)->delete();
        session()->flash('status', 'کاربر حذف شد.');
        $this->dispatch('toast', type: 'success', message: 'کاربر حذف شد.');
        $this->resetPage();
    }

    protected function requiresAccommodationContextForCatalog(): bool
    {
        return false;
    }

    protected function shouldAttachBeneficiaryToCatalogRows(): bool
    {
        return false;
    }

    protected function afterEmployerAddedToCatalog(ProgramEmployer $employer): void
    {
        $this->section = 'employers';
        $this->resetPage();
    }

    protected function afterBeneficiaryAddedToCatalog(ProgramBeneficiary $beneficiary): void
    {
        $this->section = 'beneficiaries';
        $this->resetPage();
    }

    public function render()
    {
        $effectiveRole = $this->effectiveRole();
        $groupByProvince = ! in_array($this->section, ['all', 'users'], true);

        $filter = AdminUserFilter::make([
            'search' => $this->search,
            'role'   => $effectiveRole ?? '',
        ]);

        $query = User::with([
            'roles',
            'province',
            'programEmployer.province',
            'programBeneficiary.province',
        ]);
        $filter->apply($query);

        if ($groupByProvince) {
            $users = null;
            $provinceGroups = AdminUserProvinceGrouper::group($query->get());
            $totalUsers = collect($provinceGroups)->sum(fn (array $group) => count($group['users']));
        } else {
            $users = $query->paginate(20);
            $provinceGroups = [];
            $totalUsers = $users->total();
        }

        $personnelTabOptions = AdminUserRoleFilterCatalog::personnelTabOptions();
        $hasActiveFilters = $this->search !== ''
            || in_array($this->section, ['users', 'personnel', 'employers', 'beneficiaries'], true);
        $exportQuery = array_filter([
            'search'  => $this->search !== '' ? $this->search : null,
            'section' => !in_array($this->section, ['all', ''], true) ? $this->section : null,
            'role'    => $this->section === 'personnel' && $this->role !== '' ? $this->role : null,
        ]);
        $section = $this->section;
        $role    = $this->role;
        $provinces = Province::query()->orderBy('name')->get();

        return view('admin.users.index', compact(
            'users',
            'provinceGroups',
            'totalUsers',
            'groupByProvince',
            'personnelTabOptions',
            'hasActiveFilters',
            'exportQuery',
            'section',
            'role',
            'provinces',
        ));
    }
}

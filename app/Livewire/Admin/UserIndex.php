<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Support\AdminUserFilter;
use App\Support\AdminUserRoleFilterCatalog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('layouts.admin', ['title' => 'مدیریت کاربران', 'pageTitle' => 'کاربران'])]
class UserIndex extends Component
{
    use WithPagination;

    #[Url] public string $search  = '';
    #[Url] public string $section = 'all';
    #[Url] public string $role    = '';

    public string $searchInput = '';

    public function mount(): void
    {
        if (in_array($this->section, ['all', 'users'], true) && $this->role !== '' && $this->role !== 'guest') {
            $this->section = 'roles';
        }

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
        if (!in_array($section, ['all', 'users', 'roles'], true)) {
            return;
        }

        $this->section = $section;
        $this->syncSectionRole();
        $this->resetPage();
    }

    public function setRoleTab(string $role): void
    {
        $this->section = 'roles';
        $this->role    = $role;
        $this->resetPage();
    }

    private function syncSectionRole(): void
    {
        if ($this->section !== 'roles') {
            $this->role = '';

            return;
        }

        $roleTabOptions = AdminUserRoleFilterCatalog::roleTabOptions();

        if ($this->role === '' || in_array($this->role, ['guest', 'super_admin'], true)) {
            $this->role = $roleTabOptions[0]['value'] ?? '';
        }
    }

    private function effectiveRole(): ?string
    {
        return match ($this->section) {
            'users' => 'guest',
            'roles' => $this->role !== '' ? $this->role : null,
            default => null,
        };
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
        if (!in_array($role, $allowed, true)) return;

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

    public function render()
    {
        $effectiveRole = $this->effectiveRole();

        $filter = AdminUserFilter::make([
            'search' => $this->search,
            'role'   => $effectiveRole ?? '',
        ]);

        $query = User::with('roles');
        $filter->apply($query);

        $users = $query->paginate(20);
        $roleTabOptions = AdminUserRoleFilterCatalog::roleTabOptions();
        $hasActiveFilters = $this->search !== '' || ($this->section === 'roles' && $this->role !== '');
        $exportQuery = array_filter([
            'search'  => $this->search !== '' ? $this->search : null,
            'section' => !in_array($this->section, ['all', ''], true) ? $this->section : null,
            'role'    => $this->section === 'roles' && $this->role !== '' ? $this->role : null,
        ]);
        $section = $this->section;
        $role    = $this->role;

        return view('admin.users.index', compact(
            'users',
            'roleTabOptions',
            'hasActiveFilters',
            'exportQuery',
            'section',
            'role',
        ));
    }
}

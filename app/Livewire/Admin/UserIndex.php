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

    #[Url] public string $search = '';
    #[Url] public string $role   = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedRole(): void { $this->resetPage(); }

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
        $filter = AdminUserFilter::make([
            'search' => $this->search,
            'role'   => $this->role,
        ]);

        $query = User::with('roles');
        $filter->apply($query);

        $users = $query->paginate(20);
        $roleFilterOptions = AdminUserRoleFilterCatalog::options();
        $hasActiveFilters = $filter->hasActiveFilters();
        $exportQuery = $filter->exportQuery();
        $role = $this->role;

        return view('admin.users.index', compact('users', 'roleFilterOptions', 'hasActiveFilters', 'exportQuery', 'role'));
    }
}

<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.admin', ['title' => 'مشاهده کاربر', 'pageTitle' => 'جزئیات کاربر'])]
class UserShow extends Component
{
    public User $user;
    public string $selectedRole = '';

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->user->load('roles', 'bookings.accommodation', 'accommodations');
        $this->selectedRole = $this->user->roles->first()?->name ?? '';
    }

    public function assignRole(): void
    {
        if (!$this->selectedRole) return;

        $role = Role::findByName($this->selectedRole, 'web');
        $this->user->syncRoles([$role]);
        $this->user->refresh()->load('roles');
        session()->flash('status', 'نقش کاربر به‌روز شد.');
        $this->dispatch('toast', type: 'success', message: 'نقش کاربر به‌روز شد.');
    }

    public function render()
    {
        $user = $this->user;
        return view('admin.users.show', compact('user'));
    }
}

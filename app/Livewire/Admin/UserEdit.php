<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\NationalIdVerificationService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.admin', ['title' => 'ویرایش کاربر', 'pageTitle' => 'ویرایش کاربر'])]
class UserEdit extends Component
{
    public User $user;

    public string $name             = '';
    public string $mobile           = '';
    public string $nationalId       = '';
    public string $veteranType      = '';
    public int    $discountPct      = 0;
    public bool   $isActive         = true;
    public string $role             = 'user';

    public function mount(User $user): void
    {
        $this->user           = $user;
        $this->name           = $user->name ?? '';
        $this->mobile         = $user->mobile ?? '';
        $this->nationalId     = $user->national_id ?? '';
        $this->veteranType    = $user->veteran_type ?? '';
        $this->discountPct    = $user->discount_percentage ?? 0;
        $this->isActive       = (bool) ($user->is_active ?? true);
        $this->role           = $user->roles->first()?->name ?? 'user';
    }

    public function update(): void
    {
        $this->validate([
            'name'       => ['nullable', 'string', 'max:100'],
            'nationalId' => ['nullable', 'digits:10'],
            'discountPct'=> ['required', 'integer', 'min:0', 'max:100'],
            'role'       => ['required', 'in:user,host,super_admin'],
        ]);

        $data = [
            'name'                => $this->name ?: null,
            'national_id'         => $this->nationalId ?: null,
            'veteran_type'        => $this->veteranType ?: null,
            'discount_percentage' => $this->discountPct,
            'is_active'           => $this->isActive,
        ];

        // If national_id changed, re-verify
        if ($this->nationalId && $this->nationalId !== $this->user->national_id) {
            $result = app(NationalIdVerificationService::class)->verify($this->nationalId);
            if (!$result['valid']) {
                $this->addError('nationalId', $result['message']);
                return;
            }
            $data['veteran_type']            = $result['veteran_type'];
            $data['discount_percentage']     = $result['discount'];
            $data['national_id_verified_at'] = now();
        }

        $this->user->update($data);
        $this->user->syncRoles([$this->role]);

        session()->flash('status', 'کاربر با موفقیت ویرایش شد.');
        $this->redirectRoute('admin.users.index', navigate: true);
    }

    public function render()
    {
        $user  = $this->user;
        $roles = Role::all();
        return view('admin.users.edit', compact('user', 'roles'));
    }
}

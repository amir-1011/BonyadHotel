<?php

namespace App\Livewire\Admin;

use App\Models\Accommodation;
use App\Models\User;
use App\Services\NationalIdVerificationService;
use App\Support\VeteranGroups;
use App\Support\HostPermissions;
use Illuminate\Validation\Rule;
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
    public string $role             = 'guest';
    public ?int   $accommodationToAssign = null;
    public array  $hostPanelPermissions  = [];
    public string $hostPassword         = '';
    public string $hostPassword_confirmation = '';

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->name = $user->name ?? '';
        $this->mobile = $user->mobile ?? '';
        $this->nationalId = $user->national_id ?? '';
        $this->veteranType = $user->normalizedVeteranType() ?? '';
        $this->discountPct = $user->discount_percentage ?? 0;
        $this->isActive = (bool) ($user->is_active ?? true);
        $this->role = $user->roles->first()?->name ?? 'guest';
        $this->hostPanelPermissions = $user->effectiveHostPermissions();
    }

    public function updatedRole(string $value): void
    {
        if ($value === 'host' && empty($this->hostPanelPermissions)) {
            $this->hostPanelPermissions = HostPermissions::defaults();
        }
    }

    public function updatedVeteranType(): void
    {
        $this->discountPct = VeteranGroups::accommodationDiscount($this->veteranType ?: null);
    }

    public function syncDiscountFromGroup(): void
    {
        $this->discountPct = VeteranGroups::accommodationDiscount($this->veteranType ?: null);
    }

    public function assignAccommodation(): void
    {
        if ($this->role !== 'host') {
            $this->addError('accommodations', 'برای نسبت دادن اقامتگاه، نقش کاربر باید میزبان (host) باشد.');
            return;
        }

        $this->validate([
            'accommodationToAssign' => ['required', 'exists:accommodations,id'],
        ], [
            'accommodationToAssign.required' => 'یک اقامتگاه انتخاب کنید.',
        ]);

        $accommodation = Accommodation::query()->findOrFail($this->accommodationToAssign);

        $accommodation->grantHostAccess($this->user);

        $this->accommodationToAssign = null;

        session()->flash('status', "اقامتگاه «{$accommodation->name}» به این میزبان نسبت داده شد.");
    }

    public function revokeAccommodation(int $accommodationId): void
    {
        $accommodation = $this->user->accommodations()
            ->where('accommodations.id', $accommodationId)
            ->firstOrFail();

        $name = $accommodation->name;
        $accommodation->revokeHostAccess($this->user);

        session()->flash('status', "دسترسی میزبان به اقامتگاه «{$name}» لغو شد.");
    }

    public function saveHostPanelAccess(): void
    {
        if ($this->role !== 'host') {
            $this->addError('hostPanelPermissions', 'تنظیم دسترسی پنل فقط برای نقش میزبان (host) امکان‌پذیر است.');
            return;
        }

        $this->validate([
            'hostPanelPermissions'   => ['required', 'array', 'min:1'],
            'hostPanelPermissions.*' => ['string', Rule::in(HostPermissions::keys())],
        ], [
            'hostPanelPermissions.required' => 'حداقل یک بخش از پنل میزبان را انتخاب کنید.',
            'hostPanelPermissions.min'      => 'حداقل یک بخش از پنل میزبان را انتخاب کنید.',
        ]);

        $this->user->update([
            'host_panel_permissions' => array_values(array_unique($this->hostPanelPermissions)),
        ]);

        session()->flash('status', 'دسترسی‌های پنل میزبان ذخیره شد.');
    }

    public function updateHostPassword(): void
    {
        if ($this->role !== 'host') {
            $this->addError('hostPassword', 'تغییر رمز فقط برای کاربران با نقش میزبان (host) امکان‌پذیر است.');
            return;
        }

        $this->validate([
            'hostPassword'              => ['required', 'string', 'min:6', 'confirmed'],
            'hostPassword_confirmation' => ['required'],
        ], [
            'hostPassword.required'  => 'رمز عبور جدید الزامی است.',
            'hostPassword.min'       => 'رمز عبور جدید باید حداقل ۶ کاراکتر باشد.',
            'hostPassword.confirmed' => 'تکرار رمز عبور جدید مطابقت ندارد.',
        ]);

        $this->user->update(['password' => $this->hostPassword]);

        $this->reset('hostPassword', 'hostPassword_confirmation');
        session()->flash('password_status', 'رمز عبور میزبان با موفقیت تغییر کرد.');
    }

    public function update(): void
    {
        $this->nationalId = preg_replace('/\D/', '', $this->nationalId);

        $veteranKeys = array_keys(VeteranGroups::options());

        $roleNames = Role::pluck('name')->all();

        $this->validate([
            'name'        => ['nullable', 'string', 'max:100'],
            'nationalId'  => [
                'nullable',
                'digits:10',
                Rule::unique('users', 'national_id')->ignore($this->user->id),
            ],
            'veteranType' => ['nullable', 'string', Rule::in($veteranKeys)],
            'discountPct' => ['required', 'integer', 'min:0', 'max:100'],
            'role'        => ['required', 'string', Rule::in($roleNames)],
        ], [
            'nationalId.unique' => $this->nationalIdDuplicateMessage(),
            'nationalId.digits' => 'کد ملی باید دقیقاً ۱۰ رقم باشد.',
        ]);

        $data = [
            'name'                => $this->name ?: null,
            'national_id'         => $this->nationalId ?: null,
            'veteran_type'        => $this->veteranType ?: null,
            'discount_percentage' => $this->discountPct,
            'is_active'           => $this->isActive,
        ];

        if ($this->nationalId && $this->nationalId !== $this->user->national_id) {
            $result = app(NationalIdVerificationService::class)->verify($this->nationalId);
            if (!$result['valid']) {
                $this->addError('nationalId', $result['message']);
                return;
            }
            $data['veteran_type']            = $result['veteran_type'];
            $data['discount_percentage']     = $result['discount'];
            $data['national_id_verified_at'] = now();
            $this->veteranType = $result['veteran_type'] ?? '';
            $this->discountPct = $result['discount'];
        } elseif (!$this->nationalId && $this->user->national_id) {
            $data['national_id_verified_at'] = null;
        }

        if (!$this->veteranType) {
            $data['discount_percentage'] = 0;
        }

        if ($this->role === 'host') {
            $data['host_panel_permissions'] = array_values(array_unique(
                array_intersect($this->hostPanelPermissions, HostPermissions::keys())
            ));
            if (empty($data['host_panel_permissions'])) {
                $data['host_panel_permissions'] = HostPermissions::defaults();
            }
        } else {
            $data['host_panel_permissions'] = null;
        }

        $this->user->update($data);
        $this->user->syncRoles([$this->role]);

        session()->flash('status', 'کاربر با موفقیت ویرایش شد.');
        $this->redirectRoute('admin.users.index', navigate: true);
    }

    private function nationalIdDuplicateMessage(): string
    {
        $existing = User::query()
            ->where('national_id', $this->nationalId)
            ->where('id', '!=', $this->user->id)
            ->first();

        if (!$existing) {
            return 'این کد ملی قبلاً برای کاربر دیگری ثبت شده است.';
        }

        $label = $existing->name ?: 'کاربر';

        return "این کد ملی قبلاً برای «{$label}» (موبایل: {$existing->mobile}) ثبت شده است.";
    }

    public function render()
    {
        $assignedAccommodations = collect();
        $availableAccommodations = collect();

        if ($this->role === 'host') {
            $assignedAccommodations = $this->user->accommodations()
                ->with(['city', 'hosts'])
                ->orderBy('name')
                ->get();

            $availableAccommodations = Accommodation::query()
                ->with(['city', 'hosts'])
                ->withCount('hosts')
                ->whereDoesntHave('hosts', fn ($query) => $query->where('users.id', $this->user->id))
                ->orderBy('name')
                ->get();
        }

        return view('admin.users.edit', [
            'user'                    => $this->user,
            'roles'                   => Role::all(),
            'veteranGroups'           => VeteranGroups::options(),
            'assignedAccommodations'  => $assignedAccommodations,
            'availableAccommodations' => $availableAccommodations,
            'hostPermissionCatalog'   => HostPermissions::catalog(),
        ]);
    }
}

<?php

namespace App\Livewire\Admin;

use App\Models\Accommodation;
use App\Models\User;
use App\Services\NationalIdVerificationService;
use App\Support\HostPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'افزودن میزبان', 'pageTitle' => 'افزودن میزبان'])]
class HostCreate extends Component
{
    public string $name                        = '';
    public string $mobile                      = '';
    public string $nationalId                  = '';
    public string $hostPassword                = '';
    public string $hostPassword_confirmation   = '';
    public array  $hostPanelPermissions        = [];
    public array  $selectedAccommodationIds    = [];

    public function mount(): void
    {
        $this->hostPanelPermissions = HostPermissions::defaults();
    }

    public function save(): void
    {
        $this->mobile = preg_replace('/\D/', '', $this->mobile);
        $this->nationalId = preg_replace('/\D/', '', $this->nationalId);

        $this->validate([
            'name'                      => ['required', 'string', 'max:100'],
            'mobile'                    => ['required', 'regex:/^09[0-9]{9}$/', 'unique:users,mobile'],
            'nationalId'                => ['nullable', 'digits:10', 'unique:users,national_id'],
            'hostPassword'              => ['required', 'string', 'min:6', 'confirmed'],
            'hostPassword_confirmation' => ['required'],
            'hostPanelPermissions'      => ['required', 'array', 'min:1'],
            'hostPanelPermissions.*'    => ['string', Rule::in(HostPermissions::keys())],
            'selectedAccommodationIds'  => ['nullable', 'array'],
            'selectedAccommodationIds.*'=> ['integer', 'exists:accommodations,id'],
        ], [
            'name.required'                  => 'نام میزبان الزامی است.',
            'mobile.required'                => 'شماره موبایل الزامی است.',
            'mobile.regex'                   => 'شماره موبایل معتبر نیست. مثال: 09123456789',
            'mobile.unique'                  => 'این شماره موبایل قبلاً ثبت شده است.',
            'nationalId.digits'              => 'کد ملی باید دقیقاً ۱۰ رقم باشد.',
            'nationalId.unique'              => $this->nationalIdDuplicateMessage(),
            'hostPassword.required'          => 'رمز عبور پنل میزبان الزامی است.',
            'hostPassword.min'               => 'رمز عبور باید حداقل ۶ کاراکتر باشد.',
            'hostPassword.confirmed'         => 'تکرار رمز عبور مطابقت ندارد.',
            'hostPanelPermissions.required'  => 'حداقل یک بخش از پنل میزبان را انتخاب کنید.',
            'hostPanelPermissions.min'       => 'حداقل یک بخش از پنل میزبان را انتخاب کنید.',
        ]);

        $data = [
            'name'                   => $this->name,
            'mobile'                 => $this->mobile,
            'password'               => $this->hostPassword,
            'mobile_verified_at'     => now(),
            'host_panel_permissions' => array_values(array_unique($this->hostPanelPermissions)),
        ];

        if ($this->nationalId) {
            $result = app(NationalIdVerificationService::class)->verify($this->nationalId);
            if (!$result['valid']) {
                $this->addError('nationalId', $result['message']);
                return;
            }

            $data['national_id']             = $this->nationalId;
            $data['veteran_type']            = $result['veteran_type'];
            $data['discount_percentage']     = $result['discount'];
            $data['national_id_verified_at'] = now();
        }

        $user = DB::transaction(function () use ($data) {
            $user = User::create($data);
            $user->assignRole('host');

            $accommodationIds = array_values(array_unique(array_map('intval', $this->selectedAccommodationIds)));

            if ($accommodationIds !== []) {
                Accommodation::query()
                    ->whereIn('id', $accommodationIds)
                    ->get()
                    ->each(fn (Accommodation $accommodation) => $accommodation->grantHostAccess($user));
            }

            return $user;
        });

        session()->flash('status', "میزبان «{$user->name}» با موفقیت ایجاد شد.");

        $this->redirectRoute('admin.users.edit', $user, navigate: true);
    }

    private function nationalIdDuplicateMessage(): string
    {
        $existing = User::query()->where('national_id', $this->nationalId)->first();

        if (!$existing) {
            return 'این کد ملی قبلاً برای کاربر دیگری ثبت شده است.';
        }

        $label = $existing->name ?: 'کاربر';

        return "این کد ملی قبلاً برای «{$label}» (موبایل: {$existing->mobile}) ثبت شده است.";
    }

    public function render()
    {
        $accommodations = Accommodation::query()
            ->with(['city', 'hosts'])
            ->withCount('hosts')
            ->orderBy('name')
            ->get();

        return view('admin.users.create-host', [
            'accommodations'        => $accommodations,
            'hostPermissionCatalog' => HostPermissions::catalog(),
        ]);
    }
}

<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ManagesHostPermissionForm;
use App\Livewire\Concerns\ManagesHostPositionForm;
use App\Models\Accommodation;
use App\Models\User;
use App\Services\NationalIdVerificationService;
use App\Support\HostPermissions;
use App\Support\HostPositionTitles;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'افزودن میزبان', 'pageTitle' => 'افزودن میزبان'])]
class HostCreate extends Component
{
    use ManagesHostPermissionForm;
    use ManagesHostPositionForm;

    public string $name                        = '';
    public string $mobile                      = '';
    public string $nationalId                  = '';
    public string $hostPassword                = '';
    public string $hostPassword_confirmation   = '';
    public array  $selectedAccommodationIds    = [];

    public function mount(): void
    {
        $this->mountHostPermissionForm();
        $this->mountHostPositionForm();
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
        ]);

        $this->validateHostPermissionForm();
        $this->validateHostPositionForm();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $data = [
            'name'                   => $this->name,
            'mobile'                 => $this->mobile,
            'password'               => $this->hostPassword,
            'mobile_verified_at'     => now(),
            'host_panel_permissions' => $this->hostPermissionGrantsFromForm(),
            'host_position_title'    => $this->resolvedHostPositionTitle(),
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
            'hostPositionOptions'   => HostPositionTitles::optionsForForm($this->hostPositionPreset),
        ]);
    }
}

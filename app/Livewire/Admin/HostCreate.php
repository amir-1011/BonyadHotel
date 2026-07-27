<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ManagesHostPositionForm;
use App\Models\Accommodation;
use App\Models\User;
use App\Services\HostPersonnelCodeProvisioner;
use App\Services\NationalIdVerificationService;
use App\Support\HostPositionTitles;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'افزودن میزبان', 'pageTitle' => 'افزودن میزبان'])]
class HostCreate extends Component
{
    use ManagesHostPositionForm;

    public string $name                        = '';
    public string $mobile                      = '';
    public string $nationalId                  = '';
    public string $hostPassword                = '';
    public string $hostPassword_confirmation   = '';
    public array  $selectedAccommodationIds    = [];

    public function mount(): void
    {
        $this->mountHostPositionForm();
    }

    public function updatedSelectedAccommodationIds(): void
    {
        $this->selectedAccommodationIds = array_values(array_unique(array_map('intval', $this->selectedAccommodationIds)));
    }

    public function previewNextPersonnelCode(): string
    {
        $accommodation = $this->firstSelectedAccommodation();

        $preview = app(HostPersonnelCodeProvisioner::class)->previewNextForAccommodation($accommodation);

        return $preview ?? '—';
    }

    public function previewPersonnelProvinceLabel(): string
    {
        $accommodation = $this->firstSelectedAccommodation();
        $province = $accommodation?->resolvedProvince();

        return $province?->displayLabel() ?? 'ابتدا یک اقامتگاه انتخاب کنید';
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
            'selectedAccommodationIds'  => ['required', 'array', 'min:1'],
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
            'selectedAccommodationIds.required'=> 'حداقل یک اقامتگاه برای تعیین استان و کد پرسنلی الزامی است.',
            'selectedAccommodationIds.min'   => 'حداقل یک اقامتگاه برای تعیین استان و کد پرسنلی الزامی است.',
        ]);

        $this->validateHostPositionForm();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $firstAccommodation = $this->firstSelectedAccommodation();

        if (!$firstAccommodation?->resolvedProvince()) {
            $this->addError('selectedAccommodationIds', 'استان اقامتگاه انتخاب‌شده مشخص نیست. لطفاً اقامتگاه دیگری انتخاب کنید یا کد استان را در مدیریت استان‌ها ثبت کنید.');

            return;
        }

        $positionTitle = $this->resolvedHostPositionTitle();

        $data = [
            'name'                   => $this->name,
            'mobile'                 => $this->mobile,
            'password'               => $this->hostPassword,
            'mobile_verified_at'     => now(),
            'host_panel_permissions' => HostPositionTitles::grantsForPositionLabel($positionTitle),
            'host_position_title'    => $positionTitle,
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

            foreach ($this->orderedSelectedAccommodationIds() as $accommodationId) {
                $accommodation = Accommodation::query()->find($accommodationId);

                if ($accommodation) {
                    $accommodation->grantHostAccess($user);
                }
            }

            return $user->fresh(['province', 'accommodations.city.province', 'accommodations.county.province']);
        });

        if (!filled($user->personnel_code)) {
            $this->addError('selectedAccommodationIds', 'کد پرسنلی تخصیص داده نشد. کد حسابداری استان مربوط به اقامتگاه را در پنل مدیریت استان‌ها بررسی کنید.');

            return;
        }

        session()->flash('status', "میزبان «{$user->name}» با کد پرسنلی {$user->personnel_code} ایجاد شد.");

        $this->redirectRoute('admin.users.edit', $user, navigate: true);
    }

    private function firstSelectedAccommodation(): ?Accommodation
    {
        $firstId = $this->orderedSelectedAccommodationIds()[0] ?? null;

        if (!$firstId) {
            return null;
        }

        return Accommodation::query()
            ->with(['city.province', 'county.province'])
            ->find($firstId);
    }

    /** @return list<int> */
    private function orderedSelectedAccommodationIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->selectedAccommodationIds)));
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
            ->with(['city.province', 'county.province', 'hosts'])
            ->withCount('hosts')
            ->orderBy('name')
            ->get();

        return view('admin.users.create-host', [
            'accommodations'      => $accommodations,
            'hostPositionOptions' => HostPositionTitles::optionsForForm($this->hostPositionPreset),
        ]);
    }
}

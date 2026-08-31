<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\EnsuresHostManagedUserAccess;
use App\Livewire\Concerns\ManagesHostPositionForm;
use App\Models\Accommodation;
use App\Models\Province;
use App\Models\User;
use App\Services\AccountingProvinceReassignmentService;
use App\Services\HostPersonnelCodeProvisioner;
use App\Services\NationalIdVerificationService;
use App\Support\HostPositionTitles;
use App\Support\VeteranGroups;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'ویرایش کاربر', 'pageTitle' => 'ویرایش کاربر'])]
class UserEdit extends Component
{
    use EnsuresHostManagedUserAccess;
    use ManagesHostPositionForm;

    public User $user;

    public string $name             = '';
    public string $nationalId       = '';
    public string $veteranType      = '';
    public string $secondaryVeteranType = '';

    /** @var array<int, string> */
    public array $selectedVeteranTypes = [];

    public int    $discountPct      = 0;
    public bool   $isActive         = true;
    public ?int   $accommodationToAssign = null;
    public string $hostPassword         = '';
    public string $hostPassword_confirmation = '';

    public ?int $provinceId = null;

    public ?int $originalProvinceId = null;

    public function mount(User $user): void
    {
        $this->authorizeHostCan('users.edit', 'read');
        $this->authorizeHostManagedUser($user);

        $this->user = $user->load([
            'country',
            'residenceCity',
            'province',
            'accommodations.city.province',
            'accommodations.county.province',
            'programBeneficiary.province',
            'programEmployer.province',
            'roles',
        ]);

        if ($this->user->isHost() && blank($this->user->personnel_code)) {
            $this->user = app(HostPersonnelCodeProvisioner::class)
                ->provisionIfNeeded($this->user)
                ->load([
                    'country',
                    'residenceCity',
                    'province',
                    'accommodations.city.province',
                    'accommodations.county.province',
                    'programBeneficiary.province',
                    'programEmployer.province',
                    'roles',
                ]);
        }

        $this->name = $this->user->name ?? '';
        $this->nationalId = $user->national_id ?? '';
        $this->veteranType = $user->normalizedVeteranType() ?? '';
        $this->secondaryVeteranType = $user->normalizedSecondaryVeteranType() ?? '';
        $this->selectedVeteranTypes = $user->normalizedVeteranTypes();
        $this->discountPct = $user->discount_percentage ?? 0;
        $this->isActive = (bool) ($user->is_active ?? true);
        $this->provinceId = $this->resolveCurrentAccountingProvinceId($this->user);
        $this->originalProvinceId = $this->provinceId;
        $this->mountHostPositionForm($user);
    }

    public function accountingProvinceChangePending(): bool
    {
        if (!$this->user->hasAccountingProfile() || !$this->provinceId) {
            return false;
        }

        return (int) $this->provinceId !== (int) $this->originalProvinceId;
    }

    public function previewAccountingCodeAfterProvinceChange(): string
    {
        if (!$this->accountingProvinceChangePending()) {
            return '—';
        }

        try {
            $province = Province::query()->findOrFail($this->provinceId);
            $indicator = app(AccountingProvinceReassignmentService::class)->accountingIndicatorForUser($this->user);

            if ($indicator === null) {
                return '—';
            }

            return app(AccountingProvinceReassignmentService::class)->previewNextCode($province, $indicator);
        } catch (\Throwable) {
            return '—';
        }
    }

    public function accountingProvinceChangeConfirmMessage(): string
    {
        $currentCode = app(AccountingProvinceReassignmentService::class)->currentCodeForUser($this->user) ?? '—';
        $newCode = $this->previewAccountingCodeAfterProvinceChange();
        $oldProvince = $this->originalProvinceId
            ? Province::query()->find($this->originalProvinceId)?->displayLabel()
            : 'نامشخص';
        $newProvince = $this->provinceId
            ? Province::query()->find($this->provinceId)?->displayLabel()
            : 'نامشخص';

        return "استان از «{$oldProvince}» به «{$newProvince}» تغییر می‌کند. "
            . "کد حسابداری از {$currentCode} به {$newCode} تغییر خواهد کرد. "
            . 'این عملیات قابل بازگشت نیست. ادامه می‌دهید؟';
    }

    public function updatedVeteranType(): void
    {
        $this->syncSelectedVeteranTypesFromFields();
        $this->syncDiscountFromGroups();
    }

    public function updatedSecondaryVeteranType(): void
    {
        $this->syncSelectedVeteranTypesFromFields();
        $this->syncDiscountFromGroups();
    }

    public function updatedSelectedVeteranTypes(): void
    {
        $this->normalizeSelectedVeteranTypes();
        $this->syncDiscountFromGroups();
    }

    public function syncDiscountFromGroup(): void
    {
        $this->syncDiscountFromGroups();
    }

    public function assignAccommodation(): void
    {
        $this->authorizeHostCan('users.edit', 'edit');

        if (!$this->user->isHost()) {
            $this->addError('accommodations', 'برای نسبت دادن اقامتگاه، کاربر باید میزبان باشد.');

            return;
        }

        $managedIds = Auth::user()->managedAccommodationIds()->all();

        $this->validate([
            'accommodationToAssign' => ['required', 'exists:accommodations,id'],
        ], [
            'accommodationToAssign.required' => 'یک اقامتگاه انتخاب کنید.',
        ]);

        if (!in_array((int) $this->accommodationToAssign, $managedIds, true)) {
            $this->addError('accommodationToAssign', 'فقط اقامتگاه‌های تحت مدیریت شما قابل انتخاب هستند.');

            return;
        }

        $accommodation = Accommodation::query()->findOrFail($this->accommodationToAssign);
        $accommodation->grantHostAccess($this->user);

        $this->user = app(HostPersonnelCodeProvisioner::class)
            ->provisionIfNeeded($this->user->fresh(['province', 'accommodations.city.province', 'accommodations.county.province']))
            ->load(['province', 'accommodations.city', 'accommodations.county']);

        $this->accommodationToAssign = null;

        $codeMessage = filled($this->user->personnel_code)
            ? " کد پرسنلی: {$this->user->personnel_code}."
            : '';

        session()->flash('status', "اقامتگاه «{$accommodation->name}» به این میزبان نسبت داده شد.{$codeMessage}");
    }

    public function revokeAccommodation(int $accommodationId): void
    {
        $this->authorizeHostCan('users.edit', 'edit');

        if (!$this->user->isHost()) {
            return;
        }

        $managedIds = Auth::user()->managedAccommodationIds()->all();

        if (!in_array($accommodationId, $managedIds, true)) {
            abort(403);
        }

        $accommodation = $this->user->accommodations()
            ->where('accommodations.id', $accommodationId)
            ->firstOrFail();

        $name = $accommodation->name;
        $accommodation->revokeHostAccess($this->user);

        session()->flash('status', "دسترسی میزبان به اقامتگاه «{$name}» لغو شد.");
    }

    public function updateHostPassword(): void
    {
        $this->authorizeHostCan('users.edit', 'edit');

        if (!$this->user->isHost()) {
            $this->addError('hostPassword', 'تغییر رمز فقط برای میزبان امکان‌پذیر است.');

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
        $this->authorizeHostCan('users.edit', 'edit');

        $this->nationalId = preg_replace('/\D/', '', $this->nationalId);

        $veteranKeys = array_keys(VeteranGroups::options());

        $this->validate([
            'name'        => ['nullable', 'string', 'max:100'],
            'nationalId'  => [
                'nullable',
                'digits:10',
                Rule::unique('users', 'national_id')->ignore($this->user->id),
            ],
            'veteranType' => ['nullable', 'string', Rule::in($veteranKeys)],
            'secondaryVeteranType' => ['nullable', 'string', Rule::in($veteranKeys)],
            'selectedVeteranTypes' => ['array', 'max:2'],
            'selectedVeteranTypes.*' => ['string', Rule::in($veteranKeys)],
            'discountPct' => ['required', 'integer', 'min:0', 'max:100'],
        ], [
            'nationalId.unique' => $this->nationalIdDuplicateMessage(),
            'nationalId.digits' => 'کد ملی باید دقیقاً ۱۰ رقم باشد.',
        ]);

        $this->normalizeSelectedVeteranTypes();
        [$primaryType, $secondaryType] = app(\App\Services\VeteranPolicyService::class)
            ->splitVeteranTypes($this->selectedVeteranTypes);

        $data = [
            'name'                   => $this->name ?: null,
            'national_id'            => $this->nationalId ?: null,
            'veteran_type'           => $primaryType,
            'secondary_veteran_type' => $secondaryType,
            'discount_percentage'    => $this->discountPct,
            'is_active'              => $this->isActive,
        ];

        if ($this->nationalId && $this->nationalId !== $this->user->national_id) {
            $result = app(NationalIdVerificationService::class)->verify($this->nationalId);
            if (!$result['valid']) {
                $this->addError('nationalId', $result['message']);

                return;
            }
            $data['veteran_type']            = $result['veteran_type'];
            $data['secondary_veteran_type']  = null;
            $data['discount_percentage']     = $result['discount'];
            $data['national_id_verified_at'] = now();
            $this->veteranType = $result['veteran_type'] ?? '';
            $this->secondaryVeteranType = '';
            $this->selectedVeteranTypes = array_values(array_filter([$this->veteranType ?: null]));
            $this->discountPct = $result['discount'];
        } elseif (!$this->nationalId && $this->user->national_id) {
            $data['national_id_verified_at'] = null;
        }

        if (empty($this->selectedVeteranTypes) && !$this->veteranType) {
            $data['discount_percentage'] = 0;
            $data['secondary_veteran_type'] = null;
        }

        if ($this->user->isHost()) {
            $this->validateHostPositionForm();

            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }

            $positionTitle = $this->resolvedHostPositionTitle();
            $data['host_panel_permissions'] = HostPositionTitles::grantsForPositionLabel($positionTitle);
            $data['host_position_title'] = $positionTitle;
        }

        if ($this->user->hasAccountingProfile() && $this->provinceId) {
            $this->validate([
                'provinceId' => ['required', 'integer', 'exists:provinces,id'],
            ], [
                'provinceId.required' => 'انتخاب استان برای کدینگ حسابداری الزامی است.',
            ]);

            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }
        }

        $provinceChangeMessage = null;

        if ($this->accountingProvinceChangePending()) {
            try {
                $newProvince = Province::query()->findOrFail($this->provinceId);
                app(\App\Services\ProvinceAccountingCodeService::class)->ensureProvinceHasCode($newProvince);
                $newCode = app(AccountingProvinceReassignmentService::class)->reassignForUser($this->user, $newProvince);
                $this->user = $this->user->fresh([
                    'province',
                    'programEmployer.province',
                    'programBeneficiary.province',
                ]);
                $this->originalProvinceId = $this->provinceId;
                $provinceChangeMessage = "کدینگ حسابداری به {$newCode} تغییر یافت.";
            } catch (\Throwable $e) {
                $this->addError('provinceId', $e->getMessage());

                return;
            }
        }

        $this->user->update($data);

        $statusMessage = 'کاربر با موفقیت ویرایش شد.';
        if ($provinceChangeMessage) {
            $statusMessage .= ' ' . $provinceChangeMessage;
        }

        session()->flash('status', $statusMessage);
        $this->redirectRoute('host.users.show', $this->user, navigate: true);
    }

    private function resolveCurrentAccountingProvinceId(User $user): ?int
    {
        if ($user->province_id) {
            return (int) $user->province_id;
        }

        if ($user->isProgramEmployer() && $user->programEmployer?->province_id) {
            return (int) $user->programEmployer->province_id;
        }

        if ($user->isProgramBeneficiary() && $user->programBeneficiary?->province_id) {
            return (int) $user->programBeneficiary->province_id;
        }

        if ($user->isHost()) {
            $province = app(HostPersonnelCodeProvisioner::class)->resolveProvinceFromAccommodations($user);

            return $province?->id;
        }

        return null;
    }

    private function syncSelectedVeteranTypesFromFields(): void
    {
        $types = array_values(array_filter([
            $this->veteranType ?: null,
            $this->secondaryVeteranType ?: null,
        ]));

        $this->selectedVeteranTypes = app(\App\Services\VeteranPolicyService::class)
            ->normalizeVeteranTypes($types);
        [$primary, $secondary] = app(\App\Services\VeteranPolicyService::class)
            ->splitVeteranTypes($this->selectedVeteranTypes);
        $this->veteranType = $primary ?? '';
        $this->secondaryVeteranType = $secondary ?? '';
    }

    private function normalizeSelectedVeteranTypes(): void
    {
        $policy = app(\App\Services\VeteranPolicyService::class);
        $this->selectedVeteranTypes = $policy->normalizeVeteranTypes($this->selectedVeteranTypes);

        if (count($this->selectedVeteranTypes) > 2) {
            $this->selectedVeteranTypes = array_slice($this->selectedVeteranTypes, 0, 2);
        }

        [$primary, $secondary] = $policy->splitVeteranTypes($this->selectedVeteranTypes);
        $this->veteranType = $primary ?? '';
        $this->secondaryVeteranType = $secondary ?? '';
    }

    private function syncDiscountFromGroups(): void
    {
        $types = $this->selectedVeteranTypes ?: array_values(array_filter([
            $this->veteranType ?: null,
            $this->secondaryVeteranType ?: null,
        ]));

        $this->discountPct = empty($types)
            ? 0
            : VeteranGroups::accommodationDiscountForTypes($types);
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

        if ($this->user->isHost()) {
            $managedIds = Auth::user()->managedAccommodationIds()->all();

            $assignedAccommodations = $this->user->accommodations()
                ->with(['city', 'hosts'])
                ->whereIn('accommodations.id', $managedIds)
                ->orderBy('name')
                ->get();

            $availableAccommodations = Auth::user()
                ->accommodations()
                ->with(['city', 'hosts'])
                ->withCount('hosts')
                ->whereDoesntHave('hosts', fn ($query) => $query->where('users.id', $this->user->id))
                ->orderBy('name')
                ->get();
        }

        $provinceIds = Auth::user()->managedAccommodationIds()->isNotEmpty()
            ? \App\Support\HostUserFilter::resolveAccountingProvinceIds(
                Auth::user()->managedAccommodationIds()->all(),
            )
            : [];

        $provinces = $provinceIds === []
            ? collect()
            : Province::query()->whereIn('id', $provinceIds)->orderBy('name')->get();

        return view('host.users.edit', [
            'user'                    => $this->user,
            'veteranGroups'           => VeteranGroups::options(),
            'assignedAccommodations'  => $assignedAccommodations,
            'availableAccommodations' => $availableAccommodations,
            'hostPositionOptions'     => HostPositionTitles::optionsForForm($this->hostPositionPreset),
            'provinces'               => $provinces,
            'canEdit'                 => Auth::user()->hostCan('users.edit', 'edit'),
        ]);
    }
}

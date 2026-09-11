<?php

namespace App\Livewire\Admin;

use App\Models\Province;
use App\Models\ProvincePosSettlement;
use App\Services\PcPosShareAllocator;
use App\Services\PlatformCommissionService;
use App\Support\IranIban;
use App\Support\PdfPersian;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'تسهیم وجه پوز', 'pageTitle' => 'تسهیم وجه پوز به‌ازای استان'])]
class ProvincePosSettlementIndex extends Component
{
    public ?int $selectedProvinceId = null;

    public string $serviceFeeLabel = 'حق سرویس';

    public string $serviceFeeIban = '';

    public bool $formIsActive = true;

    public string $formNotes = '';

    /** @var array<int, array{label: string, iban: string, percentage: string}> */
    public array $remainderAccounts = [];

    public string $previewAmount = '1000000';

    public function mount(?int $province = null): void
    {
        $first = Province::query()->orderBy('name')->first();
        $this->selectProvince($province ?: ($first?->id ?? 0));
    }

    public function selectProvince(int $id): void
    {
        if ($id <= 0) {
            $this->selectedProvinceId = null;
            $this->resetForm();

            return;
        }

        $this->selectedProvinceId = $id;
        $settlement = ProvincePosSettlement::query()
            ->with('accounts')
            ->where('province_id', $id)
            ->first();

        if (! $settlement) {
            $this->resetForm();

            return;
        }

        $this->serviceFeeLabel = (string) ($settlement->service_fee_label ?: 'حق سرویس');
        $this->serviceFeeIban = $settlement->service_fee_iban;
        $this->formIsActive = (bool) $settlement->is_active;
        $this->formNotes = (string) ($settlement->notes ?? '');
        $this->remainderAccounts = $settlement->accounts
            ->map(fn ($account) => [
                'label' => (string) $account->label,
                'iban' => (string) $account->iban,
                'percentage' => rtrim(rtrim(number_format((float) $account->percentage, 2, '.', ''), '0'), '.') ?: '0',
            ])
            ->all();

        if ($this->remainderAccounts === []) {
            $this->remainderAccounts = $this->defaultRemainderAccounts();
        }

        $this->resetErrorBag();
    }

    public function addRemainderAccount(): void
    {
        $this->remainderAccounts[] = [
            'label' => 'حساب ' . (count($this->remainderAccounts) + 1),
            'iban' => '',
            'percentage' => '',
        ];
    }

    public function removeRemainderAccount(int $index): void
    {
        unset($this->remainderAccounts[$index]);
        $this->remainderAccounts = array_values($this->remainderAccounts);
        if ($this->remainderAccounts === []) {
            $this->remainderAccounts = $this->defaultRemainderAccounts();
        }
    }

    public function save(): void
    {
        if (! $this->selectedProvinceId) {
            throw ValidationException::withMessages([
                'selectedProvinceId' => 'استان را انتخاب کنید.',
            ]);
        }

        $this->serviceFeeIban = IranIban::normalize($this->serviceFeeIban);
        foreach ($this->remainderAccounts as $i => $account) {
            $this->remainderAccounts[$i]['iban'] = IranIban::normalize((string) ($account['iban'] ?? ''));
            $this->remainderAccounts[$i]['label'] = trim((string) ($account['label'] ?? ''));
            $this->remainderAccounts[$i]['percentage'] = str_replace(',', '.', PdfPersian::toEnglishDigits((string) ($account['percentage'] ?? '')));
        }

        $this->validate([
            'serviceFeeLabel' => ['required', 'string', 'max:80'],
            'serviceFeeIban' => ['required', 'regex:/^IR\d{24}$/'],
            'remainderAccounts' => ['required', 'array', 'min:1'],
            'remainderAccounts.*.label' => ['required', 'string', 'max:80'],
            'remainderAccounts.*.iban' => ['required', 'regex:/^IR\d{24}$/', 'distinct'],
            'remainderAccounts.*.percentage' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'formIsActive' => ['boolean'],
            'formNotes' => ['nullable', 'string', 'max:250'],
        ], [
            'serviceFeeIban.regex' => 'شماره شبا حق سرویس باید با IR و ۲۴ رقم باشد.',
            'remainderAccounts.*.iban.regex' => 'شماره شبا باید با IR و ۲۴ رقم باشد.',
            'remainderAccounts.*.iban.distinct' => 'شماره شبای حساب‌های باقیمانده نباید تکراری باشد.',
        ], [
            'serviceFeeLabel' => 'عنوان حق سرویس',
            'serviceFeeIban' => 'شبا حق سرویس',
            'remainderAccounts.*.label' => 'عنوان حساب',
            'remainderAccounts.*.iban' => 'شبا',
            'remainderAccounts.*.percentage' => 'درصد',
        ]);

        $percentSum = round(collect($this->remainderAccounts)->sum(fn ($row) => (float) $row['percentage']), 2);
        if (abs($percentSum - 100.0) > 0.009) {
            throw ValidationException::withMessages([
                'remainderAccounts' => 'مجموع درصد حساب‌های باقیمانده باید دقیقاً ۱۰۰ باشد. مجموع فعلی: ' . number_format($percentSum, 2) . '٪',
            ]);
        }

        DB::transaction(function () {
            $settlement = ProvincePosSettlement::query()->firstOrNew([
                'province_id' => $this->selectedProvinceId,
            ]);
            if (! $settlement->exists) {
                $settlement->created_by = Auth::id();
            }
            $settlement->fill([
                'service_fee_label' => trim($this->serviceFeeLabel) ?: 'حق سرویس',
                'service_fee_iban' => $this->serviceFeeIban,
                'is_active' => $this->formIsActive,
                'notes' => trim($this->formNotes) ?: null,
            ]);
            $settlement->save();

            $settlement->accounts()->delete();
            foreach ($this->remainderAccounts as $index => $account) {
                $settlement->accounts()->create([
                    'label' => $account['label'],
                    'iban' => $account['iban'],
                    'percentage' => (float) $account['percentage'],
                    'sort_order' => $index,
                ]);
            }
        });

        $this->selectProvince((int) $this->selectedProvinceId);
        $this->dispatch('toast', type: 'success', message: 'تسهیم وجه استان ذخیره شد.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function previewShares(): array
    {
        $amount = (int) preg_replace('/\D/', '', PdfPersian::toEnglishDigits($this->previewAmount));
        if ($amount <= 0 || ! $this->selectedProvinceId) {
            return [];
        }

        $settlement = ProvincePosSettlement::query()
            ->with(['accounts', 'province'])
            ->where('province_id', $this->selectedProvinceId)
            ->first();

        if (! $settlement) {
            return [];
        }

        try {
            $serviceFee = min($amount, app(PlatformCommissionService::class)->fixedAmount());

            return app(PcPosShareAllocator::class)->allocate($settlement, $amount, $serviceFee);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<int, array{label: string, iban: string, percentage: string}> */
    private function defaultRemainderAccounts(): array
    {
        return [
            ['label' => 'حساب اول', 'iban' => '', 'percentage' => '50'],
            ['label' => 'حساب دوم', 'iban' => '', 'percentage' => '50'],
        ];
    }

    private function resetForm(): void
    {
        $this->serviceFeeLabel = 'حق سرویس';
        $this->serviceFeeIban = '';
        $this->formIsActive = true;
        $this->formNotes = '';
        $this->remainderAccounts = $this->defaultRemainderAccounts();
        $this->resetErrorBag();
    }

    public function render()
    {
        $provinces = Province::query()
            ->with(['posSettlement.accounts'])
            ->orderBy('name')
            ->get();

        return view('admin.pos-settlements.index', [
            'provinces' => $provinces,
            'previewShares' => $this->previewShares(),
            'remainderPercentTotal' => round(collect($this->remainderAccounts)->sum(fn ($row) => (float) ($row['percentage'] ?? 0)), 2),
        ]);
    }
}

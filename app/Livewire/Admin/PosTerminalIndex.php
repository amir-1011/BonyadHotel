<?php

namespace App\Livewire\Admin;

use App\Models\PosTerminal;
use App\Models\Province;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin', ['title' => 'ترمینال‌های پز', 'pageTitle' => 'مدیریت ترمینال‌های پز'])]
class PosTerminalIndex extends Component
{
    use WithPagination;

    #[Url(as: 'province')]
    public string $provinceFilter = '';

    #[Url(as: 'q')]
    public string $search = '';

    public string $draftProvinceFilter = '';

    public string $draftSearch = '';

    public ?int $editingId = null;

    public string $formProvinceId = '';
    public string $formTerminalNumber = '';
    public string $formLabel = '';
    public bool $formIsActive = true;

    public function mount(): void
    {
        $this->syncDraftFromApplied();
        $this->resetForm();
    }

    public function applyFilters(): void
    {
        $this->provinceFilter = $this->draftProvinceFilter;
        $this->search = $this->draftSearch;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['provinceFilter', 'search']);
        $this->syncDraftFromApplied();
        $this->resetPage();
    }

    protected function syncDraftFromApplied(): void
    {
        $this->draftProvinceFilter = $this->provinceFilter;
        $this->draftSearch = $this->search;
    }

    public function hasActiveFilters(): bool
    {
        return $this->provinceFilter !== '' || $this->search !== '';
    }

    public function saveFromSwal(
        ?int $editingId,
        string $formProvinceId,
        string $formTerminalNumber,
        string $formLabel,
        bool $formIsActive,
    ): void {
        $this->editingId = $editingId;
        $this->formProvinceId = $formProvinceId;
        $this->formTerminalNumber = $formTerminalNumber;
        $this->formLabel = $formLabel;
        $this->formIsActive = $formIsActive;

        try {
            $this->save();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'ورودی معتبر نیست.';
            $this->dispatch('toast', type: 'error', message: $message);
            throw $e;
        }
    }

    public function save(): void
    {
        $provinceId = (int) $this->formProvinceId;

        $rules = [
            'formProvinceId' => ['required', 'integer', 'exists:provinces,id'],
            'formTerminalNumber' => ['required', 'string', 'max:64'],
            'formLabel' => ['nullable', 'string', 'max:120'],
            'formIsActive' => ['boolean'],
        ];

        if ($this->editingId) {
            $rules['formTerminalNumber'][] = \Illuminate\Validation\Rule::unique('pos_terminals', 'terminal_number')
                ->where('province_id', $provinceId)
                ->ignore($this->editingId);
        } else {
            $rules['formTerminalNumber'][] = \Illuminate\Validation\Rule::unique('pos_terminals', 'terminal_number')
                ->where('province_id', $provinceId);
        }

        $this->validate($rules, [], [
            'formProvinceId' => 'استان',
            'formTerminalNumber' => 'شماره ترمینال',
            'formLabel' => 'عنوان',
        ]);

        $payload = [
            'province_id' => $provinceId,
            'terminal_number' => trim($this->formTerminalNumber),
            'label' => trim($this->formLabel) ?: null,
            'is_active' => $this->formIsActive,
        ];

        if ($this->editingId) {
            PosTerminal::query()->whereKey($this->editingId)->update($payload);
            $message = 'ترمینال به‌روز شد.';
        } else {
            $payload['created_by'] = auth()->id();
            PosTerminal::create($payload);
            $message = 'ترمینال جدید ثبت شد.';
        }

        $this->closeForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    private function closeForm(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $terminal = PosTerminal::query()->findOrFail($id);
        if ($terminal->paymentRecords()->exists()) {
            $this->dispatch('toast', type: 'error', message: 'این ترمینال در تراکنش‌ها استفاده شده و قابل حذف نیست.');
            return;
        }
        $terminal->delete();
        $this->dispatch('toast', type: 'success', message: 'ترمینال حذف شد.');
    }

    private function resetForm(): void
    {
        $this->formProvinceId = $this->provinceFilter !== '' ? $this->provinceFilter : '';
        $this->formTerminalNumber = '';
        $this->formLabel = '';
        $this->formIsActive = true;
        $this->resetErrorBag();
    }

    public function render()
    {
        $query = PosTerminal::query()
            ->with('province')
            ->withSum('paymentRecords as transactions_total', 'amount')
            ->withCount('paymentRecords as transactions_count')
            ->orderBy('province_id')
            ->orderBy('terminal_number');

        if ($this->provinceFilter !== '') {
            $query->where('province_id', (int) $this->provinceFilter);
        }

        if ($this->search !== '') {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('terminal_number', 'like', $term)
                    ->orWhere('label', 'like', $term);
            });
        }

        return view('admin.pos-terminals.index', [
            'terminals' => $query->paginate(20),
            'provinces' => Province::query()->orderBy('name')->get(),
            'hasActiveFilters' => $this->hasActiveFilters(),
        ]);
    }
}

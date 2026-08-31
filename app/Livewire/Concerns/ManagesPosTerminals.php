<?php

namespace App\Livewire\Concerns;

use App\Models\PosTerminal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

trait ManagesPosTerminals
{
    use ResolvesAccountingProvince;

    #[\Livewire\Attributes\On('bnb-open-pos-terminal-modal')]
    public function openPosTerminalModalFromPaymentFlow(): void
    {
        $this->openPosTerminalModal();
    }

    public bool $showAddPosTerminal = false;

    public string $posTerminalId = '';

    public string $newPosTerminalNumber = '';
    public string $newPosTerminalLabel = '';

    public function openPosTerminalModal(): void
    {
        if (!$this->assertAccommodationSelectedForAccounting()) {
            return;
        }

        $this->accountingProvinceManuallySet = false;
        $this->syncDefaultAccountingProvinceFromContext();
        $this->showAddPosTerminal = true;
        $this->newPosTerminalNumber = '';
        $this->newPosTerminalLabel = '';
        $this->resetErrorBag(['newPosTerminalNumber', 'newPosTerminalLabel']);
    }

    public function closePosTerminalModal(): void
    {
        $this->showAddPosTerminal = false;
        $this->resetErrorBag(['newPosTerminalNumber', 'newPosTerminalLabel']);
    }

    public function addPosTerminalToCatalog(): void
    {
        if (!$this->assertAccommodationSelectedForAccounting()) {
            return;
        }

        $province = $this->resolveAccountingProvince();

        $this->validate([
            'newPosTerminalNumber' => [
                'required',
                'string',
                'max:64',
                Rule::unique('pos_terminals', 'terminal_number')->where('province_id', $province->id),
            ],
            'newPosTerminalLabel' => ['nullable', 'string', 'max:120'],
            'accountingProvinceId' => ['required', 'integer', 'exists:provinces,id'],
        ], [], [
            'newPosTerminalNumber' => 'شماره ترمینال',
            'newPosTerminalLabel' => 'عنوان ترمینال',
        ]);

        $terminal = PosTerminal::create([
            'province_id' => $province->id,
            'terminal_number' => trim($this->newPosTerminalNumber),
            'label' => trim($this->newPosTerminalLabel) ?: null,
            'is_active' => true,
            'created_by' => Auth::id(),
        ]);

        $this->posTerminalId = (string) $terminal->id;
        $this->closePosTerminalModal();
        $this->dispatch('bnb-pos-terminals-updated');
    }

    protected function resolvedPosTerminalId(): ?int
    {
        return $this->posTerminalId !== '' ? (int) $this->posTerminalId : null;
    }
}

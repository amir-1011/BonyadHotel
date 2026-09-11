<?php

namespace App\Livewire\Admin;

use App\Models\Accommodation;
use App\Models\AccommodationPosMapping;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin', ['title' => 'مپینگ پوز اقامتگاه', 'pageTitle' => 'مپینگ اقامتگاه به پوز'])]
class AccommodationPosMappingIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public string $draftSearch = '';

    public ?int $editingId = null;

    public string $formAccommodationId = '';

    public string $formWindowsLanIp = '';

    public string $formPosLanIp = '';

    public string $formPosPort = '1362';

    public string $formAgentPort = '8088';

    public bool $formIsActive = true;

    public string $formNotes = '';

    public function mount(): void
    {
        $this->draftSearch = $this->search;
        $this->resetForm();
    }

    public function applyFilters(): void
    {
        $this->search = $this->draftSearch;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search']);
        $this->draftSearch = '';
        $this->resetPage();
    }

    public function edit(int $id): void
    {
        $mapping = AccommodationPosMapping::query()->findOrFail($id);
        $this->editingId = $mapping->id;
        $this->formAccommodationId = (string) $mapping->accommodation_id;
        $this->formWindowsLanIp = $mapping->windows_lan_ip;
        $this->formPosLanIp = $mapping->pos_lan_ip;
        $this->formPosPort = (string) $mapping->posPort();
        $this->formAgentPort = (string) $mapping->agentPort();
        $this->formIsActive = (bool) $mapping->is_active;
        $this->formNotes = (string) ($mapping->notes ?? '');
        $this->resetErrorBag();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->resetForm();
    }

    public function save(): void
    {
        $accommodationId = (int) $this->formAccommodationId;
        $unique = Rule::unique('accommodation_pos_mappings', 'accommodation_id');
        if ($this->editingId) {
            $unique = $unique->ignore($this->editingId);
        }

        $this->validate([
            'formAccommodationId' => ['required', 'integer', 'exists:accommodations,id', $unique],
            'formWindowsLanIp' => ['required', 'ip'],
            'formPosLanIp' => ['required', 'ip'],
            'formPosPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'formAgentPort' => ['required', 'integer', 'min:1', 'max:65535'],
            'formIsActive' => ['boolean'],
            'formNotes' => ['nullable', 'string', 'max:250'],
        ], [], [
            'formAccommodationId' => 'اقامتگاه',
            'formWindowsLanIp' => 'آی‌پی ویندوز (Tailscale)',
            'formPosLanIp' => 'آی‌پی پوز (شبکه مودم)',
            'formPosPort' => 'پورت پوز',
            'formAgentPort' => 'پورت Agent',
            'formNotes' => 'یادداشت',
        ]);

        $payload = [
            'accommodation_id' => $accommodationId,
            'windows_lan_ip' => trim($this->formWindowsLanIp),
            'pos_lan_ip' => trim($this->formPosLanIp),
            'pos_port' => (int) $this->formPosPort,
            'agent_port' => (int) $this->formAgentPort,
            'is_active' => $this->formIsActive,
            'notes' => trim($this->formNotes) ?: null,
        ];

        if ($this->editingId) {
            AccommodationPosMapping::query()->whereKey($this->editingId)->update($payload);
            $message = 'مپینگ پوز به‌روز شد.';
        } else {
            $payload['created_by'] = auth()->id();
            AccommodationPosMapping::create($payload);
            $message = 'مپینگ پوز ثبت شد.';
        }

        $this->cancelEdit();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function delete(int $id): void
    {
        AccommodationPosMapping::query()->findOrFail($id)->delete();
        if ($this->editingId === $id) {
            $this->cancelEdit();
        }
        $this->dispatch('toast', type: 'success', message: 'مپینگ پوز حذف شد.');
    }

    private function resetForm(): void
    {
        $this->formAccommodationId = '';
        $this->formWindowsLanIp = '';
        $this->formPosLanIp = '';
        $this->formPosPort = (string) config('pcpos.default_pos_port', 1362);
        $this->formAgentPort = (string) config('pcpos.default_agent_port', 8088);
        $this->formIsActive = true;
        $this->formNotes = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        $query = AccommodationPosMapping::query()
            ->with(['accommodation.city', 'createdBy'])
            ->orderByDesc('id');

        if ($this->search !== '') {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('windows_lan_ip', 'like', $term)
                    ->orWhere('pos_lan_ip', 'like', $term)
                    ->orWhereHas('accommodation', fn ($acc) => $acc->where('name', 'like', $term));
            });
        }

        $mappedIds = AccommodationPosMapping::query()->pluck('accommodation_id');
        $accommodations = Accommodation::query()
            ->with('city')
            ->orderBy('name')
            ->get()
            ->filter(function (Accommodation $acc) use ($mappedIds) {
                if ((string) $acc->id === $this->formAccommodationId) {
                    return true;
                }

                return ! $mappedIds->contains($acc->id);
            })
            ->values();

        return view('admin.pos-mappings.index', [
            'mappings' => $query->paginate(20),
            'accommodations' => $accommodations,
            'hasActiveFilters' => $this->search !== '',
        ]);
    }
}

<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ManagesHostPermissionForm;
use App\Models\HostPositionTitle;
use App\Models\User;
use App\Support\HostLabels;
use App\Support\HostPermissions;
use App\Support\HostPositionTitles;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'سمت‌ها و دسترسی کاربر', 'pageTitle' => 'سمت‌ها و دسترسی پنل کاربر'])]
class HostPositionPermissionIndex extends Component
{
    use ManagesHostPermissionForm;

    public ?int $selectedPositionId = null;

    public bool $showAddPosition = false;

    public string $newPositionLabel = '';

    public string $editingPositionLabel = '';

    public function mount(): void
    {
        $first = HostPositionTitle::query()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->first();

        if ($first) {
            $this->selectPosition($first->id);
        } else {
            $this->mountHostPermissionForm();
        }
    }

    public function selectPosition(int $id): void
    {
        $position = HostPositionTitle::query()->findOrFail($id);

        $this->selectedPositionId = $position->id;
        $this->editingPositionLabel = HostLabels::displayPositionLabel($position->label);
        $this->mountHostPermissionForm($position->host_panel_permissions);
        $this->showAddPosition = false;
        $this->resetErrorBag();
    }

    public function toggleAddPosition(): void
    {
        $this->showAddPosition = !$this->showAddPosition;
        $this->newPositionLabel = '';
        $this->resetErrorBag('newPositionLabel');
    }

    public function addPosition(): void
    {
        $label = trim($this->newPositionLabel);

        $this->validate([
            'newPositionLabel' => [
                'required',
                'string',
                'max:100',
                Rule::unique('host_position_titles', 'label'),
            ],
        ], [
            'newPositionLabel.required' => 'نام سمت را وارد کنید.',
            'newPositionLabel.unique'   => 'این سمت قبلاً ثبت شده است.',
        ]);

        $position = HostPositionTitle::query()->create([
            'label'                  => $label,
            'is_system'              => false,
            'sort_order'             => (int) HostPositionTitle::query()->max('sort_order') + 1,
            'host_panel_permissions' => HostPermissions::defaults(),
        ]);

        $this->newPositionLabel = '';
        $this->showAddPosition = false;
        $this->selectPosition($position->id);

        $this->dispatch('toast', type: 'success', message: 'سمت جدید ایجاد شد. دسترسی‌های آن را تنظیم و ذخیره کنید.');
    }

    public function updatePositionLabel(): void
    {
        if ($this->selectedPositionId === null) {
            return;
        }

        $label = HostLabels::storedPositionLabel($this->editingPositionLabel);

        $this->validate([
            'editingPositionLabel' => [
                'required',
                'string',
                'max:100',
                Rule::unique('host_position_titles', 'label')->ignore($this->selectedPositionId),
            ],
        ], [
            'editingPositionLabel.required' => 'نام سمت را وارد کنید.',
            'editingPositionLabel.unique'   => 'این نام سمت قبلاً ثبت شده است.',
        ]);

        $position = HostPositionTitle::query()->findOrFail($this->selectedPositionId);
        $oldLabel = $position->label;

        if ($oldLabel === HostPositionTitles::DEFAULT_LABEL && $label !== HostPositionTitles::DEFAULT_LABEL) {
            $this->addError('editingPositionLabel', 'نام سمت پیش‌فرض «کاربر» قابل تغییر نیست.');
            return;
        }

        if ($oldLabel === $label) {
            $this->dispatch('toast', type: 'info', message: 'نام سمت تغییری نکرد.');
            return;
        }

        $position->update(['label' => $label]);

        User::query()
            ->where('host_position_title', $oldLabel)
            ->update(['host_position_title' => $label]);

        $this->dispatch('toast', type: 'success', message: 'نام سمت به «' . HostLabels::displayPositionLabel($label) . '» تغییر کرد.');
    }

    public function save(): void
    {
        if ($this->selectedPositionId === null) {
            $this->addError('hostPermissionForm', 'ابتدا یک سمت انتخاب کنید.');
            return;
        }

        $this->validateHostPermissionForm();

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $position = HostPositionTitle::query()->findOrFail($this->selectedPositionId);

        $grants = $this->hostPermissionGrantsFromForm();

        $position->update([
            'host_panel_permissions' => $grants,
        ]);

        $synced = HostPositionTitles::syncUsersForPosition($position->label, $grants);

        $message = 'دسترسی‌های سمت «' . HostLabels::displayPositionLabel($position->label) . '» ذخیره شد.';
        if ($synced > 0) {
            $message .= " ({$synced} کاربر به‌روز شد)";
        }

        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function render()
    {
        $positions = HostPositionTitle::query()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        $selectedPosition = $positions->firstWhere('id', $this->selectedPositionId);

        return view('admin.host-positions.index', [
            'positions'          => $positions,
            'selectedPosition'   => $selectedPosition,
            'hostPermissionCatalog' => HostPermissions::catalog(),
        ]);
    }
}

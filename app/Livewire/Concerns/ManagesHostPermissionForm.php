<?php

namespace App\Livewire\Concerns;

use App\Support\HostPermissions;

trait ManagesHostPermissionForm
{
    /** @var array<string, bool> */
    public array $hostPermissionForm = [];

    public function mountHostPermissionForm(?array $stored = null): void
    {
        $grants = HostPermissions::normalizeStored($stored ?? HostPermissions::defaults());
        $this->hostPermissionForm = HostPermissions::grantsToFormState($grants);
    }

    public function toggleHostPermissionModule(string $moduleKey): void
    {
        $enabled = !HostPermissions::moduleIsFullyEnabled($this->hostPermissionForm, $moduleKey);
        HostPermissions::toggleModuleInFormState($this->hostPermissionForm, $moduleKey, $enabled);
        $this->hostPermissionForm = [...$this->hostPermissionForm];
    }

    public function toggleHostPermissionPage(string $pageKey): void
    {
        $page = HostPermissions::pageDefinition($pageKey);

        if (!$page) {
            return;
        }

        $enabled = true;

        foreach ($page['actions'] as $action) {
            $key = HostPermissions::formKey($pageKey, $action);

            if (!empty($this->hostPermissionForm[$key])) {
                $enabled = false;
                break;
            }
        }

        HostPermissions::togglePageInFormState($this->hostPermissionForm, $pageKey, $enabled);
        $this->hostPermissionForm = [...$this->hostPermissionForm];
    }

  /**
     * @return array<string, list<string>>
     */
    protected function hostPermissionGrantsFromForm(): array
    {
        return HostPermissions::grantsFromFormState($this->hostPermissionForm);
    }

    protected function validateHostPermissionForm(): void
    {
        $this->validate([
            'hostPermissionForm' => ['required', 'array'],
        ], [
            'hostPermissionForm.required' => 'حداقل یک دسترسی از پنل کاربر را انتخاب کنید.',
        ]);

        if ($this->hostPermissionGrantsFromForm() === []) {
            $this->addError('hostPermissionForm', 'حداقل یک دسترسی از پنل کاربر را انتخاب کنید.');
        }
    }
}

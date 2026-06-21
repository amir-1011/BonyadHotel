<?php

namespace App\Livewire\Admin;

use App\Services\AccommodationCsvImportService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.admin', ['title' => 'درون‌ریزی گروهی اقامتگاه‌ها', 'pageTitle' => 'درون‌ریزی گروهی اقامتگاه‌ها'])]
class AccommodationImport extends Component
{
    use WithFileUploads;

    public $csvFile;

    /** @var array{success:bool, imported:int, errors:list<string>, warnings:list<string>, summary:array<string,int>}|null */
    public ?array $result = null;

    protected function rules(): array
    {
        return [
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ];
    }

    public function preview(): void
    {
        $this->validate();
        $this->runImport(dryRun: true);
    }

    public function import(): void
    {
        $this->validate();
        $this->runImport(dryRun: false);
    }

    private function runImport(bool $dryRun): void
    {
        $this->result = null;

        try {
            $path = $this->csvFile->getRealPath();
            $this->result = app(AccommodationCsvImportService::class)->import($path, $dryRun);

            if ($this->result['success'] && !$dryRun) {
                session()->flash('status', "{$this->result['imported']} اقامتگاه با موفقیت درون‌ریزی شد.");
                $this->dispatch('toast', type: 'success', message: "{$this->result['imported']} اقامتگاه ثبت شد.");
            }
        } catch (\InvalidArgumentException $e) {
            $this->result = [
                'success'  => false,
                'imported' => 0,
                'errors'   => [$e->getMessage()],
                'warnings' => [],
                'summary'  => [],
            ];
        }
    }

    public function render()
    {
        return view('admin.accommodations.import');
    }
}

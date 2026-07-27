<?php

namespace App\Livewire\Admin;

use App\Models\Accommodation;
use App\Models\Program;
use App\Models\ProgramBeneficiary;
use App\Models\ProgramEmployer;
use App\Support\ProgramFilter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin', ['title' => 'برنامه‌ها و اردوها', 'pageTitle' => 'برنامه‌ها'])]
class ProgramIndex extends Component
{
    use WithPagination;

    #[Url] public string $search = '';
    #[Url] public string $status = '';
    #[Url] public string $programType = '';
    #[Url] public string $paymentType = '';
    #[Url] public int $accommodationId = 0;
    #[Url] public int $employerId = 0;
    #[Url] public string $contractor = '';
    #[Url] public int $beneficiaryId = 0;

    public function updated($property): void
    {
        if (!in_array($property, ['page'], true)) {
            $this->resetPage();
        }
    }

    public function updateStatus(int $programId, string $newStatus): void
    {
        $allowed = [Program::STATUS_ACTIVE, Program::STATUS_COMPLETED, Program::STATUS_CANCELLED];
        if (!in_array($newStatus, $allowed, true)) {
            return;
        }

        $program = Program::with('booking')->findOrFail($programId);
        $program->update(['status' => $newStatus]);

        if ($newStatus === Program::STATUS_CANCELLED && $program->booking) {
            $program->booking->update(['status' => 'cancelled']);
        }

        session()->flash('status', 'وضعیت برنامه به‌روز شد.');
        $this->dispatch('toast', type: 'success', message: 'وضعیت برنامه به‌روز شد.');
    }

    public function render()
    {
        $filters = [
            'search'           => $this->search,
            'status'           => $this->status,
            'program_type'     => $this->programType,
            'payment_type'     => $this->paymentType,
            'accommodation_id' => $this->accommodationId,
            'employer_id'      => $this->employerId,
            'contractor'       => $this->contractor,
            'beneficiary_id'   => $this->beneficiaryId,
        ];

        $programs = ProgramFilter::make($filters)
            ->apply(Program::query()->with(['accommodation.city', 'booking']))
            ->paginate(20);

        $accommodations = Accommodation::orderBy('name')->get(['id', 'name']);
        $beneficiaries = ProgramBeneficiary::orderBy('name')->get();
        $employers = ProgramEmployer::orderBy('name')->get();

        return view('admin.programs.index', compact('programs', 'accommodations', 'beneficiaries', 'employers', 'filters'));
    }
}

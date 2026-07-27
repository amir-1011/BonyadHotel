<?php

namespace App\Livewire\Host;

use App\Livewire\Concerns\AssertsHostPermissions;
use App\Models\Program;
use App\Models\ProgramBeneficiary;
use App\Models\ProgramEmployer;
use App\Support\ProgramFilter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.host', ['title' => 'برنامه‌ها و اردوها', 'pageTitle' => 'برنامه‌ها'])]
class ProgramIndex extends Component
{
    use WithPagination;
    use AssertsHostPermissions;

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

    public function destroy(int $id): void
    {
        $this->assertHostCan('programs.list', 'delete');
        $accIds = Auth::user()->managedAccommodationIds();
        $program = Program::whereIn('accommodation_id', $accIds)->with('booking')->findOrFail($id);

        if ($program->booking) {
            $program->booking->update(['status' => 'cancelled']);
        }

        $program->update(['status' => Program::STATUS_CANCELLED]);
        session()->flash('status', 'برنامه لغو شد.');
        $this->dispatch('toast', type: 'success', message: 'برنامه لغو شد.');
        $this->resetPage();
    }

    public function render()
    {
        $accIds = Auth::user()->managedAccommodationIds();

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

        $programs = ProgramFilter::make($filters, $accIds->all())
            ->apply(Program::query()->with(['accommodation', 'booking']))
            ->paginate(20);

        $myAccommodations = Auth::user()->managedAccommodationOptions();
        $beneficiaries = ProgramBeneficiary::orderBy('name')->get();
        $employers = ProgramEmployer::orderBy('name')->get();

        return view('host.programs.index', compact('programs', 'myAccommodations', 'beneficiaries', 'employers', 'filters'));
    }
}

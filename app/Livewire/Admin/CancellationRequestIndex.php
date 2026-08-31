<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\ParsesCancellationSettleInput;
use App\Models\Accommodation;
use App\Models\CancellationRequest;
use App\Services\CancellationRequestService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin', ['title' => 'درخواست‌های کنسلی', 'pageTitle' => 'درخواست‌های کنسلی و استرداد وجه'])]
class CancellationRequestIndex extends Component
{
    use WithPagination;
    use ParsesCancellationSettleInput;

    #[Url]
    public string $status = '';

    #[Url]
    public string $accommodationId = '';

    #[Url]
    public string $search = '';

    public ?int $settlingRequestId = null;
    public bool $showSettleModal = false;
    public int $settleAmount = 0;
    public string $settleAccountNumber = '';
    public string $settleNotes = '';

    public function updatedStatus(): void { $this->resetPage(); }
    public function updatedAccommodationId(): void { $this->resetPage(); }
    public function updatedSearch(): void { $this->resetPage(); }

    public function approve(int $requestId): void
    {
        $request = CancellationRequest::with('booking')->findOrFail($requestId);

        try {
            app(CancellationRequestService::class)->approve($request, Auth::user());
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
            return;
        }

        $this->presentSettleModalAfterApproval($request->fresh());
    }

    public function submitReject(int $requestId, string $rejectionReason): void
    {
        if (trim($rejectionReason) === '') {
            $this->dispatch('toast', type: 'warning', message: 'ذکر دلیل رد درخواست الزامی است.');
            return;
        }

        $request = CancellationRequest::with('booking')->findOrFail($requestId);

        try {
            app(CancellationRequestService::class)->reject($request, Auth::user(), $rejectionReason);
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: collect($e->errors())->flatten()->first() ?? 'خطا در رد درخواست.');
            return;
        }

        $this->dispatch('toast', type: 'success', message: 'درخواست کنسلی رد شد.');
    }

    public function openSettleModal(int $requestId): void
    {
        $request = CancellationRequest::with('booking')->findOrFail($requestId);
        if (!$request->isApproved() || $request->isSettled()) {
            $this->dispatch('toast', type: 'error', message: 'این درخواست قابل تسویه نیست.');
            return;
        }

        if ($request->hasZeroRefund()) {
            try {
                app(CancellationRequestService::class)->markSettledWithoutPayment($request, Auth::user());
            } catch (ValidationException $e) {
                $this->dispatch('toast', type: 'error', message: collect($e->errors())->flatten()->first() ?? 'خطا در تسویه درخواست.');
                return;
            }

            $this->dispatch('toast', type: 'success', message: 'درخواست با مبلغ استرداد صفر تسویه شد.');
            return;
        }

        $this->presentSettleModal($request);
    }

    public function closeSettleModal(): void
    {
        $this->showSettleModal = false;
        $this->settlingRequestId = null;
    }

    public function submitSettle(): void
    {
        $this->validate(
            $this->settleFormValidationRules(),
            $this->settleFormValidationMessages(),
        );

        $request = CancellationRequest::with('booking')->findOrFail($this->settlingRequestId);

        try {
            $depositedAt = $this->resolveSettleDepositedAt();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }
            return;
        }

        try {
            app(CancellationRequestService::class)->markSettled($request, Auth::user(), [
                'deposited_at'   => $depositedAt->toDateTimeString(),
                'amount'         => $this->settleAmount,
                'account_number' => $this->settleAccountNumber,
                'notes'          => $this->settleNotes ?: null,
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $livewireField = match ($field) {
                    'deposited_at'   => 'settleDepositedDate',
                    'amount'         => 'settleAmount',
                    'account_number' => 'settleAccountNumber',
                    default          => $field,
                };
                if ($field === 'cancellation_request') {
                    $this->dispatch('toast', type: 'error', message: $messages[0]);
                } else {
                    $this->addError($livewireField, $messages[0]);
                }
            }
            return;
        }

        $this->showSettleModal = false;
        $this->settlingRequestId = null;
        $this->dispatch('toast', type: 'success', message: 'وضعیت درخواست به «تسویه شده» تغییر یافت.');
    }

    public function render()
    {
        $query = CancellationRequest::query()
            ->with(['booking.accommodation', 'booking.user', 'reason', 'requestedBy', 'decidedBy', 'settledBy'])
            ->latest('id');

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->accommodationId !== '') {
            $query->whereHas('booking', fn ($q) => $q->where('accommodation_id', $this->accommodationId));
        }

        if (trim($this->search) !== '') {
            $term = trim($this->search);
            $query->whereHas('booking', function ($q) use ($term) {
                $q->where('tracking_code', 'like', "%{$term}%")
                    ->orWhere('guest_contact_name', 'like', "%{$term}%")
                    ->orWhere('guest_contact_mobile', 'like', "%{$term}%");
            });
        }

        $requests = $query->paginate(20);
        $accommodations = Accommodation::orderBy('name')->get(['id', 'name']);

        return view('admin.cancellation-requests.index', compact('requests', 'accommodations'));
    }
}

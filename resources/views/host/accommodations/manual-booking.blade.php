<div>
<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
    <a href="{{ route('host.accommodations.index') }}" wire:navigate class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>بازگشت</a>
</div>

<livewire:manual-booking-form :accommodation="$accommodation" panel="host" :key="'manual-booking-'.$accommodation->id" />
</div>

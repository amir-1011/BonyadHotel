<div>

@php
    $hostUser = Auth::user();
@endphp

@if($accommodations->isEmpty())
    <div class="row g-3">
        @if($hostUser->hostCan('accommodations.create', 'write'))
        <div class="col-12 col-md-6">
            <a wire:navigate href="{{ route('host.accommodations.create') }}" class="card shadow-sm h-100 host-acc-card host-acc-card--add">
                <span class="host-acc-card__plus" aria-hidden="true">
                    <i class="bi bi-plus-lg"></i>
                </span>
                <span class="host-acc-card__add-label">ثبت اولین اقامتگاه</span>
            </a>
        </div>
        @endif
    </div>
@else
<div class="row g-3">
    @foreach($accommodations as $acc)
    <div class="col-12 col-md-6">
        <x-host.accommodation-card :acc="$acc" />
    </div>
    @endforeach
    @if($hostUser->hostCan('accommodations.create', 'write'))
    <div class="col-12 col-md-6">
        <a wire:navigate href="{{ route('host.accommodations.create') }}" class="card shadow-sm h-100 host-acc-card host-acc-card--add">
            <span class="host-acc-card__plus" aria-hidden="true">
                <i class="bi bi-plus-lg"></i>
            </span>
            <span class="host-acc-card__add-label">اقامتگاه جدید</span>
        </a>
    </div>
    @endif
</div>
@endif

</div>

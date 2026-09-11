@props(['startDate' => '', 'endDate' => '', 'singleDate' => false, 'startLabel' => null])

@php
    $startLabel = $startLabel ?? ($singleDate ? 'تاریخ برگزاری' : 'تاریخ شروع');
@endphp

<div class="col-md-6">
    <label class="form-label small fw-semibold">{{ $startLabel }} <span class="text-danger">*</span></label>
    <div wire:ignore>
        <input type="text"
               id="program-start-date"
               class="form-control jalali-picker-program @error('startDate') is-invalid @enderror"
               data-wire-prop="startDate"
               value="{{ $startDate }}"
               autocomplete="off"
               placeholder="۱۴۰۴/۰۱/۰۱"
               dir="ltr">
    </div>
    @error('startDate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
@if(!$singleDate)
<div class="col-md-6">
    <label class="form-label small fw-semibold">تاریخ پایان <span class="text-danger">*</span></label>
    <div wire:ignore>
        <input type="text"
               id="program-end-date"
               class="form-control jalali-picker-program @error('endDate') is-invalid @enderror"
               data-wire-prop="endDate"
               value="{{ $endDate }}"
               autocomplete="off"
               placeholder="۱۴۰۴/۰۱/۱۵"
               dir="ltr">
    </div>
    @error('endDate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
@endif

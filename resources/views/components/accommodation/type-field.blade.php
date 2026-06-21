{{-- Accommodation type selector with manual add support --}}
@props(['accommodationTypes'])

<div class="col-md-4">
    <label class="form-label small fw-semibold">نوع</label>
    <select wire:model="type" class="form-select @error('type') is-invalid @enderror">
        @foreach($accommodationTypes as $key => $label)
            <option value="{{ $key }}">{{ $label }}</option>
        @endforeach
    </select>
    @error('type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    <div class="mt-1">
        @if($showAddType)
            <div class="input-group input-group-sm">
                <input wire:model="newTypeLabel" type="text" class="form-control" placeholder="نام نوع جدید">
                <button wire:click="addType" type="button" class="btn btn-success">افزودن</button>
                <button wire:click="toggleAddType" type="button" class="btn btn-outline-secondary">انصراف</button>
            </div>
            @error('newTypeLabel')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        @else
            <button wire:click="toggleAddType" type="button" class="btn btn-link btn-sm p-0 text-decoration-none">
                <i class="bi bi-plus-circle me-1"></i>نوع در لیست نیست؟ افزودن
            </button>
        @endif
    </div>
</div>

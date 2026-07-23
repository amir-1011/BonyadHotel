<div class="host-position-select">
    <label class="form-label small text-muted">سمت کاربر</label>
    <select wire:model.live="hostPositionPreset" class="form-select @error('hostPositionPreset') is-invalid @enderror">
        <option value="">— انتخاب سمت —</option>
        @foreach($positionOptions as $title)
            <option value="{{ $title }}">{{ $title }}</option>
        @endforeach
    </select>
    @error('hostPositionPreset')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    <div class="form-text">در صورت انتخاب سمت، به‌جای «میزبان» در پنل و فهرست کاربران نمایش داده می‌شود.</div>

    <div class="mt-1">
        @if($showAddHostPosition)
            <div class="input-group input-group-sm">
                <input wire:model="newHostPositionTitle" type="text" class="form-control" placeholder="نام سمت جدید">
                <button wire:click="addHostPosition" type="button" class="btn btn-success">افزودن</button>
                <button wire:click="toggleAddHostPosition" type="button" class="btn btn-outline-secondary">انصراف</button>
            </div>
            @error('newHostPositionTitle')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        @else
            <button wire:click="toggleAddHostPosition" type="button" class="btn btn-link btn-sm p-0 text-decoration-none">
                <i class="bi bi-plus-circle me-1"></i>سمت در لیست نیست؟ افزودن
            </button>
        @endif
    </div>
</div>

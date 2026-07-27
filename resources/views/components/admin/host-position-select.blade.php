<div class="host-position-select">
    <label class="form-label small text-muted">سمت کاربر</label>
    <select wire:model="hostPositionPreset" class="form-select @error('hostPositionPreset') is-invalid @enderror">
        @foreach($positionOptions as $title)
            <option value="{{ $title }}">{{ $title }}{{ $title === \App\Support\HostPositionTitles::DEFAULT_LABEL ? ' (پیش‌فرض)' : '' }}</option>
        @endforeach
    </select>
    @error('hostPositionPreset')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    <div class="form-text">
        سمت «میزبان» پیش‌فرض است و سطح دسترسی پایهٔ میزبانان جدید را از
        <a href="{{ route('admin.host-positions.index') }}" wire:navigate>تنظیمات سمت‌ها</a>
        می‌گیرد.
    </div>
</div>

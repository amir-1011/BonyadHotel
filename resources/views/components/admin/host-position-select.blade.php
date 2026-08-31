@props(['positionOptions' => [], 'showSettingsLink' => true])

<div class="host-position-select">
    <label class="form-label small text-muted">سمت کاربر</label>
    <select wire:model="hostPositionPreset" class="form-select @error('hostPositionPreset') is-invalid @enderror" @disabled($attributes->get('disabled'))>
        @foreach($positionOptions as $title)
            <option value="{{ $title }}">{{ $title }}{{ $title === \App\Support\HostPositionTitles::DEFAULT_LABEL ? ' (پیش‌فرض)' : '' }}</option>
        @endforeach
    </select>
    @error('hostPositionPreset')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    @if($showSettingsLink)
    <div class="form-text">
        سمت «میزبان» پیش‌فرض است و سطح دسترسی پایهٔ میزبانان جدید را از
        <a href="{{ route('admin.host-positions.index') }}" wire:navigate>تنظیمات سمت‌ها</a>
        می‌گیرد.
    </div>
    @else
    <div class="form-text">دسترسی‌های پنل بر اساس سمت انتخاب‌شده اعمال می‌شوند.</div>
    @endif
</div>

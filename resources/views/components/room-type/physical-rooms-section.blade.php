@props(['roomType'])

@php
    $roomAmenities = app(\App\Services\RoomTypeAmenityCatalogService::class)->names();
    $physicalRooms = $roomType->rooms ?? collect();
    $collapseId = 'physicalRoomsCollapse-' . $roomType->id;
    $startOpen = true;
@endphp

<div class="card shadow-sm mb-4" id="physicalRoomsCard-{{ $roomType->id }}">
    <div class="card-header fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2"
         role="button"
         data-bs-toggle="collapse"
         data-bs-target="#{{ $collapseId }}"
         aria-expanded="{{ $startOpen ? 'true' : 'false' }}"
         aria-controls="{{ $collapseId }}"
         style="cursor:pointer;user-select:none">
        <span>
            <i class="bi bi-grid-3x3-gap me-2"></i>اتاق‌های فیزیکی
            <span class="badge bg-primary bg-opacity-10 text-primary ms-1" style="font-size:.7rem;font-weight:500">{{ $physicalRooms->count() }} اتاق</span>
        </span>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-dark border small d-none d-md-inline">تعرفه‌های پایین (قیمت به ازای هر تخت) برای همه این اتاق‌ها اعمال می‌شود</span>
            <i class="bi bi-chevron-up text-muted physical-rooms-chevron {{ $startOpen ? '' : 'is-collapsed' }}"
               data-physical-rooms-chevron
               style="transition:transform .25s"></i>
        </div>
    </div>
    <div class="collapse {{ $startOpen ? 'show' : '' }}" id="{{ $collapseId }}">
        <div class="card-body">
            <p class="text-muted small mb-3">
                بر اساس «تعداد اتاق موجود از این دسته»، برای هر اتاق یک باکس ایجاد می‌شود.
                نام، امکانات و توضیحات هر اتاق را جداگانه وارد کنید.
            </p>

            @if($physicalRooms->isEmpty())
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    هنوز اتاق فیزیکی ساخته نشده. پس از ذخیره فرم، اتاق‌ها به‌صورت خودکار ایجاد می‌شوند.
                </div>
            @else
                <div class="row g-3">
                    @foreach($physicalRooms as $index => $room)
                    @php
                        $oldPrefix = "physical_rooms.{$index}";
                        $storedAmenities = array_values(array_filter($room->amenities ?? []));
                        $defaultAmenities = $storedAmenities !== []
                            ? $storedAmenities
                            : array_values(array_filter($roomType->amenities ?? []));
                        $selectedAmenities = old("{$oldPrefix}.amenities", $defaultAmenities);
                        $customAmenities = array_values(array_diff($selectedAmenities, $roomAmenities));
                    @endphp
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="physical-room-box h-100">
                            <input type="hidden" name="physical_rooms[{{ $index }}][id]" value="{{ $room->id }}">

                            <div class="physical-room-box__header">
                                <span class="physical-room-box__badge">اتاق {{ $index + 1 }}</span>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-semibold mb-1">نام اتاق <span class="text-danger">*</span></label>
                                <input type="text"
                                       name="physical_rooms[{{ $index }}][name]"
                                       class="form-control form-control-sm @error("{$oldPrefix}.name") is-invalid @enderror"
                                       value="{{ old("{$oldPrefix}.name", $room->name) }}"
                                       placeholder="مثلاً: اتاق ۱ — نمای کوه">
                                @error("{$oldPrefix}.name")<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-2">
                                <label class="form-label small fw-semibold mb-1">توضیحات</label>
                                <textarea name="physical_rooms[{{ $index }}][description]"
                                          rows="2"
                                          class="form-control form-control-sm @error("{$oldPrefix}.description") is-invalid @enderror"
                                          placeholder="ویژگی‌های این اتاق...">{{ old("{$oldPrefix}.description", $room->description) }}</textarea>
                                @error("{$oldPrefix}.description")<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div>
                                <label class="form-label small fw-semibold mb-1">امکانات این اتاق</label>
                                <div class="physical-room-amenities">
                                    @foreach($roomAmenities as $a)
                                    <label class="physical-room-amenity">
                                        <input type="checkbox"
                                               name="physical_rooms[{{ $index }}][amenities][]"
                                               value="{{ $a }}"
                                               @checked(in_array($a, $selectedAmenities, true))>
                                        <span>{{ $a }}</span>
                                    </label>
                                    @endforeach
                                    @foreach($customAmenities as $a)
                                    <label class="physical-room-amenity">
                                        <input type="checkbox"
                                               name="physical_rooms[{{ $index }}][amenities][]"
                                               value="{{ $a }}"
                                               checked>
                                        <span>{{ $a }}</span>
                                    </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

@once
@push('styles')
<style>
.physical-room-box {
    border: 1.5px solid var(--bs-border-color);
    border-radius: .75rem;
    padding: 1rem;
    background: var(--bs-body-bg);
    transition: border-color .15s, box-shadow .15s;
}
.physical-room-box:hover {
    border-color: rgba(var(--bs-primary-rgb), .35);
    box-shadow: 0 2px 8px rgba(0,0,0,.04);
}
.physical-room-box__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: .75rem;
    padding-bottom: .5rem;
    border-bottom: 1px dashed var(--bs-border-color-translucent);
}
.physical-room-box__badge {
    font-size: .75rem;
    font-weight: 700;
    color: var(--bs-primary);
    background: rgba(var(--bs-primary-rgb), .1);
    padding: .2rem .55rem;
    border-radius: 999px;
}
.physical-room-amenities {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
    max-height: 120px;
    overflow-y: auto;
}
.physical-room-amenity {
    display: inline-flex;
    align-items: center;
    gap: .25rem;
    font-size: .72rem;
    border: 1px solid var(--bs-border-color);
    border-radius: 999px;
    padding: .15rem .5rem;
    margin: 0;
    cursor: pointer;
    user-select: none;
}
.physical-room-amenity:has(input:checked) {
    border-color: var(--bs-success);
    background: rgba(var(--bs-success-rgb), .1);
    color: var(--bs-success-text-emphasis, var(--bs-success));
}
.physical-room-amenity input {
    width: .85rem;
    height: .85rem;
    margin: 0;
}
.physical-rooms-chevron.is-collapsed {
    transform: rotate(180deg);
}
</style>
@endpush
@push('scripts')
<script data-navigate-once>
(function () {
    if (window.__bonyadPhysicalRoomsCollapseBound) return;
    window.__bonyadPhysicalRoomsCollapseBound = true;

    function syncChevron(collapseEl, isOpen) {
        const card = collapseEl.closest('.card');
        const chevron = card?.querySelector('[data-physical-rooms-chevron]');
        if (!chevron) return;
        chevron.classList.toggle('is-collapsed', !isOpen);
    }

    document.addEventListener('show.bs.collapse', function (e) {
        if (!e.target?.id?.startsWith('physicalRoomsCollapse-')) return;
        syncChevron(e.target, true);
    });
    document.addEventListener('hide.bs.collapse', function (e) {
        if (!e.target?.id?.startsWith('physicalRoomsCollapse-')) return;
        syncChevron(e.target, false);
    });
})();
</script>
@endpush
@endonce

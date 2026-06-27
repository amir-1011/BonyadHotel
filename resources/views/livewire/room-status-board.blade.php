<div>

    <div class="ta-card h-100" id="room-status-board-root">
        <div class="ta-card__head flex-wrap gap-2">
            <div>
                <h2 class="ta-card__title mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>وضعیت اتاق‌ها</h2>
                <div class="ta-card__sub">نمای زنده اتاق‌های فیزیکی بر اساس نقشه ساختمان — رنگ هر باکس وضعیت همان روز را نشان می‌دهد</div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @if($panel === 'admin')
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted mb-0">اقامتگاه:</label>
                    <select wire:model="accommodationId"
                            class="form-select form-select-sm @error('accommodationId') is-invalid @enderror"
                            style="min-width:200px;">
                        <option value="">انتخاب اقامتگاه...</option>
                        @foreach($accommodations as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="d-flex align-items-center gap-2" wire:ignore>
                    <label class="small text-muted mb-0">تاریخ:</label>
                    <div class="input-group input-group-sm" style="width:auto;">
                        <input type="text"
                               id="room-status-board-date"
                               class="form-control form-control-sm rsb-jalali-date @error('viewDateJalali') is-invalid @enderror"
                               data-wire-prop="viewDateJalali"
                               value="{{ $viewDateJalali }}"
                               style="width:8.5rem;"
                               placeholder="۱۴۰۵/۰۱/۰۱"
                               autocomplete="off"
                               dir="ltr">
                        <button type="button"
                                class="btn btn-outline-secondary room-status-board-clear-date"
                                data-target="room-status-board-date"
                                tabindex="-1"
                                title="پاک کردن">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                </div>
                @if($panel === 'admin')
                <button type="button"
                        wire:click="viewRooms"
                        onclick="window.syncRoomStatusBoardDate && window.syncRoomStatusBoardDate()"
                        class="btn btn-primary btn-sm">
                    <i class="bi bi-eye me-1"></i>مشاهده اتاق‌ها
                </button>
                @else
                <button type="button"
                        wire:click="applyDate"
                        onclick="window.syncRoomStatusBoardDate && window.syncRoomStatusBoardDate()"
                        class="btn btn-primary btn-sm">
                    <i class="bi bi-check2 me-1"></i>اعمال
                </button>
                @if(!empty($board))
                <button type="button"
                        wire:click="toggleLayoutEdit"
                        class="btn btn-sm {{ $layoutEditMode ? 'btn-warning' : 'btn-outline-secondary' }}">
                    <i class="bi bi-{{ $layoutEditMode ? 'x-lg' : 'layout-three-columns' }} me-1"></i>
                    {{ $layoutEditMode ? 'لغو نقشه' : 'نقشه ساختمان' }}
                </button>
                @if($layoutEditMode)
                <button type="button" wire:click="saveLayout" class="btn btn-success btn-sm">
                    <i class="bi bi-check-lg me-1"></i>ذخیره چیدمان
                </button>
                @endif
                @endif
                @endif
            </div>
            @error('accommodationId')<div class="text-danger small mt-1 w-100">{{ $message }}</div>@enderror
            @error('viewDateJalali')<div class="text-danger small mt-1 w-100">{{ $message }}</div>@enderror
        </div>

        <div class="ta-card__body">
            <div class="d-flex flex-wrap gap-2 mb-3 align-items-center" style="font-size:.75rem;">
                @if($layoutEditMode)
                <span class="badge bg-warning text-dark"><i class="bi bi-arrows-move me-1"></i>حالت چیدمان — اتاق‌های مختلف (۲ تخته، ۳ تخته و ...) را طبق نقشه ساختمان در ردیف‌ها بچینید</span>
                @else
                <span class="badge bg-success-subtle text-success border border-success-subtle">آزاد</span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">مهمان فعلی</span>
                <span class="badge bg-info-subtle text-info border border-info-subtle">رزرو آینده</span>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">بسته (ظرفیت روزانه)</span>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">مسدود</span>
                @endif
            </div>

            @if($panel === 'admin' && !$boardVisible)
            <div class="text-center text-muted py-4 small">
                <i class="bi bi-building fs-2 d-block mb-2 opacity-25"></i>
                ابتدا اقامتگاه را انتخاب کنید، تاریخ شمسی را وارد کنید و روی «مشاهده اتاق‌ها» کلیک کنید.
            </div>
            @elseif(empty($board))
            <div class="text-center text-muted py-4 small">
                <i class="bi bi-door-open fs-2 d-block mb-2 opacity-25"></i>
                اتاق فیزیکی فعالی یافت نشد. ابتدا در بخش مدیریت اتاق‌ها، اتاق‌های فیزیکی را تعریف کنید.
            </div>
            @else
            @foreach($board as $acc)
            @php
                $accKey = (string) $acc['accommodation_id'];
                $roomsById = collect($acc['rooms'])->keyBy('id');
                $editLayout = $editLayouts[$accKey] ?? ['cols' => 6, 'rows' => [], 'row_labels' => []];
                $displayRows = $acc['rows'] ?? [['label' => '', 'rooms' => $acc['rooms']]];
                $displayCols = $acc['cols'] ?? 6;
            @endphp
            <div class="mb-4" wire:key="rsb-acc-{{ $acc['accommodation_id'] }}">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <div class="fw-bold"><i class="bi bi-building me-1 text-primary"></i>{{ $acc['accommodation_name'] }}</div>
                    @if($layoutEditMode && $panel === 'host')
                    <div class="d-flex align-items-center gap-2 ms-auto flex-wrap">
                        <label class="small text-muted mb-0 d-flex align-items-center gap-1">
                            حداکثر ستون در هر ردیف:
                            <select class="form-select form-select-sm"
                                    style="width:auto;min-width:4rem;"
                                    wire:change="setLayoutCols({{ $acc['accommodation_id'] }}, $event.target.value)">
                                @foreach([3, 4, 5, 6, 7, 8, 10, 12] as $n)
                                <option value="{{ $n }}" @selected((int) ($editLayout['cols'] ?? 6) === $n)>{{ $n }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="button"
                                wire:click="addLayoutRow({{ $acc['accommodation_id'] }})"
                                class="btn btn-outline-primary btn-sm py-0 px-2"
                                style="font-size:.72rem;">
                            <i class="bi bi-plus-lg me-1"></i>ردیف جدید
                        </button>
                        <button type="button"
                                wire:click="resetAccommodationLayout({{ $acc['accommodation_id'] }})"
                                class="btn btn-outline-secondary btn-sm py-0 px-2"
                                style="font-size:.72rem;"
                                data-swal-confirm="چیدمان پیش‌فرض بازگردانده شود؟">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>پیش‌فرض
                        </button>
                    </div>
                    @endif
                </div>

                @if($layoutEditMode && $panel === 'host')
                    @foreach($editLayout['rows'] as $rowIndex => $rowIds)
                    @php $rowLabel = trim((string) ($editLayout['row_labels'][$rowIndex] ?? '')); @endphp
                    <div class="room-status-row room-status-row--editable"
                         wire:sort="sortRoom"
                         wire:sort:group="room-board-{{ $acc['accommodation_id'] }}"
                         wire:sort:group-id="{{ $acc['accommodation_id'] }}:{{ $rowIndex }}"
                         style="--rsb-cols: {{ (int) ($editLayout['cols'] ?? 6) }};"
                         wire:key="rsb-edit-row-{{ $acc['accommodation_id'] }}-{{ $rowIndex }}">
                        <div class="room-status-row__header">
                            <input type="text"
                                   class="form-control form-control-sm room-status-row__name-input"
                                   value="{{ $rowLabel }}"
                                   placeholder="نام ردیف — مثلاً: طبقه اول"
                                   maxlength="60"
                                   wire:change="setRowLabel({{ $acc['accommodation_id'] }}, {{ $rowIndex }}, $event.target.value)">
                            <span class="room-status-row__index">ردیف {{ $rowIndex + 1 }}</span>
                        </div>
                        @foreach($rowIds as $roomId)
                        @php $room = $roomsById->get($roomId); @endphp
                        @if($room)
                        <div wire:sort:item="{{ $roomId }}"
                             wire:key="rsb-edit-room-{{ $roomId }}-{{ $rowIndex }}"
                             class="room-status-sortable-item">
                            <div class="room-status-box room-status-box--{{ $room['color'] }} room-status-box--editable {{ $room['has_future'] ? 'room-status-box--has-future' : '' }}">
                                <div class="room-status-box__top">
                                    <span wire:sort:handle class="room-status-box__drag" title="بکشید">
                                        <i class="bi bi-grip-vertical"></i>
                                    </span>
                                    <span class="room-status-box__name">{{ $room['name'] }}</span>
                                </div>
                                @if($room['bed_type'] || $room['room_type_name'])
                                <div class="room-status-box__type">{{ $room['bed_type'] ?: $room['room_type_name'] }}</div>
                                @endif
                                <div class="room-status-box__status">{{ $room['status_label'] }}</div>
                            </div>
                        </div>
                        @endif
                        @endforeach
                    </div>
                    @endforeach
                @else
                    @foreach($displayRows as $rowIndex => $rowData)
                    @php
                        $rowRooms = $rowData['rooms'] ?? $rowData;
                        $rowLabel = trim((string) ($rowData['label'] ?? ''));
                    @endphp
                    @if($rowLabel !== '')
                    <div class="room-status-row__heading" wire:key="rsb-row-h-{{ $acc['accommodation_id'] }}-{{ $rowIndex }}">
                        <i class="bi bi-layers me-1"></i>{{ $rowLabel }}
                    </div>
                    @endif
                    <div class="room-status-row"
                         style="--rsb-cols: {{ (int) $displayCols }};"
                         wire:key="rsb-row-{{ $acc['accommodation_id'] }}-{{ $rowIndex }}">
                        @foreach($rowRooms as $room)
                        <button type="button"
                                wire:click="selectRoom({{ $acc['accommodation_id'] }}, {{ $room['id'] }})"
                                class="room-status-box room-status-box--{{ $room['color'] }} {{ $room['has_future'] ? 'room-status-box--has-future' : '' }}"
                                wire:key="rsb-room-{{ $room['id'] }}-{{ $viewDate }}">
                            <div class="room-status-box__name">{{ $room['name'] }}</div>
                            @if($room['bed_type'] || $room['room_type_name'])
                            <div class="room-status-box__type">{{ $room['bed_type'] ?: $room['room_type_name'] }}</div>
                            @endif
                            <div class="room-status-box__status">{{ $room['status_label'] }}</div>
                            @if($room['has_future'])
                            <div class="room-status-box__future"><i class="bi bi-calendar-event"></i> رزرو آینده</div>
                            @endif
                            @if($room['current_booking'])
                            <div class="room-status-box__guest text-truncate">{{ $room['current_booking']['guest_name'] }}</div>
                            @endif
                        </button>
                        @endforeach
                    </div>
                    @endforeach
                @endif
            </div>
            @endforeach
            @endif
        </div>
    </div>

    @if($selectedRoom)
    <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.45);" wire:click.self="closeDetail">
        <div class="modal-dialog modal-dialog-centered {{ $servicesBookingId ? 'modal-xl' : 'modal-lg' }}">
            <div class="modal-content" wire:click.stop>
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-door-open me-2"></i>{{ $selectedRoom['name'] }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="closeDetail"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge text-bg-{{ $selectedRoom['color'] }}">{{ $selectedRoom['status_label'] }}</span>
                        <span class="badge bg-light text-dark border">{{ $selectedRoom['accommodation_name'] }}</span>
                        <span class="badge bg-light text-dark border">{{ $selectedRoom['room_type_name'] }}</span>
                        @if($selectedRoom['bed_type'])
                        <span class="badge bg-light text-dark border">{{ $selectedRoom['bed_type'] }}</span>
                        @endif
                    </div>

                    @if($selectedRoom['description'])
                    <p class="small text-muted">{{ $selectedRoom['description'] }}</p>
                    @endif

                    @if(!empty($selectedRoom['amenities']))
                    <div class="mb-3 d-flex flex-wrap gap-1">
                        @foreach($selectedRoom['amenities'] as $a)
                        <span class="badge bg-light text-dark border" style="font-size:.7rem;">{{ $a }}</span>
                        @endforeach
                    </div>
                    @endif

                    @if($selectedRoom['status'] === 'blocked')
                    <div class="alert alert-danger py-2 small mb-3">
                        <i class="bi bi-calendar-x me-1"></i>
                        <strong>مسدود در این تاریخ</strong>
                        @if($selectedRoom['block_reason'])
                        <div class="mt-1">دلیل: {{ $selectedRoom['block_reason'] }}</div>
                        @endif
                    </div>
                    @endif

                    @if($selectedRoom['status'] === 'capacity_closed')
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="bi bi-sliders me-1"></i>
                        این اتاق در تاریخ انتخاب‌شده به‌دلیل <strong>تنظیم ظرفیت روزانه</strong> برای فروش بسته است.
                    </div>
                    @endif

                    @if(!empty($selectedRoom['current_booking']))
                    @php $cb = $selectedRoom['current_booking']; @endphp
                    <div class="card border-primary border-opacity-25 mb-3">
                        <div class="card-header bg-primary-subtle py-2 small fw-semibold">مهمان فعلی / رزرو این بازه</div>
                        <div class="card-body py-2 small">
                            <div><strong>{{ $cb['guest_name'] }}</strong> · <span dir="ltr">{{ $cb['guest_mobile'] }}</span></div>
                            <div class="text-muted mt-1">ورود @jalali($cb['check_in']) → خروج @jalali($cb['check_out']) · {{ $cb['guests'] }} نفر</div>
                            @if($cb['room_rate'])<div class="text-muted">تعرفه: {{ $cb['room_rate'] }}</div>@endif
                            <div class="mt-2">
                                <code>{{ $cb['tracking_code'] }}</code>
                                <span class="badge bg-{{ $cb['status'] === 'confirmed' ? 'success' : 'warning' }} ms-1">{{ $cb['status_label'] }}</span>
                            </div>
                            @if($panel === 'host')
                            <a wire:navigate href="{{ route('host.bookings.show', $cb['booking_id']) }}" class="btn btn-sm btn-outline-primary mt-2">مشاهده رزرو</a>
                            @elseif($panel === 'admin')
                            <a wire:navigate href="{{ route('admin.bookings.show', $cb['booking_id']) }}" class="btn btn-sm btn-outline-primary mt-2">مشاهده رزرو</a>
                            @endif
                        </div>
                    </div>
                    @endif

                    @if(!empty($selectedRoom['future_bookings']))
                    <div class="card border-info border-opacity-25">
                        <div class="card-header bg-info-subtle py-2 small fw-semibold">رزروهای آینده</div>
                        <ul class="list-group list-group-flush">
                            @foreach($selectedRoom['future_bookings'] as $fb)
                            <li class="list-group-item small">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <strong>{{ $fb['guest_name'] }}</strong>
                                        <div class="text-muted">@jalali($fb['check_in']) → @jalali($fb['check_out']) · {{ $fb['guests'] }} نفر</div>
                                        <code class="small">{{ $fb['tracking_code'] }}</code>
                                    </div>
                                    @if($panel === 'host')
                                    <div class="d-flex flex-column gap-1">
                                        <button type="button"
                                                wire:click="selectServicesBooking({{ $fb['booking_id'] }})"
                                                class="btn btn-sm {{ (int) $servicesBookingId === (int) $fb['booking_id'] ? 'btn-info' : 'btn-outline-info' }}">
                                            <i class="bi bi-bag-check me-1"></i>خدمات
                                        </button>
                                        <a wire:navigate href="{{ route('host.bookings.show', $fb['booking_id']) }}" class="btn btn-sm btn-outline-secondary">جزئیات</a>
                                    </div>
                                    @elseif($panel === 'admin')
                                    <a wire:navigate href="{{ route('admin.bookings.show', $fb['booking_id']) }}" class="btn btn-sm btn-outline-info">جزئیات</a>
                                    @endif
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @elseif($selectedRoom['status'] === 'available')
                    <div class="alert alert-success py-2 small mb-0">
                        <i class="bi bi-check-circle me-1"></i>در تاریخ انتخاب‌شده رزرو فعلی یا آینده‌ای برای این اتاق ثبت نشده است.
                    </div>
                    @endif

                    @if($servicesBookingId && in_array($panel, ['host', 'admin'], true))
                    <div class="card border-success border-opacity-25 mt-3">
                        <div class="card-header bg-success-subtle py-2 small fw-semibold d-flex justify-content-between align-items-center gap-2 flex-wrap">
                            <span><i class="bi bi-bag-check me-1"></i>مدیریت خدمات رزرو</span>
                            @if($panel === 'host')
                            <a wire:navigate href="{{ route('host.bookings.show', $servicesBookingId) }}" class="btn btn-xs btn-outline-success" style="font-size:.72rem;">صفحه رزرو</a>
                            @else
                            <a wire:navigate href="{{ route('admin.bookings.show', $servicesBookingId) }}" class="btn btn-xs btn-outline-success" style="font-size:.72rem;">صفحه رزرو</a>
                            @endif
                        </div>
                        <div class="card-body py-2">
                            <livewire:booking-services-editor
                                :booking-id="$servicesBookingId"
                                :panel="$panel"
                                :key="'rsb-booking-services-'.$servicesBookingId" />
                        </div>
                    </div>
                    @endif

                    @if($actionMessage)
                    <div class="alert alert-success py-2 small mt-3 mb-0">
                        <i class="bi bi-check-circle me-1"></i>{{ $actionMessage }}
                    </div>
                    @endif

                    @if($panel === 'host' && empty($selectedRoom['current_booking']))
                    <div class="card border-secondary border-opacity-25 mt-3">
                        <div class="card-header bg-light py-2 small fw-semibold">
                            <i class="bi bi-lock-fill me-1 text-danger"></i>مسدودسازی در تاریخ @jalali($viewDate)
                        </div>
                        <div class="card-body py-2">
                            @if($selectedRoom['status'] === 'blocked')
                            <div class="alert alert-danger py-2 small mb-3 mb-0">
                                <i class="bi bi-calendar-x me-1"></i>
                                این اتاق در این تاریخ مسدود است.
                                @if($selectedRoom['block_reason'])
                                <div class="mt-1">دلیل: {{ $selectedRoom['block_reason'] }}</div>
                                @endif
                            </div>
                            <button type="button"
                                    wire:click="unblockSelectedRoom"
                                    class="btn btn-outline-success btn-sm w-100 mt-2"
                                    wire:loading.attr="disabled"
                                    data-swal-confirm="مسدودیت این اتاق برای تاریخ انتخاب‌شده برداشته شود؟">
                                <span wire:loading.remove wire:target="unblockSelectedRoom"><i class="bi bi-unlock-fill me-1"></i>خارج کردن از مسدودی</span>
                                <span wire:loading wire:target="unblockSelectedRoom">در حال ثبت...</span>
                            </button>
                            @else
                            <p class="small text-muted mb-2">این اتاق فقط برای تاریخ <strong dir="ltr">@jalali($viewDate)</strong> مسدود می‌شود.</p>
                            <div class="mb-2">
                                <label class="form-label small mb-1">دلیل (اختیاری)</label>
                                <input type="text"
                                       wire:model="blockReason"
                                       class="form-control form-control-sm @error('blockReason') is-invalid @enderror"
                                       placeholder="مثال: تعمیرات، رزرو شخصی..."
                                       maxlength="200">
                                @error('blockReason')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <button type="button"
                                    wire:click="blockSelectedRoom"
                                    class="btn btn-danger btn-sm w-100"
                                    wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="blockSelectedRoom"><i class="bi bi-lock-fill me-1"></i>مسدود کردن این تاریخ</span>
                                <span wire:loading wire:target="blockSelectedRoom">در حال ثبت...</span>
                            </button>
                            @endif
                        </div>
                    </div>
                    @endif

                    @if($panel === 'host' && !empty($selectedRoomRates))
                    <div class="card border-primary border-opacity-25 mt-3">
                        <div class="card-header bg-primary-subtle py-2 small fw-semibold">
                            <i class="bi bi-calendar-plus me-1"></i>رزرو این اتاق
                        </div>
                        <div class="card-body py-2">
                            <p class="small text-muted mb-2">تعرفه را انتخاب کنید؛ سپس به صفحه رزرو دستی می‌روید تا تاریخ ورود/خروج و تعداد نفرات را مشخص کنید. اتاق «{{ $selectedRoom['name'] }}» از قبل انتخاب می‌شود.</p>
                            <div class="mb-2">
                                <label class="form-label small mb-1">تعرفه <span class="text-danger">*</span></label>
                                <select wire:model="bookingRoomRateId"
                                        class="form-select form-select-sm @error('bookingRoomRateId') is-invalid @enderror">
                                    <option value="">انتخاب تعرفه...</option>
                                    @foreach($selectedRoomRates as $rate)
                                    <option value="{{ $rate['id'] }}">{{ $rate['name'] }} — {{ number_format($rate['price_per_night']) }} تومان/شب</option>
                                    @endforeach
                                </select>
                                @error('bookingRoomRateId')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <button type="button"
                                    wire:click="goToManualBooking"
                                    class="btn btn-primary btn-sm w-100"
                                    @disabled(!$bookingRoomRateId)>
                                <i class="bi bi-arrow-left-circle me-1"></i>ادامه در رزرو دستی
                            </button>
                        </div>
                    </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="closeDetail">بستن</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    @once
    <style>
    .room-status-row {
        display: grid;
        grid-template-columns: repeat(var(--rsb-cols, 4), minmax(0, 1fr));
        gap: .5rem;
        margin-bottom: .5rem;
    }
    .room-status-row--editable {
        position: relative;
        min-height: 4.5rem;
        padding: 2.4rem .5rem .5rem;
        border: 1.5px dashed rgba(var(--bs-primary-rgb), .35);
        border-radius: .65rem;
        background: rgba(var(--bs-primary-rgb), .03);
    }
    .room-status-row__header {
        position: absolute;
        top: .35rem;
        right: .5rem;
        left: .5rem;
        display: flex;
        align-items: center;
        gap: .5rem;
    }
    .room-status-row__name-input {
        flex: 1;
        min-width: 0;
        font-size: .75rem;
        font-weight: 600;
        border-style: dashed;
        background: var(--bs-body-bg);
    }
    .room-status-row__name-input:focus {
        border-style: solid;
        border-color: var(--bs-primary);
    }
    .room-status-row__index {
        font-size: .62rem;
        color: var(--bs-secondary);
        white-space: nowrap;
        flex-shrink: 0;
    }
    .room-status-row__heading {
        font-size: .78rem;
        font-weight: 700;
        color: var(--bs-primary);
        margin: .75rem 0 .35rem;
        padding-bottom: .25rem;
        border-bottom: 1px solid rgba(var(--bs-primary-rgb), .15);
    }
    .room-status-row__heading:first-child { margin-top: 0; }
    .room-status-sortable-item { min-width: 0; }
    .room-status-box {
        border: 2px solid var(--bs-border-color);
        border-radius: .65rem;
        padding: .55rem .7rem;
        width: 100%;
        text-align: right;
        background: var(--bs-body-bg);
        cursor: pointer;
        transition: transform .12s, box-shadow .12s;
    }
    button.room-status-box:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,.08);
    }
    .room-status-box--editable { cursor: default; }
    .room-status-box__top {
        display: flex;
        align-items: center;
        gap: .35rem;
    }
    .room-status-box__drag {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--bs-secondary);
        cursor: grab;
        padding: .1rem;
        border-radius: .25rem;
        flex-shrink: 0;
    }
    .room-status-box__drag:active { cursor: grabbing; }
    .room-status-box__drag:hover { color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), .08); }
    .room-status-box__name { font-size: .8rem; font-weight: 700; line-height: 1.3; flex: 1; min-width: 0; }
    .room-status-box__type { font-size: .62rem; color: var(--bs-secondary); margin-top: .15rem; }
    .room-status-box__status { font-size: .68rem; margin-top: .2rem; opacity: .85; }
    .room-status-box__guest { font-size: .65rem; margin-top: .25rem; opacity: .9; }
    .room-status-box__future { font-size: .62rem; margin-top: .2rem; color: var(--bs-info); }
    .room-status-box--success { border-color: rgba(var(--bs-success-rgb), .5); background: rgba(var(--bs-success-rgb), .08); }
    .room-status-box--primary { border-color: rgba(var(--bs-primary-rgb), .5); background: rgba(var(--bs-primary-rgb), .1); }
    .room-status-box--warning { border-color: rgba(var(--bs-warning-rgb), .55); background: rgba(var(--bs-warning-rgb), .1); background-image: repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(var(--bs-warning-rgb), .08) 4px, rgba(var(--bs-warning-rgb), .08) 5px); }
    .room-status-box--danger { border-color: rgba(var(--bs-danger-rgb), .5); background: rgba(var(--bs-danger-rgb), .08); background-image: repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(var(--bs-danger-rgb), .1) 4px, rgba(var(--bs-danger-rgb), .1) 5px); }
    .room-status-box--has-future:not(.room-status-box--primary) {
        box-shadow: inset 0 -3px 0 var(--bs-info);
    }
    .sortable-ghost .room-status-box {
        opacity: .45;
        border-style: dashed;
    }
    .sortable-chosen .room-status-box {
        box-shadow: 0 8px 20px rgba(0,0,0,.12);
        transform: scale(1.02);
    }
    </style>
    @endonce

@push('scripts')
<script>
(function () {
    var NS = window.RoomStatusBoardDatepicker = window.RoomStatusBoardDatepicker || { ready: false, docBound: false };

    /** persian-datepicker calls global $ internally — Livewire may replace it. */
    function ensureJqueryGlobal$() {
        if (typeof window.jQuery !== 'undefined') {
            window.$ = window.jQuery;
        }
    }

    function roomStatusWire() {
        var root = document.getElementById('room-status-board-root');
        if (!root) return null;
        var host = root.closest('[wire\\:id]');
        if (!host || typeof Livewire === 'undefined') return null;
        return Livewire.find(host.getAttribute('wire:id'));
    }

    function syncRoomStatusDateToWire(input) {
        var wire = roomStatusWire();
        var prop = input.getAttribute('data-wire-prop');
        if (wire && prop) {
            wire.set(prop, input.value || '');
        }
    }

    function syncRoomStatusBoardDate() {
        var input = document.getElementById('room-status-board-date');
        if (input) syncRoomStatusDateToWire(input);
    }

    function rsbInputs() {
        return document.querySelectorAll('#room-status-board-root .rsb-jalali-date');
    }

    function destroyRoomStatusDatepicker() {
        ensureJqueryGlobal$();
        var jq = window.jQuery;
        if (!jq || !jq.fn || typeof jq.fn.pDatepicker !== 'function') {
            NS.ready = false;
            return;
        }

        jq(rsbInputs()).each(function () {
            var $input = jq(this);
            if ($input.data('pDatepicker')) {
                try { $input.pDatepicker('destroy'); } catch (e) { /* ignore */ }
                $input.removeData('pDatepicker');
            }
        });
        NS.ready = false;
    }

    function initRoomStatusDatepicker() {
        ensureJqueryGlobal$();
        var jq = window.jQuery;
        if (!jq || !jq.fn || typeof jq.fn.pDatepicker !== 'function') return;
        if (NS.ready) return;
        if (!document.getElementById('room-status-board-date')) return;

        jq(rsbInputs()).each(function () {
            var $input = jq(this);
            if ($input.data('pDatepicker')) return;

            ensureJqueryGlobal$();
            $input.pDatepicker({
                format: 'YYYY/MM/DD',
                viewMode: 'day',
                autoClose: true,
                initialValue: false,
                initialValueType: 'persian',
                persianDigit: false,
                toolbox: {
                    enabled: true,
                    todayButton: { enabled: true },
                    submitButton: { enabled: false },
                },
                onSelect: function () {
                    var el = this.model && this.model.inputElement ? this.model.inputElement : $input[0];
                    syncRoomStatusDateToWire(el);
                },
            });
        });

        NS.ready = true;
    }

    function bootRoomStatusDatepicker() {
        ensureJqueryGlobal$();
        NS.ready = false;
        initRoomStatusDatepicker();
    }

    window.syncRoomStatusBoardDate = syncRoomStatusBoardDate;

    if (!NS.docBound) {
        NS.docBound = true;

        document.addEventListener('blur', function (e) {
            if (e.target && e.target.matches && e.target.matches('#room-status-board-root .rsb-jalali-date')) {
                syncRoomStatusDateToWire(e.target);
            }
        }, true);

        document.addEventListener('focus', function (e) {
            if (!e.target || !e.target.matches || !e.target.matches('#room-status-board-root .rsb-jalali-date')) return;
            ensureJqueryGlobal$();
            var jq = window.jQuery;
            if (!jq || jq(e.target).data('pDatepicker')) return;
            NS.ready = false;
            initRoomStatusDatepicker();
        }, true);

        document.addEventListener('click', function (e) {
            var btn = e.target.closest && e.target.closest('.room-status-board-clear-date');
            if (!btn) return;
            var targetId = btn.getAttribute('data-target');
            var input = targetId ? document.getElementById(targetId) : null;
            if (!input) return;
            input.value = '';
            syncRoomStatusDateToWire(input);
        });

        document.addEventListener('livewire:navigated', function () {
            if (!document.getElementById('room-status-board-date')) return;
            destroyRoomStatusDatepicker();
            setTimeout(bootRoomStatusDatepicker, 0);
        });

        document.addEventListener('livewire:initialized', function () {
            setTimeout(bootRoomStatusDatepicker, 0);
            if (typeof Livewire === 'undefined') return;
            Livewire.hook('commit', function (payload) {
                payload.succeed(function () {
                    if (!document.getElementById('room-status-board-date')) return;
                    ensureJqueryGlobal$();
                    var jq = window.jQuery;
                    if (!jq) return;
                    var $input = jq('#room-status-board-date');
                    if ($input.length && !$input.data('pDatepicker')) {
                        NS.ready = false;
                        initRoomStatusDatepicker();
                    }
                });
            });
        });
    }

    if (typeof window.Livewire !== 'undefined') {
        setTimeout(bootRoomStatusDatepicker, 0);
    } else {
        document.addEventListener('livewire:initialized', function () {
            setTimeout(bootRoomStatusDatepicker, 0);
        });
    }
})();
</script>
@endpush
</div>

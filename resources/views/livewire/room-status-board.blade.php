<div class="room-status-board">

    @php
        $isViewDateToday = $viewDate === now()->toDateString();
    @endphp

    <div class="ta-card h-100" id="room-status-board-root">
        <div class="ta-card__head room-status-board-head flex-wrap gap-2">
            <div class="min-w-0">
                <h2 class="ta-card__title mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>وضعیت اتاق‌ها</h2>
                <div class="ta-card__sub">نمای زنده اتاق‌های فیزیکی بر اساس نقشه ساختمان — رنگ هر باکس وضعیت همان روز را نشان می‌دهد</div>
            </div>
            <div class="room-status-board-toolbar d-flex align-items-center gap-2 flex-wrap min-w-0">
                @if($panel === 'admin' && !$useDashboardFilter)
                <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                    <label class="small text-muted mb-0 flex-shrink-0">اقامتگاه:</label>
                    <select wire:model="accommodationId"
                            class="form-select form-select-sm @error('accommodationId') is-invalid @enderror"
                            style="min-width:0;max-width:100%;">
                        <option value="">انتخاب اقامتگاه...</option>
                        @foreach($accommodations as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="d-flex align-items-center gap-2 room-status-board-date-wrap" wire:ignore>
                    <label class="small text-muted mb-0 flex-shrink-0">تاریخ:</label>
                    <div class="input-group input-group-sm room-status-board-date-group">
                        <input type="text"
                               id="room-status-board-date"
                               class="form-control form-control-sm rsb-jalali-date @error('viewDateJalali') is-invalid @enderror{{ $isViewDateToday ? ' jalali-date-is-today' : '' }}"
                               data-wire-prop="viewDateJalali"
                               value="{{ $viewDateJalali }}"
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
                @if($panel === 'admin' && !$useDashboardFilter)
                <button type="button"
                        wire:click="viewRooms"
                        onclick="window.syncRoomStatusBoardDate && window.syncRoomStatusBoardDate()"
                        class="btn btn-primary btn-sm">
                    <i class="bi bi-eye me-1"></i>مشاهده اتاق‌ها
                </button>
                @elseif($panel === 'admin' && $useDashboardFilter && !$boardVisible)
                <button type="button"
                        wire:click="showFilteredRooms"
                        onclick="window.syncRoomStatusBoardDate && window.syncRoomStatusBoardDate()"
                        class="btn btn-primary btn-sm">
                    <i class="bi bi-eye me-1"></i>نمایش
                </button>
                @else
                @if(!$layoutEditMode)
                <button type="button"
                        wire:click="applyDate"
                        onclick="window.syncRoomStatusBoardDate && window.syncRoomStatusBoardDate()"
                        class="btn btn-primary btn-sm">
                    <i class="bi bi-check2 me-1"></i>اعمال
                </button>
                @endif
                @if(!empty($board) && $canEditBuildingLayout)
                <button type="button"
                        wire:click="toggleLayoutEdit"
                        class="btn btn-sm {{ $layoutEditMode ? 'btn-warning' : 'btn-outline-secondary' }}">
                    <i class="bi bi-{{ $layoutEditMode ? 'x-lg' : 'layout-three-columns' }} me-1"></i>
                    {{ $layoutEditMode ? 'لغو نقشه' : 'نقشه ساختمان' }}
                </button>
                @if($layoutEditMode && $canEditBuildingLayout)
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
            @if($boardVisible)
            @php
                $anyPhysicalRoomsShown = $layoutEditMode || collect($board)->contains(
                    fn ($acc) => in_array((int) $acc['accommodation_id'], array_map('intval', $expandedPhysicalRoomIds), true)
                );
            @endphp
            @if($layoutEditMode && $canEditBuildingLayout)
            <div class="d-flex flex-wrap gap-2 mb-3 align-items-center" style="font-size:.75rem;">
                <div class="d-flex flex-column gap-2 w-100">
                    <span class="badge bg-warning text-dark align-self-start"><i class="bi bi-arrows-move me-1"></i>حالت چیدمان — ردیف‌ها با ≡ و اتاق‌ها با ⋮⋮</span>
                    <div class="small text-muted border border-warning border-opacity-25 rounded px-3 py-2 bg-warning-subtle">
                        <i class="bi bi-info-circle me-1 text-warning"></i>
                        اتاق فیزیکی را با موس بردارید؛ روی هر اتاقی که قرار دهید، <strong>سمت راست</strong> آن اتاق قرار می‌گیرد.
                    </div>
                </div>
            </div>
            @elseif($anyPhysicalRoomsShown)
            <div class="d-flex flex-wrap gap-2 mb-3 align-items-center" style="font-size:.75rem;">
                <span class="badge bg-success-subtle text-success border border-success-subtle">آزاد</span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">مهمان فعلی</span>
                <span class="badge room-status-legend-purple">اردو / برنامه</span>
                <span class="badge bg-info-subtle text-info border border-info-subtle">رزرو آینده</span>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">بسته (سیاست قیمتی)</span>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">مسدود</span>
            </div>
            @endif
            @endif

            @if($panel === 'admin' && !$boardVisible)
            <div class="text-center text-muted py-4 small">
                <i class="bi bi-building fs-2 d-block mb-2 opacity-25"></i>
                @if($useDashboardFilter)
                    تاریخ شمسی را بررسی کنید و برای بارگذاری وضعیت اتاق‌های اقامتگاه‌های انتخاب‌شده روی «نمایش» کلیک کنید.
                @else
                    ابتدا اقامتگاه را انتخاب کنید، تاریخ شمسی را وارد کنید و روی «مشاهده اتاق‌ها» کلیک کنید.
                @endif
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
                $showPhysicalRooms = $layoutEditMode
                    || in_array((int) $acc['accommodation_id'], array_map('intval', $expandedPhysicalRoomIds), true);
                $summary = $acc['summary'] ?? [
                    'total' => count($acc['rooms'] ?? []),
                    'available' => 0,
                    'occupied' => 0,
                    'occupied_guests' => 0,
                    'program' => 0,
                    'program_guests' => 0,
                    'future' => 0,
                    'future_program' => 0,
                    'capacity_closed' => 0,
                    'blocked' => 0,
                ];
                $busyRooms = (int) $summary['occupied'] + (int) $summary['program'];
                $occupancyPct = (int) $summary['total'] > 0
                    ? (int) round(100 * $busyRooms / (int) $summary['total'])
                    : 0;
            @endphp
            <div class="mb-4 rsb-acc" wire:key="rsb-acc-{{ $acc['accommodation_id'] }}" data-rsb-accommodation="{{ $acc['accommodation_id'] }}">
                <div class="ta-card rsb-kpi mb-3">
                    <div class="ta-card__head rsb-kpi__head flex-wrap gap-2">
                        <div class="fw-bold min-w-0">
                            <i class="bi bi-building me-1 text-primary"></i>{{ $acc['accommodation_name'] }}
                        </div>
                        @if($layoutEditMode && $panel === 'host' && $canEditBuildingLayout)
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
                    <div class="rsb-kpi__grid">
                        <article class="rsb-kpi__cell" data-rsb-kpi="total">
                            <span class="rsb-kpi__icon rsb-kpi__icon--success" aria-hidden="true"><i class="bi bi-door-open-fill"></i></span>
                            <div class="rsb-kpi__copy">
                                <div class="rsb-kpi__label">مجموع اتاق</div>
                                <div class="rsb-kpi__value-row">
                                    <strong class="rsb-kpi__value">{{ $summary['total'] }}</strong>
                                    <span class="rsb-kpi__unit">اتاق</span>
                                </div>
                                <div class="rsb-kpi__chips">
                                    <span class="rsb-kpi__chip rsb-kpi__chip--ok">{{ $summary['available'] }} آزاد</span>
                                </div>
                            </div>
                        </article>
                        <article class="rsb-kpi__cell" data-rsb-kpi="occupied">
                            <span class="rsb-kpi__icon rsb-kpi__icon--primary" aria-hidden="true"><i class="bi bi-person-fill"></i></span>
                            <div class="rsb-kpi__copy">
                                <div class="rsb-kpi__label">مهمان فعلی</div>
                                <div class="rsb-kpi__value-row">
                                    <strong class="rsb-kpi__value">{{ $summary['occupied'] }}</strong>
                                    <span class="rsb-kpi__unit">اتاق</span>
                                </div>
                                <div class="rsb-kpi__meta">
                                    {{ $summary['occupied_guests'] }} نفر
                                    @if((int) $summary['total'] > 0)
                                        · {{ $occupancyPct }}٪ اشغال
                                    @endif
                                </div>
                            </div>
                        </article>
                        <article class="rsb-kpi__cell" data-rsb-kpi="program">
                            <span class="rsb-kpi__icon rsb-kpi__icon--violet" aria-hidden="true"><i class="bi bi-flag-fill"></i></span>
                            <div class="rsb-kpi__copy">
                                <div class="rsb-kpi__label">اردو / برنامه</div>
                                <div class="rsb-kpi__value-row">
                                    <strong class="rsb-kpi__value">{{ $summary['program'] }}</strong>
                                    <span class="rsb-kpi__unit">اتاق</span>
                                </div>
                                <div class="rsb-kpi__meta">{{ $summary['program_guests'] }} نفر</div>
                            </div>
                        </article>
                        <article class="rsb-kpi__cell" data-rsb-kpi="future">
                            <span class="rsb-kpi__icon rsb-kpi__icon--info" aria-hidden="true"><i class="bi bi-calendar-event-fill"></i></span>
                            <div class="rsb-kpi__copy">
                                <div class="rsb-kpi__label">رزرو آینده</div>
                                <div class="rsb-kpi__value-row">
                                    <strong class="rsb-kpi__value">{{ $summary['future'] }}</strong>
                                    <span class="rsb-kpi__unit">اتاق</span>
                                </div>
                                @if((int) $summary['future_program'] > 0)
                                <div class="rsb-kpi__chips">
                                    <span class="rsb-kpi__chip rsb-kpi__chip--violet">{{ $summary['future_program'] }} اردوی آینده</span>
                                </div>
                                @else
                                <div class="rsb-kpi__meta">ورود بعد از تاریخ انتخاب‌شده</div>
                                @endif
                            </div>
                        </article>
                        <article class="rsb-kpi__cell" data-rsb-kpi="capacity_closed">
                            <span class="rsb-kpi__icon rsb-kpi__icon--warning" aria-hidden="true"><i class="bi bi-sliders"></i></span>
                            <div class="rsb-kpi__copy">
                                <div class="rsb-kpi__label">بسته (سیاست قیمتی)</div>
                                <div class="rsb-kpi__value-row">
                                    <strong class="rsb-kpi__value">{{ $summary['capacity_closed'] }}</strong>
                                    <span class="rsb-kpi__unit">اتاق</span>
                                </div>
                                <div class="rsb-kpi__meta">خارج از ظرفیت فروش روز</div>
                            </div>
                        </article>
                        <article class="rsb-kpi__cell" data-rsb-kpi="blocked">
                            <span class="rsb-kpi__icon rsb-kpi__icon--rose" aria-hidden="true"><i class="bi bi-lock-fill"></i></span>
                            <div class="rsb-kpi__copy">
                                <div class="rsb-kpi__label">مسدود</div>
                                <div class="rsb-kpi__value-row">
                                    <strong class="rsb-kpi__value">{{ $summary['blocked'] }}</strong>
                                    <span class="rsb-kpi__unit">اتاق</span>
                                </div>
                                <div class="rsb-kpi__meta">خارج از فروش توسط میزبان</div>
                            </div>
                        </article>
                    </div>
                </div>

                @if($layoutEditMode && $panel === 'host' && $canEditBuildingLayout)
                    <div class="room-status-rows-list rsb-physical-rooms"
                         data-rsb-rows-list
                         data-rsb-accommodation-id="{{ $acc['accommodation_id'] }}"
                         wire:key="rsb-edit-rows-{{ $acc['accommodation_id'] }}">
                    @foreach($editLayout['rows'] as $rowIndex => $rowIds)
                    @php $rowLabel = trim((string) ($editLayout['row_labels'][$rowIndex] ?? '')); @endphp
                    <div class="room-status-row-wrapper"
                         data-rsb-row-index="{{ $rowIndex }}"
                         wire:key="rsb-edit-row-wrap-{{ $acc['accommodation_id'] }}-{{ $rowIndex }}">
                        <div class="room-status-row__header">
                            <span class="room-status-row__drag" title="جابجایی ردیف">
                                <i class="bi bi-list"></i>
                            </span>
                            <input type="text"
                                   class="form-control form-control-sm room-status-row__name-input"
                                   value="{{ $rowLabel }}"
                                   placeholder="نام ردیف — مثلاً: طبقه اول"
                                   maxlength="60"
                                   wire:change="setRowLabel({{ $acc['accommodation_id'] }}, {{ $rowIndex }}, $event.target.value)">
                            <span class="room-status-row__index">ردیف {{ $rowIndex + 1 }}</span>
                        </div>
                        <div class="room-status-row room-status-row--editable"
                             data-rsb-rooms-row
                             data-rsb-accommodation-id="{{ $acc['accommodation_id'] }}"
                             data-rsb-row-index="{{ $rowIndex }}"
                             style="--rsb-cols: {{ (int) ($editLayout['cols'] ?? 6) }};"
                             wire:key="rsb-edit-row-{{ $acc['accommodation_id'] }}-{{ $rowIndex }}">
                        @foreach($rowIds as $roomId)
                        @php $room = $roomsById->get($roomId); @endphp
                        @if($room)
                        <div data-rsb-room-id="{{ $roomId }}"
                             wire:key="rsb-edit-room-{{ $roomId }}-{{ $rowIndex }}"
                             class="room-status-sortable-item">
                            <div class="room-status-box room-status-box--{{ $room['color'] }} room-status-box--editable {{ $room['has_future'] ? 'room-status-box--has-future' : '' }}">
                                <div class="room-status-box__top">
                                    <span class="room-status-box__drag" title="بکشید">
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
                    </div>
                    @endforeach
                    </div>
                @elseif($showPhysicalRooms)
                    <div class="rsb-physical-rooms" data-rsb-physical-rooms="{{ $acc['accommodation_id'] }}">
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
                                class="room-status-box room-status-box--{{ $room['color'] }} {{ ($room['has_future'] && empty($room['has_future_program'])) ? 'room-status-box--has-future' : '' }} {{ !empty($room['has_future_program']) ? 'room-status-box--has-future-program' : '' }}"
                                wire:key="rsb-room-{{ $room['id'] }}-{{ $viewDate }}">
                            <x-room-status.hover-tip :room="$room" />
                            <div class="room-status-box__name">{{ $room['name'] }}</div>
                            @if($room['bed_type'] || $room['room_type_name'])
                            <div class="room-status-box__type">{{ $room['bed_type'] ?: $room['room_type_name'] }}</div>
                            @endif
                            <div class="room-status-box__status">{{ $room['status_label'] }}</div>
                            @if($room['has_future'])
                            <div class="room-status-box__future"><i class="bi bi-calendar-event"></i> {{ !empty($room['has_future_program']) ? 'اردوی آینده' : 'رزرو آینده' }}</div>
                            @endif
                            @if($room['current_booking'])
                            <div class="room-status-box__guest text-truncate">{{ $room['current_booking']['guest_name'] }}</div>
                            @endif
                        </button>
                        @endforeach
                    </div>
                    @endforeach
                    </div>
                    <div class="text-center mt-2">
                        <button type="button"
                                wire:click="hidePhysicalRooms({{ $acc['accommodation_id'] }})"
                                class="btn btn-outline-secondary btn-sm"
                                data-rsb-hide-rooms="{{ $acc['accommodation_id'] }}">
                            <i class="bi bi-eye-slash me-1"></i>عدم نمایش
                        </button>
                    </div>
                @else
                    <div class="text-center">
                        <button type="button"
                                wire:click="showPhysicalRooms({{ $acc['accommodation_id'] }})"
                                class="btn btn-outline-primary btn-sm"
                                data-rsb-show-rooms="{{ $acc['accommodation_id'] }}">
                            <i class="bi bi-grid-3x3-gap me-1"></i>نمایش اتاق‌های فیزیکی
                        </button>
                    </div>
                @endif
            </div>
            @endforeach
            @endif
        </div>
    </div>

    @if($selectedRoom)
    <div class="modal fade show d-block room-status-detail-modal" tabindex="-1" style="background:rgba(0,0,0,.45);" wire:click.self="closeDetail">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable {{ $servicesBookingId ? 'modal-xl' : 'modal-lg' }}">
            <div class="modal-content" wire:click.stop>
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-door-open me-2"></i>{{ $selectedRoom['name'] }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="closeDetail"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge {{ $selectedRoom['color'] === 'purple' ? 'room-status-badge-purple' : 'text-bg-' . $selectedRoom['color'] }}">{{ $selectedRoom['status_label'] }}</span>
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
                        این اتاق در تاریخ انتخاب‌شده به‌دلیل <strong>سیاست قیمتی</strong> برای فروش بسته است.
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
                            @if(!empty($cb['is_program']))
                            <a wire:navigate href="{{ route($panel === 'admin' ? 'admin.programs.show' : 'host.programs.show', \App\Models\Program::where('booking_id', $cb['booking_id'])->value('id')) }}" class="btn btn-sm btn-outline-primary mt-2">مشاهده برنامه</a>
                            @elseif($panel === 'host')
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
                            <span><i class="bi bi-people me-1"></i>مهمانان و خدمات این اتاق</span>
                            @if($panel === 'host')
                            <a wire:navigate href="{{ route('host.bookings.show', $servicesBookingId) }}" class="btn btn-xs btn-outline-success" style="font-size:.72rem;">صفحه رزرو</a>
                            @else
                            <a wire:navigate href="{{ route('admin.bookings.show', $servicesBookingId) }}" class="btn btn-xs btn-outline-success" style="font-size:.72rem;">صفحه رزرو</a>
                            @endif
                        </div>
                        <div class="card-body py-2">
                            @php
                                $servicesBooking = $this->servicesBooking;
                                $canEditServices = $servicesBooking?->canEditServices() ?? false;
                                $veteranApplied = !empty($servicesBooking?->veteran_type_applied);
                            @endphp
                            @forelse($this->selectedRoomGuests as $guest)
                            <div class="border rounded mb-3 overflow-hidden" wire:key="rsb-room-guest-{{ $servicesBookingId }}-{{ $guest['sort_order'] }}">
                                <div class="bg-light px-3 py-2 border-bottom">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <span class="badge text-bg-secondary">نفر {{ $guest['sort_order'] + 1 }}</span>
                                        <strong class="small">{{ $guest['full_name'] }}</strong>
                                        @if($guest['relation'])
                                        <span class="badge bg-white text-muted border">{{ \App\Models\BookingGuestDetail::formatRelationLabel($guest['relation']) }}</span>
                                        @endif
                                    </div>
                                    @if($guest['identity_number'] || $guest['mobile'] || !empty($guest['residence_label']))
                                    <div class="text-muted mt-1" style="font-size:.72rem;">
                                        @if($guest['identity_number'])<span dir="ltr">{{ $guest['identity_label'] ?? 'کد ملی' }}: {{ $guest['identity_number'] }}</span>@endif
                                        @if(!empty($guest['residence_label']))<span class="ms-2">محل اقامت: {{ $guest['residence_label'] }}</span>@endif
                                        @if($guest['mobile'])<span class="ms-2" dir="ltr">موبایل: {{ $guest['mobile'] }}</span>@endif
                                    </div>
                                    @endif
                                </div>
                                <div class="p-2">
                                    @if($canEditServices)
                                    <livewire:booking-services-editor
                                        :booking-id="$servicesBookingId"
                                        :panel="$panel"
                                        :guest-sort-order="$guest['sort_order']"
                                        :key="'rsb-booking-services-'.$servicesBookingId.'-guest-'.$guest['sort_order']" />
                                    @elseif($servicesBooking)
                                    @php $guestServices = $servicesBooking->servicesForGuest($guest['sort_order']); @endphp
                                    @if($guestServices->isNotEmpty())
                                    <div class="d-flex flex-column gap-2">
                                        @foreach($guestServices as $service)
                                        <x-booking.service-line-readonly
                                            :service="$service"
                                            :veteran-type-applied="$veteranApplied"
                                            wire:key="rsb-guest-svc-ro-{{ $service->id }}" />
                                        @endforeach
                                    </div>
                                    @else
                                    <div class="alert alert-light border small py-2 mb-0">
                                        <i class="bi bi-info-circle me-1"></i>خدمتی برای این مهمان ثبت نشده است.
                                    </div>
                                    @endif
                                    @endif
                                </div>
                            </div>
                            @empty
                            <div class="alert alert-light border small py-2 mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                مهمان ثبت‌شده‌ای برای این اتاق یافت نشد. از صفحه رزرو، مهمانان را به اتاق‌ها اختصاص دهید.
                            </div>
                            @endforelse
                        </div>
                    </div>
                    @endif

                    @if($actionMessage)
                    <div class="alert alert-success py-2 small mt-3 mb-0">
                        <i class="bi bi-check-circle me-1"></i>{{ $actionMessage }}
                    </div>
                    @endif

                    @if($panel === 'host' && !empty($selectedRoomRates) && empty($selectedRoom['current_booking']))
                    <div class="card border-primary border-opacity-25 mt-3">
                        <div class="card-header bg-primary-subtle py-2 small fw-semibold">
                            <i class="bi bi-calendar-plus me-1"></i>رزرو این اتاق
                        </div>
                        <div class="card-body py-2">
                            <p class="small text-muted mb-2">تعرفه را انتخاب کنید (قیمت به ازای هر تخت است)؛ سپس به صفحه رزرو دستی می‌روید تا تاریخ ورود/خروج و تعداد نفرات را مشخص کنید. اتاق «{{ $selectedRoom['name'] }}» از قبل انتخاب می‌شود.</p>
                            <div class="mb-2">
                                <label class="form-label small mb-1">تعرفه <span class="text-danger">*</span></label>
                                <select wire:model="bookingRoomRateId"
                                        class="form-select form-select-sm @error('bookingRoomRateId') is-invalid @enderror">
                                    <option value="">انتخاب تعرفه...</option>
                                    @foreach($selectedRoomRates as $rate)
                                    <option value="{{ $rate['id'] }}">{{ $rate['name'] }} — {{ \App\Support\PdfPersian::toPersianDigits(number_format($rate['price_per_night'])) }} ریال/شب/تخت</option>
                                    @endforeach
                                </select>
                                @error('bookingRoomRateId')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <button type="button"
                                    wire:click="goToManualBooking"
                                    class="btn btn-primary btn-sm w-100"
                                    @disabled(!$bookingRoomRateId)>
                                <i class="bi bi-arrow-left-circle me-1"></i>ادامه
                            </button>
                        </div>
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
        --rsb-gap: .5rem;
        --rsb-cols-cap: 12;
        display: grid;
        grid-template-columns: repeat(min(var(--rsb-cols, 4), var(--rsb-cols-cap)), minmax(0, 1fr));
        gap: var(--rsb-gap);
        margin-bottom: .5rem;
    }
    .room-status-row--editable {
        position: relative;
        min-height: 4.5rem;
        padding: .5rem;
        border: 1.5px dashed rgba(var(--bs-primary-rgb), .35);
        border-radius: .65rem;
        background: rgba(var(--bs-primary-rgb), .03);
    }
    .room-status-row-wrapper {
        display: flex;
        flex-direction: column;
        gap: .35rem;
        margin-bottom: .15rem;
    }
    .room-status-row-wrapper > .room-status-row__header {
        position: static;
        top: auto;
        right: auto;
        left: auto;
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: 0 .15rem;
    }
    .room-status-row__header {
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
    .room-status-rows-list {
        display: flex;
        flex-direction: column;
        gap: .5rem;
    }
    .room-status-row__drag {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--bs-secondary);
        cursor: grab;
        padding: .15rem .35rem;
        border-radius: .25rem;
        flex-shrink: 0;
        font-size: 1.1rem;
        line-height: 1;
        border: 1px dashed rgba(var(--bs-secondary-rgb), .35);
        background: rgba(var(--bs-secondary-rgb), .06);
    }
    .room-status-row__drag .bi {
        font-family: "bootstrap-icons" !important;
        pointer-events: none;
    }
    .room-status-row__drag:active { cursor: grabbing; }
    .room-status-row__drag:hover { color: var(--bs-warning); background: rgba(var(--bs-warning-rgb), .12); }
    .rsb-dnd-placeholder.room-status-row-wrapper {
        opacity: .55;
    }
    .rsb-dnd-placeholder.room-status-row-wrapper .room-status-row--editable {
        border-style: solid;
        border-color: rgba(var(--bs-warning-rgb), .6);
        background: rgba(var(--bs-warning-rgb), .06);
    }
    .rsb-dnd-dragging.room-status-row-wrapper,
    .rsb-dnd-synth-dragging.room-status-row-wrapper {
        box-shadow: 0 6px 18px rgba(0,0,0,.1);
        z-index: 2;
    }
    .rsb-dnd-drop-parent.room-status-row--editable {
        border-color: rgba(var(--bs-primary-rgb), .65);
        background: rgba(var(--bs-primary-rgb), .07);
    }
    .rsb-dnd-drop-zone .room-status-box {
        outline: 2px dashed rgba(var(--bs-primary-rgb), .55);
        outline-offset: 2px;
    }
    .room-status-sortable-item { min-width: 0; }
    .room-status-box {
        position: relative;
        border: 2px solid var(--bs-border-color);
        border-radius: .65rem;
        padding: .55rem .7rem;
        width: 100%;
        text-align: right;
        background: var(--bs-body-bg);
        cursor: pointer;
        transition: transform .12s, box-shadow .12s;
        overflow: visible;
    }
    .room-status-box__hover-tip {
        position: absolute;
        bottom: calc(100% + .4rem);
        right: 50%;
        transform: translateX(50%);
        z-index: 30;
        max-width: min(18rem, 90vw);
        padding: .4rem .6rem;
        font-size: .68rem;
        line-height: 1.4;
        text-align: right;
        color: #fff;
        background: rgba(33, 37, 41, .94);
        border-radius: .4rem;
        box-shadow: 0 4px 14px rgba(0, 0, 0, .18);
        pointer-events: none;
        opacity: 0;
        visibility: hidden;
        transition: opacity .12s ease, visibility .12s ease;
        white-space: normal;
    }
    .room-status-box__hover-tip::after {
        content: '';
        position: absolute;
        top: 100%;
        right: 50%;
        transform: translateX(50%);
        border: 5px solid transparent;
        border-top-color: rgba(33, 37, 41, .94);
    }
    .room-status-box__hover-tip-row {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: .65rem;
    }
    .room-status-box__hover-tip-row + .room-status-box__hover-tip-row {
        margin-top: .2rem;
    }
    .room-status-box__hover-tip-row--muted {
        opacity: .88;
        font-size: .64rem;
    }
    .room-status-box__hover-tip-key {
        flex-shrink: 0;
        font-size: .62rem;
        opacity: .8;
    }
    .room-status-box:hover .room-status-box__hover-tip {
        opacity: 1;
        visibility: visible;
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
    .room-status-box--purple {
        --rsb-purple-rgb: 111, 66, 193;
        border-color: rgba(var(--rsb-purple-rgb), .55);
        background: rgba(var(--rsb-purple-rgb), .12);
    }
    .room-status-legend-purple,
    .room-status-badge-purple {
        color: #5a32a3 !important;
        background: rgba(111, 66, 193, .12) !important;
        border: 1px solid rgba(111, 66, 193, .35) !important;
    }
    .room-status-box--warning { border-color: rgba(var(--bs-warning-rgb), .55); background: rgba(var(--bs-warning-rgb), .1); background-image: repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(var(--bs-warning-rgb), .08) 4px, rgba(var(--bs-warning-rgb), .08) 5px); }
    .room-status-box--danger { border-color: rgba(var(--bs-danger-rgb), .5); background: rgba(var(--bs-danger-rgb), .08); background-image: repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(var(--bs-danger-rgb), .1) 4px, rgba(var(--bs-danger-rgb), .1) 5px); }
    .room-status-box--has-future:not(.room-status-box--primary):not(.room-status-box--purple) {
        box-shadow: inset 0 -3px 0 var(--bs-info);
    }
    .room-status-box--has-future-program:not(.room-status-box--purple) {
        box-shadow: inset 0 -3px 0 #6f42c1;
    }
    .room-status-box--purple .room-status-box__future {
        color: #6f42c1;
    }
    .rsb-dnd-placeholder .room-status-box {
        opacity: .45;
        border-style: dashed;
    }
    .rsb-dnd-dragging .room-status-box,
    .rsb-dnd-synth-dragging .room-status-box {
        box-shadow: 0 8px 20px rgba(0,0,0,.12);
        transform: scale(1.02);
        transition: none;
    }
    .room-status-sortable-item.rsb-dnd-dragging,
    .room-status-sortable-item.rsb-dnd-synth-dragging {
        z-index: 5;
    }

    #room-status-board-root {
        min-width: 0;
        max-width: 100%;
    }
    .room-status-board {
        min-width: 0;
        max-width: 100%;
    }
    .rsb-kpi { overflow: hidden; }
    .rsb-kpi__head {
        align-items: flex-start;
        padding-bottom: .85rem;
    }
    .rsb-kpi__grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .rsb-kpi__cell {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
        padding: 18px 20px;
        background: transparent;
    }
    .rsb-kpi__grid > .rsb-kpi__cell {
        border-inline-end: 1px solid var(--bs-border-color, #eaecf0);
        border-bottom: 1px solid var(--bs-border-color, #eaecf0);
    }
    .rsb-kpi__grid > .rsb-kpi__cell:nth-child(3n) { border-inline-end: none; }
    .rsb-kpi__grid > .rsb-kpi__cell:nth-last-child(-n+3) { border-bottom: none; }
    .rsb-kpi__icon {
        flex: 0 0 42px;
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }
    .rsb-kpi__icon--primary { color: #465fff; background: rgba(70, 95, 255, .12); }
    .rsb-kpi__icon--info { color: #0ba5ec; background: rgba(11, 165, 236, .12); }
    .rsb-kpi__icon--warning { color: #f79009; background: rgba(247, 144, 9, .12); }
    .rsb-kpi__icon--success { color: #12b76a; background: rgba(18, 183, 106, .12); }
    .rsb-kpi__icon--violet { color: #7a5af8; background: rgba(122, 90, 248, .12); }
    .rsb-kpi__icon--rose { color: #e31b54; background: rgba(227, 27, 84, .1); }
    .rsb-kpi__copy { min-width: 0; flex: 1; }
    .rsb-kpi__label {
        color: #667085;
        font-size: .78rem;
        font-weight: 600;
        margin-bottom: 4px;
        line-height: 1.3;
    }
    .rsb-kpi__value-row {
        display: flex;
        align-items: baseline;
        gap: 8px;
        min-width: 0;
        flex-wrap: wrap;
    }
    .rsb-kpi__value {
        font-size: 1.45rem;
        font-weight: 700;
        line-height: 1.15;
        color: #101828;
        letter-spacing: -.02em;
        font-variant-numeric: tabular-nums;
    }
    .rsb-kpi__unit {
        color: #667085;
        font-size: .8rem;
        font-weight: 500;
        white-space: nowrap;
    }
    .rsb-kpi__meta {
        margin-top: 6px;
        color: #98a2b3;
        font-size: .75rem;
        line-height: 1.45;
    }
    .rsb-kpi__chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
    }
    .rsb-kpi__chip {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 600;
        line-height: 1.4;
        white-space: nowrap;
    }
    .rsb-kpi__chip--ok { color: #027a48; background: #ecfdf3; }
    .rsb-kpi__chip--violet { color: #6941c6; background: #f4f3ff; }
    [data-bs-theme="dark"] .rsb-kpi__label,
    [data-bs-theme="dark"] .rsb-kpi__unit { color: #98a2b3; }
    [data-bs-theme="dark"] .rsb-kpi__value { color: #f2f4f7; }
    [data-bs-theme="dark"] .rsb-kpi__meta { color: #667085; }
    [data-bs-theme="dark"] .rsb-kpi__chip--ok { color: #6ce9a6; background: rgba(18, 183, 106, .16); }
    [data-bs-theme="dark"] .rsb-kpi__chip--violet { color: #bdb4fe; background: rgba(122, 90, 248, .16); }
    .room-status-board-head {
        align-items: flex-start;
    }
    .room-status-board-toolbar {
        max-width: 100%;
    }
    .room-status-board-date-group {
        width: auto;
        min-width: 0;
        flex: 1 1 8.5rem;
        max-width: 12rem;
    }
    .room-status-board-date-group .rsb-jalali-date {
        min-width: 0;
        width: 8.5rem;
        flex: 1 1 auto;
    }
    .room-status-box__name,
    .room-status-box__type,
    .room-status-box__status,
    .room-status-box__guest {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .room-status-detail-modal {
        padding: 0;
    }
    .room-status-detail-modal .modal-dialog {
        margin: 1.75rem auto;
        width: calc(100% - 1.5rem);
    }
    .room-status-detail-modal .modal-lg {
        max-width: min(800px, calc(100vw - 1.5rem));
    }
    .room-status-detail-modal .modal-xl {
        max-width: min(1140px, calc(100vw - 1.5rem));
    }
    .room-status-detail-modal .modal-content {
        max-height: min(92dvh, 920px);
    }

    @media (max-width: 1199.98px) {
        .room-status-row:not(.room-status-row--editable) { --rsb-cols-cap: 5; }
        .rsb-kpi__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .rsb-kpi__grid > .rsb-kpi__cell { border-inline-end: 1px solid var(--bs-border-color, #eaecf0); border-bottom: 1px solid var(--bs-border-color, #eaecf0); }
        .rsb-kpi__grid > .rsb-kpi__cell:nth-child(3n) { border-inline-end: 1px solid var(--bs-border-color, #eaecf0); }
        .rsb-kpi__grid > .rsb-kpi__cell:nth-child(2n) { border-inline-end: none; }
        .rsb-kpi__grid > .rsb-kpi__cell:nth-last-child(-n+3) { border-bottom: 1px solid var(--bs-border-color, #eaecf0); }
        .rsb-kpi__grid > .rsb-kpi__cell:nth-last-child(-n+2) { border-bottom: none; }
    }
    @media (max-width: 991.98px) {
        .room-status-row:not(.room-status-row--editable) { --rsb-cols-cap: 4; }
        .room-status-box { padding: .5rem .55rem; }
    }
    @media (max-width: 767.98px) {
        .room-status-row:not(.room-status-row--editable) { --rsb-cols-cap: 3; }
        .room-status-board-head {
            flex-direction: column;
            align-items: stretch;
        }
        .room-status-board-toolbar,
        .room-status-board-date-wrap {
            width: 100%;
        }
        .room-status-board-date-group {
            max-width: none;
            flex: 1 1 auto;
        }
        .room-status-board-date-group .rsb-jalali-date {
            width: auto;
        }
        .room-status-board-toolbar > .btn {
            flex: 1 1 auto;
            min-width: 0;
        }
        .room-status-rows-list {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 4px;
            margin-inline: -4px;
            padding-inline: 4px;
        }
        .room-status-row--editable {
            min-width: calc(var(--rsb-cols, 4) * 7rem);
        }
        .room-status-row__header {
            flex-wrap: wrap;
        }
        .room-status-detail-modal .modal-dialog {
            margin: .5rem;
            width: calc(100% - 1rem);
            max-width: none;
        }
        .room-status-detail-modal .modal-content {
            max-height: calc(100dvh - 1rem);
        }
        .room-status-detail-modal .modal-header,
        .room-status-detail-modal .modal-footer {
            padding: .75rem 1rem;
        }
        .room-status-detail-modal .modal-body {
            padding: .85rem 1rem;
        }
    }
    @media (max-width: 575.98px) {
        .room-status-row:not(.room-status-row--editable) {
            --rsb-cols-cap: 2;
            --rsb-gap: .4rem;
        }
        .rsb-kpi__grid { grid-template-columns: minmax(0, 1fr); }
        .rsb-kpi__grid > .rsb-kpi__cell,
        .rsb-kpi__grid > .rsb-kpi__cell:nth-child(2n),
        .rsb-kpi__grid > .rsb-kpi__cell:nth-child(3n),
        .rsb-kpi__grid > .rsb-kpi__cell:nth-last-child(-n+3),
        .rsb-kpi__grid > .rsb-kpi__cell:nth-last-child(-n+2) {
            border-inline-end: none;
            border-bottom: 1px solid var(--bs-border-color, #eaecf0);
        }
        .rsb-kpi__grid > .rsb-kpi__cell:last-child { border-bottom: none; }
        .rsb-kpi__cell { padding: 14px 16px; }
        .rsb-kpi__value { font-size: 1.25rem; }
        .room-status-box {
            padding: .45rem .5rem;
            border-radius: .55rem;
        }
        .room-status-box__name { font-size: .74rem; }
        .room-status-box__type,
        .room-status-box__future { font-size: .58rem; }
        .room-status-box__status,
        .room-status-box__guest { font-size: .62rem; }
        .room-status-box__hover-tip {
            max-width: min(16rem, calc(100vw - 1.5rem));
            font-size: .64rem;
        }
    }
    @media (hover: none) {
        .room-status-box__hover-tip {
            display: none !important;
        }
        button.room-status-box:hover {
            transform: none;
            box-shadow: none;
        }
    }
    </style>
    @endonce

</div>

@php
    $steps = [
        1 => ['icon' => 'bi-info-circle', 'label' => 'اطلاعات پایه'],
        2 => ['icon' => 'bi-door-open', 'label' => 'انتخاب اتاق'],
        3 => ['icon' => 'bi-bag-plus', 'label' => 'خدمات و نرخ'],
        4 => ['icon' => 'bi-cash-coin', 'label' => 'مالی'],
        5 => ['icon' => 'bi-person-lines-fill', 'label' => 'مهمانان'],
        6 => ['icon' => 'bi-people', 'label' => 'ذینفعان'],
        7 => ['icon' => 'bi-check-circle', 'label' => 'تأیید'],
    ];
    $showRoute = $panel === 'admin' ? 'admin.programs.show' : 'host.programs.show';
    $indexRoute = $panel === 'admin' ? 'admin.programs.index' : 'host.programs.index';
@endphp

<div id="program-form-root" x-on:manual-booking-rooms-selected.window="$wire.call('onRoomsSelected', $event.detail.rooms ?? [])">
    @if($step < 7)
    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                @foreach($steps as $num => $meta)
                    @if($num < 7)
                    <button type="button"
                            wire:click="goToStep({{ $num }})"
                            class="btn btn-sm {{ $step === $num ? 'btn-primary' : ($step > $num ? 'btn-outline-primary' : 'btn-outline-secondary') }}"
                            @disabled($num > $step)>
                        <i class="bi {{ $meta['icon'] }} me-1"></i>{{ $num }}. {{ $meta['label'] }}
                    </button>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
    @endif

    @error('submit')<div class="alert alert-danger">{{ $message }}</div>@enderror

    {{-- Step 1 --}}
    @if($step === 1)
    <div class="card shadow-sm">
        <div class="card-header fw-bold"><i class="bi bi-flag me-2"></i>اطلاعات برنامه / اردو</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">اقامتگاه <span class="text-danger">*</span></label>
                    <select wire:model.live="accommodationId" class="form-select @error('accommodationId') is-invalid @enderror">
                        <option value="0">انتخاب اقامتگاه</option>
                        @foreach($myAccommodations as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                        @endforeach
                    </select>
                    @error('accommodationId')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">نوع برنامه <span class="text-danger">*</span></label>
                    <select wire:model="programType" class="form-select">
                        @foreach(\App\Models\Program::typeOptions() as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">عنوان برنامه <span class="text-danger">*</span></label>
                    <input type="text" wire:model="title" class="form-control @error('title') is-invalid @enderror" placeholder="مثلاً اردوی دانش‌آموزی بهار ۱۴۰۴">
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <x-program.date-fields :start-date="$startDate" :end-date="$endDate" />
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">تعداد نفرات <span class="text-danger">*</span></label>
                    <input type="number" wire:model="guestCount" min="1" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">تعداد اتاق اختصاص داده شده به این رزرو <span class="text-danger">*</span></label>
                    <input type="number" wire:model="roomsAllocated" min="1" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">طرف حساب <span class="text-danger">*</span></label>
                    <input type="text" wire:model="counterparty" class="form-control @error('counterparty') is-invalid @enderror">
                    @error('counterparty')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">کارفرما</label>
                    <input type="text" wire:model="employer" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">پیمانکار</label>
                    <input type="text" wire:model="contractor" class="form-control">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">توضیحات</label>
                    <textarea wire:model="description" rows="3" class="form-control"></textarea>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Step 2 --}}
    @if($step === 2)
    <div class="card shadow-sm">
        <div class="card-header fw-bold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-door-open me-2"></i>انتخاب اتاق‌های فیزیکی</span>
            <span class="badge bg-primary">{{ count($roomLines) }} / {{ $roomsAllocated }}</span>
        </div>
        <div class="card-body">
            @error('roomLines')<div class="alert alert-danger">{{ $message }}</div>@enderror

            @if($accommodation)
            <div class="alert alert-info small">
                بازه اقامت: <strong dir="ltr">{{ $startDate }}</strong> تا <strong dir="ltr">{{ $endDate }}</strong>
                — باید <strong>{{ $roomsAllocated }}</strong> اتاق فیزیکی انتخاب شود.
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3">
                @php
                    $canOpenRoomPicker = $this->roomsRemaining > 0
                        && $startDate !== ''
                        && $endDate !== '';
                @endphp
                <button type="button"
                        wire:click="openRoomPicker"
                        class="btn btn-primary"
                        @disabled(!$canOpenRoomPicker)>
                    <i class="bi bi-door-open me-1"></i>
                    انتخاب {{ $this->roomsRemaining }} اتاق (یکجا)
                </button>
                @if($roomLines !== [])
                <button type="button" wire:click="clearRoomLines" class="btn btn-outline-danger">
                    <i class="bi bi-x-circle me-1"></i>پاک کردن انتخاب‌ها
                </button>
                @endif
            </div>

            @if($roomLines !== [])
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>نوع اتاق</th>
                            <th>اتاق فیزیکی</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($roomLines as $i => $line)
                        <tr wire:key="prog-room-{{ $i }}">
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $line['room_type_name'] }}</td>
                            <td><span class="badge bg-info-subtle text-info border">{{ $line['room_name'] }}</span></td>
                            <td>
                                <button type="button" wire:click="removeRoomLine({{ $i }})" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="alert alert-warning mb-0">ابتدا تاریخ‌ها را در مرحله قبل ثبت کنید.</div>
            @endif
            @else
            <div class="alert alert-warning">ابتدا اقامتگاه را در مرحله قبل انتخاب کنید.</div>
            @endif
        </div>
    </div>
    @endif

    {{-- Room picker modal — always in DOM so Alpine can init (scripts loaded via app.js) --}}
    <x-manual-booking.room-picker />

    {{-- Step 3 --}}
    @if($step === 3)
    <div class="card shadow-sm">
        <div class="card-header fw-bold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-bag-plus me-2"></i>تعریف خدمات و نرخ</span>
            <button type="button" wire:click="addService" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus"></i> خدمت</button>
        </div>
        <div class="card-body">
            @foreach($services as $si => $service)
            @php
                $catalogId = $service['service_catalog_id'] ?? '';
                $selectedCatalog = $catalogId && $catalogId !== 'custom' ? $serviceCatalog->firstWhere('id', (int) $catalogId) : null;
                $activeVariants = $selectedCatalog?->variants?->where('is_active', true) ?? collect();
                $hasVariants = $activeVariants->isNotEmpty();
            @endphp
            <div class="border rounded p-3 mb-3 bg-light" wire:key="prog-svc-{{ $si }}">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">خدمت</label>
                        <select wire:model.live="services.{{ $si }}.service_catalog_id" class="form-select form-select-sm">
                            <option value="">— انتخاب —</option>
                            @foreach($serviceCatalog as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                            <option value="custom">سایر (دستی)</option>
                        </select>
                    </div>
                    @if($hasVariants)
                    <div class="col-md-3">
                        <label class="form-label small mb-1">نوع</label>
                        <select wire:model.live="services.{{ $si }}.service_catalog_variant_id" class="form-select form-select-sm">
                            <option value="">— نوع —</option>
                            @foreach($activeVariants as $variant)
                                <option value="{{ $variant->id }}">{{ $variant->name }} ({{ number_format($variant->price) }})</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="col-md-{{ $hasVariants ? 3 : 4 }}">
                        <label class="form-label small mb-1">نام</label>
                        <input type="text" wire:model.live="services.{{ $si }}.name" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">قیمت (تومان)</label>
                        <x-money-input wire:model.live="services.{{ $si }}.unit_price" class="form-control form-control-sm" min="0" />
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small mb-1">تعداد</label>
                        <input type="number" wire:model.live="services.{{ $si }}.quantity" min="1" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="text-end mt-2">
                    <button type="button" wire:click="removeService({{ $si }})" class="btn btn-sm btn-outline-danger" @disabled(count($services) <= 1)>
                        <i class="bi bi-trash"></i> حذف
                    </button>
                </div>
            </div>
            @endforeach

            <div class="alert alert-secondary small mb-0">
                جمع خدمات: <strong>{{ number_format($this->servicesSubtotal) }} تومان</strong>
            </div>
        </div>
    </div>
    @endif

    {{-- Step 4 --}}
    @if($step === 4)
    <div class="card shadow-sm"
         x-data="{
            servicesSubtotal: {{ $this->servicesSubtotal }},
            base: 0,
            discount: 0,
            deposit: 0,
            parseMoney(v) {
                if (window.parseMoney) return window.parseMoney(String(v ?? ''));
                return parseInt(String(v ?? '').replace(/[^\d]/g, '') || '0', 10);
            },
            fmt(n) {
                if (window.formatMoney) return window.formatMoney(n);
                return Number(n || 0).toLocaleString('en-US');
            },
            syncFromWire() {
                this.base = this.parseMoney(this.$wire.get('basePrice'));
                this.discount = this.parseMoney(this.$wire.get('discountAmount'));
                this.deposit = this.parseMoney(this.$wire.get('depositAmount'));
            },
            get total() { return Math.max(0, this.base + this.servicesSubtotal - this.discount); },
            get remaining() { return Math.max(0, this.total - this.deposit); },
            init() {
                this.syncFromWire();
                this.$wire.$watch('basePrice', () => this.syncFromWire());
                this.$wire.$watch('discountAmount', () => this.syncFromWire());
                this.$wire.$watch('depositAmount', () => this.syncFromWire());
            }
         }">
        <div class="card-header fw-bold"><i class="bi bi-cash-coin me-2"></i>مالی · تعریف خدمات و نوع پرداخت</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label small fw-semibold">نوع پرداخت <span class="text-danger">*</span></label>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach(\App\Models\Program::paymentTypeOptions() as $key => $label)
                        <label class="form-check">
                            <input type="radio" wire:model.live="paymentType" value="{{ $key }}" class="form-check-input">
                            <span class="form-check-label">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">بارگذاری مدارک / اسناد (PDF یا تصویر)</label>
                    <input type="file" wire:model="paymentDocuments" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*" class="form-control">
                    <div class="form-text">فرمت‌های مجاز: PDF، JPG، PNG، WEBP — حداکثر ۱۰ مگابایت</div>
                    <div wire:loading wire:target="paymentDocuments" class="small text-muted mt-1">در حال بارگذاری...</div>
                    @error('paymentDocuments.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label small fw-semibold">قیمت پایه برنامه (تومان) <span class="text-danger">*</span></label>
                    <x-money-input wire:model.live="basePrice" class="form-control" min="0" />
                    @error('basePrice')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">جمع خدمات (تومان)</label>
                    <input type="text" class="form-control" readonly :value="fmt(servicesSubtotal)">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">مبلغ تخفیف (تومان)</label>
                    <x-money-input wire:model.live="discountAmount" class="form-control" min="0" />
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">بیعانه (تومان)</label>
                    <x-money-input wire:model.live="depositAmount" class="form-control" min="0" />
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">باقیمانده (تومان)</label>
                    <input type="text" class="form-control fw-bold text-success" readonly :value="fmt(remaining)">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">یادداشت</label>
                    <textarea wire:model="notes" rows="2" class="form-control"></textarea>
                </div>
            </div>

            <div class="card bg-primary-subtle border-0 mt-3">
                <div class="card-body py-2 d-flex justify-content-between">
                    <span>مبلغ کل برنامه:</span>
                    <strong x-text="fmt(total) + ' تومان'"></strong>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Step 5 — Guests --}}
    @if($step === 5)
        @include('livewire.concerns.program-guests-step')
    @endif

    {{-- Step 6 — Beneficiaries --}}
    @if($step === 6)
    <div class="card shadow-sm">
        <div class="card-header fw-bold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-people me-2"></i>ذینفعان و هزینه‌های برنامه</span>
            <button type="button" wire:click="addBeneficiaryRow" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus"></i> ردیف</button>
        </div>
        <div class="card-body">
            @if($beneficiaryRows === [])
            <div class="alert alert-info small">می‌توانید ذینفعان و بدهی‌های برنامه را ثبت کنید (اختیاری).</div>
            <button type="button" wire:click="addBeneficiaryRow" class="btn btn-sm btn-primary">افزودن ذینفع</button>
            @endif

            @foreach($beneficiaryRows as $bi => $row)
            <div class="border rounded p-3 mb-3" wire:key="prog-ben-{{ $bi }}">
                <div class="row g-2">
                    <div class="col-md-5">
                        <label class="form-label small mb-1">ذینفع</label>
                        <select wire:model="beneficiaryRows.{{ $bi }}.program_beneficiary_id" class="form-select form-select-sm">
                            <option value="">— انتخاب —</option>
                            @foreach($beneficiaries as $b)
                                <option value="{{ $b->id }}">{{ $b->displayLabel() }}</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="openBeneficiaryModal({{ $bi }})" class="btn btn-link btn-sm p-0 text-decoration-none mt-1">
                            <i class="bi bi-plus-circle me-1"></i>ذینفع در لیست نیست؟ افزودن
                        </button>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">میزان بدهی (تومان)</label>
                        <x-money-input wire:model="beneficiaryRows.{{ $bi }}.debt_amount" class="form-control form-control-sm" min="0" />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">مدارک ضمیمه (PDF یا تصویر)</label>
                        <input type="file" wire:model="beneficiaryRows.{{ $bi }}.documents" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*" class="form-control form-control-sm">
                        @error('beneficiaryRows.'.$bi.'.documents.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-1">توضیح</label>
                        <textarea wire:model="beneficiaryRows.{{ $bi }}.description" rows="2" class="form-control form-control-sm"></textarea>
                    </div>
                </div>
                <div class="text-end mt-2">
                    <button type="button" wire:click="removeBeneficiaryRow({{ $bi }})" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    @if($showAddBeneficiary)
    <div class="modal-backdrop fade show" style="z-index:1080;"></div>
    <div class="modal fade show" style="display:block;z-index:1085;" tabindex="-1" role="dialog" wire:keydown.escape="closeBeneficiaryModal">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content" style="border-top:4px solid #22c55e;">
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center gap-2">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle" style="width:36px;height:36px;">
                            <i class="bi bi-person-plus-fill text-success"></i>
                        </span>
                        افزودن ذینفع جدید
                    </h5>
                    <button type="button" class="btn-close" wire:click="closeBeneficiaryModal" aria-label="بستن"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small mb-3">
                        ذینفعان در کل سامانه یکپارچه هستند و پس از ثبت در همه اقامتگاه‌ها قابل انتخاب‌اند.
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label small">نام ذینفع <span class="text-danger">*</span></label>
                            <input type="text" wire:model="newBeneficiaryName" class="form-control form-control-sm">
                            @error('newBeneficiaryName')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">شناسه ذینفع <span class="text-danger">*</span></label>
                            <input type="text" wire:model="newBeneficiaryCode" class="form-control form-control-sm">
                            @error('newBeneficiaryCode')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">کد ملی / شناسه اقتصادی <span class="text-danger">*</span></label>
                            <input type="text" wire:model="newBeneficiaryNationalId" class="form-control form-control-sm">
                            @error('newBeneficiaryNationalId')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">شماره همراه <span class="text-danger">*</span></label>
                            <input type="text" wire:model="newBeneficiaryMobile" class="form-control form-control-sm" placeholder="09xxxxxxxxx">
                            @error('newBeneficiaryMobile')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" wire:click="closeBeneficiaryModal" class="btn btn-outline-secondary">انصراف</button>
                    <button type="button" wire:click="addBeneficiaryToCatalog" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i>ذخیره و انتخاب
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
    @endif

    {{-- Step 7 — Confirmation --}}
    @if($step === 7 && $createdProgram)
    <div class="card shadow-sm border-success">
        <div class="card-body text-center py-4">
            <i class="bi bi-check-circle-fill text-success" style="font-size:3rem;"></i>
            <h5 class="fw-bold mt-3">برنامه با موفقیت ثبت شد</h5>
            <p class="text-muted mb-3">{{ $createdProgram->title }}</p>
            @if($createdProgram->booking)
            <p class="small">کد پیگیری رزرو: <strong dir="ltr">{{ $createdProgram->booking->tracking_code }}</strong></p>
            @endif
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <a wire:navigate href="{{ route($showRoute, $createdProgram) }}" class="btn btn-primary">مشاهده برنامه</a>
                @if($createdProgram->booking)
                <a wire:navigate href="{{ route($panel === 'admin' ? 'admin.bookings.show' : 'host.bookings.show', $createdProgram->booking) }}" class="btn btn-outline-primary">مشاهده رزرو</a>
                @endif
                <a wire:navigate href="{{ route($indexRoute) }}" class="btn btn-outline-secondary">بازگشت به لیست</a>
            </div>
        </div>
    </div>
    @endif

    @if($step < 7)
    <div class="d-flex justify-content-between mt-3">
        <button type="button" wire:click="prevStep" class="btn btn-outline-secondary" @disabled($step <= 1)>
            <i class="bi bi-arrow-right me-1"></i>مرحله قبل
        </button>
        @if($step < 6)
        <button type="button"
                class="btn btn-primary"
                @if($step === 1)
                    x-data
                    @click="async () => { if (window.syncProgramDates) await window.syncProgramDates(); $wire.nextStep(); }"
                @else
                    wire:click="nextStep"
                @endif>
            مرحله بعد<i class="bi bi-arrow-left ms-1"></i>
        </button>
        @else
        <button type="button" wire:click="submit" class="btn btn-success" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="submit"><i class="bi bi-check-lg me-1"></i>ثبت نهایی برنامه</span>
            <span wire:loading wire:target="submit">در حال ثبت...</span>
        </button>
        @endif
    </div>
    @endif
</div>

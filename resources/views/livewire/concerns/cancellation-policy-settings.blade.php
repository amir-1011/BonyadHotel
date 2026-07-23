<div wire:key="cancellation-policy-{{ $filterKey ?? ($accommodation->id ?? 'single') }}">

@php
    $canEditCancellationPolicy = ($panel ?? 'admin') !== 'host'
        || auth()->user()?->hostCan('accommodations.cancellation-policy', 'edit');
@endphp

@if(($panel ?? 'admin') === 'host' && !$canEditCancellationPolicy)
<div class="alert alert-warning small"><i class="bi bi-lock me-1"></i>فقط مجوز مشاهده دارید — امکان تغییر سیاست کنسلی وجود ندارد.</div>
@endif

<div class="ta-page-head mb-4">
    <div>
        @isset($isAllAccommodationsSelected)
            <p class="text-muted small mb-0">
                @if($isAllAccommodationsSelected)
                    تعیین درصد بازگشت وجه و دلایل کنسلی — تغییرات این صفحه روی <strong>همه {{ $accommodationCount }} اقامتگاه</strong> اعمال می‌شود.
                @elseif($scopedAccommodationCount === 1)
                    @php
                        $singleAcc = collect($dashboardAccommodationOptions)->firstWhere('id', $scopedAccommodationIds[0] ?? null);
                    @endphp
                    سیاست کنسلی برای اقامتگاه <strong>{{ $singleAcc['name'] ?? 'انتخاب‌شده' }}</strong> — تغییرات فقط روی این اقامتگاه اعمال می‌شود.
                @else
                    سیاست کنسلی برای <strong>{{ $scopedAccommodationCount }} اقامتگاه</strong> انتخاب‌شده — تغییرات فقط روی اقامتگاه‌های فیلترشده اعمال می‌شود.
                @endif
            </p>
        @else
            <p class="text-muted small mb-0">
                سیاست کنسلی و استرداد وجه برای اقامتگاه <strong>{{ $accommodation->name }}</strong>.
            </p>
        @endisset
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        @isset($isAllAccommodationsSelected)
            @if($this->showDashboardAccommodationFilter())
                @include('components.dashboard.accommodation-filter', [
                    'hint' => 'تغییرات پس از «اعمال» روی سیاست کنسلی اعمال می‌شود',
                ])
            @endif
        @else
            <a wire:navigate href="{{ $backRoute }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-right me-1"></i>بازگشت
            </a>
            @if($panel === 'admin')
            <button type="button" wire:click="restoreDefaultCancellationPolicy" data-swal-confirm="سیاست کنسلی این اقامتگاه از تنظیمات سراسری بازگردانی شود؟" class="btn btn-sm btn-outline-warning">
                <i class="bi bi-arrow-counterclockwise me-1"></i>بازگردانی از تنظیمات سراسری
            </button>
            @endif
        @endisset
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <button type="button" wire:click="$set('tab', 'tiers')" class="nav-link {{ $tab === 'tiers' ? 'active' : '' }}">
            <i class="bi bi-percent me-1"></i>بازه‌های بازگشت وجه
        </button>
    </li>
    @if(($panel ?? 'admin') === 'admin')
    <li class="nav-item">
        <button type="button" wire:click="$set('tab', 'reasons')" class="nav-link {{ $tab === 'reasons' ? 'active' : '' }}">
            <i class="bi bi-list-check me-1"></i>دلایل کنسلی
        </button>
    </li>
    @endif
</ul>

@if($tab === 'tiers')
<form wire:submit="saveTiers">
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold d-flex align-items-center justify-content-between">
            <span>بازه‌های روز باقی‌مانده تا ورود و درصد بازگشت وجه متناظر</span>
            @if($canEditCancellationPolicy)
            <button type="button" wire:click="addTier" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>افزودن بازه</button>
            @endif
        </div>
        <div class="card-body p-0">
            <div class="alert alert-info small m-3 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                عدد روزها نسبت به تاریخ <strong>ورود (check-in)</strong> رزرو محاسبه می‌شود. مقادیر مثبت یعنی چند روز پیش از ورود، صفر یعنی همان روز ورود، و مقادیر منفی یعنی بعد از ورود (در حین اقامت).
                در حین اقامت، مبلغ استرداد فقط روی <strong>شب‌های باقی‌مانده</strong> اعمال می‌شود. برای «بدون محدودیت» فیلد را خالی بگذارید.
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:220px;">عنوان بازه (اختیاری)</th>
                            <th style="width:150px;">از (روز)</th>
                            <th style="width:150px;">تا (روز)</th>
                            <th style="width:160px;">درصد بازگشت وجه</th>
                            <th style="width:60px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tiers as $index => $tier)
                        <tr wire:key="tier-{{ $tier['id'] ?? $tier['key'] ?? 'new-'.$index }}">
                            <td>
                                <input type="text" wire:model="tiers.{{ $index }}.label" class="form-control form-control-sm" placeholder="مثال: ۳ تا ۵ روز قبل از ورود">
                                @isset($tierAccommodationsByKey)
                                    <x-veteran-policy.accommodation-badges :accommodations="$tierAccommodationsByKey[$tier['key'] ?? ''] ?? []" />
                                @endisset
                            </td>
                            <td><input type="number" wire:model="tiers.{{ $index }}.min_days_before_checkin" class="form-control form-control-sm" placeholder="بدون محدودیت"></td>
                            <td><input type="number" wire:model="tiers.{{ $index }}.max_days_before_checkin" class="form-control form-control-sm" placeholder="بدون محدودیت"></td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" min="0" max="100" wire:model="tiers.{{ $index }}.refund_percentage" class="form-control form-control-sm">
                                    <span class="input-group-text">٪</span>
                                </div>
                                @error('tiers.'.$index.'.refund_percentage') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            </td>
                            <td>
                                @if($canEditCancellationPolicy)
                                <button type="button" wire:click="removeTier({{ $index }})" data-swal-confirm="این بازه حذف شود؟" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i></button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                        @if(empty($tiers))
                        <tr><td colspan="5" class="text-center text-muted py-4">هنوز بازه‌ای تعریف نشده است.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
        @if($canEditCancellationPolicy)
        <div class="card-footer bg-white d-flex justify-content-end">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>ذخیره بازه‌ها</button>
        </div>
        @endif
    </div>
</form>
@endif

@if($tab === 'reasons' && ($panel ?? 'admin') === 'admin')
<form wire:submit="saveReasons">
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold d-flex align-items-center justify-content-between">
            <span>دلایل قابل انتخاب هنگام ثبت درخواست کنسلی</span>
            <button type="button" wire:click="addReason" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>افزودن دلیل</button>
        </div>
        <div class="card-body p-0">
            <div class="alert alert-info small m-3 mb-0">
                <i class="bi bi-info-circle me-1"></i>
                برای گزینه‌هایی که تیک «دلخواه» دارند، هنگام ثبت درخواست از کاربر خواسته می‌شود متن دلیل را خودش تایپ کند (مثلاً گزینه «سایر»).
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:260px;">عنوان دلیل</th>
                            <th style="width:110px;">دلخواه (متن آزاد)</th>
                            <th style="width:90px;">فعال</th>
                            <th style="width:60px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reasons as $index => $reason)
                        <tr wire:key="reason-{{ $reason['id'] ?? $reason['key'] ?? 'new-'.$index }}">
                            <td>
                                <input type="text" wire:model="reasons.{{ $index }}.label" class="form-control form-control-sm">
                                @error('reasons.'.$index.'.label') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                                @isset($reasonAccommodationsByKey)
                                    <x-veteran-policy.accommodation-badges :accommodations="$reasonAccommodationsByKey[$reason['key'] ?? ''] ?? []" />
                                @endisset
                            </td>
                            <td class="text-center"><input type="checkbox" wire:model="reasons.{{ $index }}.is_custom" class="form-check-input"></td>
                            <td class="text-center"><input type="checkbox" wire:model="reasons.{{ $index }}.is_active" class="form-check-input"></td>
                            <td>
                                <button type="button" wire:click="removeReason({{ $index }})" data-swal-confirm="این دلیل حذف شود؟" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i></button>
                            </td>
                        </tr>
                        @endforeach
                        @if(empty($reasons))
                        <tr><td colspan="4" class="text-center text-muted py-4">هنوز دلیلی تعریف نشده است.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end">
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>ذخیره دلایل</button>
        </div>
    </div>
</form>
@endif

</div>

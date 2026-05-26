
@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
<style>
.datepicker-plot-area { font-family: 'Vazirmatn', sans-serif !important; }
</style>
@endpush

<div>

@php
    function sortUrl(string $col, string $currentSort, string $currentDir): string {
        $dir = ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc';
        return request()->fullUrlWithQuery(['sort' => $col, 'dir' => $dir, 'page' => null]);
    }
    function sortIcon(string $col, string $currentSort, string $currentDir): string {
        if ($currentSort !== $col) return '<i class="bi bi-arrow-down-up text-muted opacity-50 ms-1" style="font-size:.7rem"></i>';
        return $currentDir === 'asc'
            ? '<i class="bi bi-sort-up-alt text-primary ms-1" style="font-size:.8rem"></i>'
            : '<i class="bi bi-sort-down text-primary ms-1" style="font-size:.8rem"></i>';
    }
@endphp

<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-calendar-check me-2"></i>رزروها ({{ $bookings->total() }})</h5>
    <a href="{{ route('admin.bookings.export', request()->query()) }}" class="btn btn-success btn-sm">
        <i class="bi bi-file-earmark-excel me-1"></i>خروجی اکسل
    </a>
</div>

{{-- ===== فیلترها ===== --}}
@php $hasFilter = request()->hasAny(['search','status','accommodation_id','city_id','check_in_from','check_in_to','check_out_from','check_out_to','nights_min','nights_max','price_min','price_max','guests_min','has_discount']); @endphp
<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex align-items-center justify-content-between" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#filterBody">
        <span class="fw-semibold small"><i class="bi bi-funnel me-1"></i>فیلترها</span>
        @if($hasFilter)
            <span class="badge bg-primary">فعال</span>
        @else
            <i class="bi bi-chevron-down text-muted" style="font-size:.8rem"></i>
        @endif
    </div>
    <div class="collapse {{ $hasFilter ? 'show' : 'show' }}" id="filterBody">
        <div class="card-body pb-2 pt-3">
            <form method="GET" id="filterForm">
                <div class="row g-2">
                    {{-- جستجوی متنی --}}
                    <div class="col-12 col-md-4">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">جستجو</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="کد رزرو / نام / موبایل / اقامتگاه" value="{{ request('search') }}">
                    </div>

                    {{-- وضعیت --}}
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">وضعیت</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">همه</option>
                            <option value="pending"   {{ request('status')=='pending'   ?'selected':'' }}>در انتظار</option>
                            <option value="confirmed" {{ request('status')=='confirmed' ?'selected':'' }}>تأیید شده</option>
                            <option value="cancelled" {{ request('status')=='cancelled' ?'selected':'' }}>لغو شده</option>
                        </select>
                    </div>

                    {{-- اقامتگاه --}}
                    <div class="col-6 col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">اقامتگاه</label>
                        <select name="accommodation_id" class="form-select form-select-sm">
                            <option value="">همه اقامتگاه‌ها</option>
                            @foreach($accommodations as $acc)
                                <option value="{{ $acc->id }}" {{ request('accommodation_id')==$acc->id?'selected':'' }}>{{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- شهر --}}
                    <div class="col-6 col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">شهر</label>
                        <select name="city_id" class="form-select form-select-sm">
                            <option value="">همه شهرها</option>
                            @foreach($cities as $city)
                                <option value="{{ $city->id }}" {{ request('city_id')==$city->id?'selected':'' }}>{{ $city->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- تاریخ ورود --}}
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">ورود از</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="check_in_from" class="form-control form-control-sm jalali-picker" autocomplete="off" placeholder="۱۴۰۳/۰۲/۰۱" value="{{ request('check_in_from') }}">
                            <button type="button" class="btn btn-outline-secondary btn-clear-date" tabindex="-1" title="پاک کردن"><i class="bi bi-x"></i></button>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">ورود تا</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="check_in_to" class="form-control form-control-sm jalali-picker" autocomplete="off" placeholder="۱۴۰۳/۰۲/۳۱" value="{{ request('check_in_to') }}">
                            <button type="button" class="btn btn-outline-secondary btn-clear-date" tabindex="-1" title="پاک کردن"><i class="bi bi-x"></i></button>
                        </div>
                    </div>

                    {{-- تاریخ خروج --}}
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">خروج از</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="check_out_from" class="form-control form-control-sm jalali-picker" autocomplete="off" placeholder="۱۴۰۳/۰۳/۰۱" value="{{ request('check_out_from') }}">
                            <button type="button" class="btn btn-outline-secondary btn-clear-date" tabindex="-1" title="پاک کردن"><i class="bi bi-x"></i></button>
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">خروج تا</label>
                        <div class="input-group input-group-sm">
                            <input type="text" name="check_out_to" class="form-control form-control-sm jalali-picker" autocomplete="off" placeholder="۱۴۰۳/۰۳/۳۱" value="{{ request('check_out_to') }}">
                            <button type="button" class="btn btn-outline-secondary btn-clear-date" tabindex="-1" title="پاک کردن"><i class="bi bi-x"></i></button>
                        </div>
                    </div>

                    {{-- تعداد شب --}}
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">شب‌ها (از)</label>
                        <input type="number" name="nights_min" class="form-control form-control-sm" min="1" placeholder="مثلاً ۱" value="{{ request('nights_min') }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">شب‌ها (تا)</label>
                        <input type="number" name="nights_max" class="form-control form-control-sm" min="1" placeholder="مثلاً ۷" value="{{ request('nights_max') }}">
                    </div>

                    {{-- محدوده قیمت --}}
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">مبلغ از (تومان)</label>
                        <input type="number" name="price_min" class="form-control form-control-sm" min="0" placeholder="مثلاً ۵۰۰۰۰۰" value="{{ request('price_min') }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">مبلغ تا (تومان)</label>
                        <input type="number" name="price_max" class="form-control form-control-sm" min="0" placeholder="مثلاً ۵۰۰۰۰۰۰" value="{{ request('price_max') }}">
                    </div>

                    {{-- مهمانان --}}
                    <div class="col-6 col-md-2">
                        <label class="form-label form-label-sm mb-1 text-muted" style="font-size:.75rem">حداقل مهمان</label>
                        <input type="number" name="guests_min" class="form-control form-control-sm" min="1" placeholder="مثلاً ۲" value="{{ request('guests_min') }}">
                    </div>

                    {{-- فقط دارای تخفیف --}}
                    <div class="col-6 col-md-2 d-flex align-items-end pb-1">
                        <div class="form-check">
                            <input type="checkbox" name="has_discount" value="1" id="chkDiscount" class="form-check-input" {{ request('has_discount') ? 'checked' : '' }}>
                            <label for="chkDiscount" class="form-check-label small">فقط تخفیف‌دار</label>
                        </div>
                    </div>

                    {{-- دکمه‌ها --}}
                    <div class="col-12 d-flex gap-2 justify-content-end mt-1">
                        <a wire:navigate href="{{ route('admin.bookings.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i>پاک کردن
                        </a>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-funnel me-1"></i>اعمال فیلتر
                        </button>
                    </div>
                </div>
                {{-- حفظ مرتب‌سازی هنگام فیلتر --}}
                @if(request('sort'))
                    <input type="hidden" name="sort" value="{{ request('sort') }}">
                    <input type="hidden" name="dir"  value="{{ request('dir') }}">
                @endif
            </form>
        </div>
    </div>
</div>

{{-- ===== خلاصه جمع مبالغ ===== --}}
<div class="d-flex flex-wrap gap-3 align-items-center mb-3">
    <div class="card border-0 shadow-sm px-3 py-2 d-flex flex-row align-items-center gap-2">
        <i class="bi bi-receipt fs-5 text-primary"></i>
        <div>
            <div class="text-muted" style="font-size:.72rem">تعداد رزروهای فیلتر شده</div>
            <div class="fw-bold">{{ number_format($countFiltered) }} رزرو</div>
        </div>
    </div>
    <div class="card border-0 shadow-sm px-3 py-2 d-flex flex-row align-items-center gap-2">
        <i class="bi bi-cash-coin fs-5 text-success"></i>
        <div>
            <div class="text-muted" style="font-size:.72rem">جمع کل مبالغ (فیلتر شده)</div>
            <div class="fw-bold text-success">{{ number_format($totalFiltered) }} تومان</div>
        </div>
    </div>
</div>

{{-- ===== جدول ===== --}}
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>
                        <a wire:navigate href="{{ sortUrl('id', $sort, $dir) }}" class="text-dark text-decoration-none">
                            کد {!! sortIcon('id', $sort, $dir) !!}
                        </a>
                    </th>
                    <th>کاربر</th>
                    <th>اقامتگاه</th>
                    <th>اتاق</th>
                    <th>
                        <a wire:navigate href="{{ sortUrl('check_in', $sort, $dir) }}" class="text-dark text-decoration-none">
                            ورود {!! sortIcon('check_in', $sort, $dir) !!}
                        </a>
                    </th>
                    <th>
                        <a wire:navigate href="{{ sortUrl('check_out', $sort, $dir) }}" class="text-dark text-decoration-none">
                            خروج {!! sortIcon('check_out', $sort, $dir) !!}
                        </a>
                    </th>
                    <th>
                        <a wire:navigate href="{{ sortUrl('nights', $sort, $dir) }}" class="text-dark text-decoration-none">
                            شب {!! sortIcon('nights', $sort, $dir) !!}
                        </a>
                    </th>
                    <th>
                        <a wire:navigate href="{{ sortUrl('guests', $sort, $dir) }}" class="text-dark text-decoration-none">
                            مهمان {!! sortIcon('guests', $sort, $dir) !!}
                        </a>
                    </th>
                    <th>
                        <a wire:navigate href="{{ sortUrl('total_price', $sort, $dir) }}" class="text-dark text-decoration-none">
                            مبلغ {!! sortIcon('total_price', $sort, $dir) !!}
                        </a>
                    </th>
                    <th>وضعیت</th>
                    <th>
                        <a wire:navigate href="{{ sortUrl('created_at', $sort, $dir) }}" class="text-dark text-decoration-none">
                            ثبت {!! sortIcon('created_at', $sort, $dir) !!}
                        </a>
                    </th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bookings as $b)
                <tr>
                    <td>
                        <a wire:navigate href="{{ route('admin.bookings.show', $b) }}" class="text-decoration-none">
                            <code class="small">{{ $b->tracking_code }}</code>
                        </a>
                    </td>
                    <td class="small">
                        <a wire:navigate href="{{ route('admin.users.show', $b->user) }}" class="text-decoration-none text-dark">
                            {{ $b->user->name ?? $b->user->mobile }}
                        </a>
                        @if($b->user->discount_percentage > 0)
                        <br><span class="badge bg-warning text-dark" style="font-size:.65rem">{{ $b->user->discount_percentage }}% تخفیف</span>
                        @endif
                    </td>
                    <td class="small">
                        <a wire:navigate href="{{ route('admin.accommodations.edit', $b->accommodation) }}" class="text-decoration-none text-dark">
                            {{ Str::limit($b->accommodation->name ?? '', 22) }}
                        </a>
                    </td>
                    <td class="small">{{ $b->roomType?->name ?? '—' }}</td>
                    <td class="small">@jalali($b->check_in)</td>
                    <td class="small">@jalali($b->check_out)</td>
                    <td>{{ $b->nights }}</td>
                    <td>{{ $b->guests }}</td>
                    <td class="small">
                        {{ number_format($b->total_price) }} ت
                        @if($b->discount_percentage > 0)
                            <br><span class="badge bg-warning text-dark" style="font-size:.6rem">{{ $b->discount_percentage }}% تخفیف</span>
                        @endif
                    </td>
                    <td><span class="badge bg-{{ $b->statusColor() }}">{{ $b->statusLabel() }}</span></td>
                    <td class="small text-muted">@jalali($b->created_at)</td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <a wire:navigate href="{{ route('admin.bookings.show', $b) }}" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem;" title="جزئیات"><i class="bi bi-eye"></i></a>
                            @if($b->status === 'pending')
                            <button wire:click="updateStatus({{ $b->id }}, 'confirmed')" class="btn btn-xs btn-outline-success" style="padding:.2rem .5rem;font-size:.75rem;" title="تأیید"><i class="bi bi-check-lg"></i></button>
                            <button wire:click="updateStatus({{ $b->id }}, 'cancelled')" data-swal-confirm="لغو شود؟" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem;" title="لغو"><i class="bi bi-x-lg"></i></button>
                            @elseif($b->status === 'confirmed')
                            <button wire:click="updateStatus({{ $b->id }}, 'cancelled')" data-swal-confirm="لغو شود؟" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem;" title="لغو"><i class="bi bi-x-lg"></i></button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="12" class="text-center text-muted py-4">رزروی یافت نشد</td></tr>
                @endforelse
            </tbody>
            @if($bookings->count() > 0)
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="8" class="text-end small text-muted">جمع این صفحه:</td>
                    <td class="text-success small">{{ number_format($bookings->sum('total_price')) }} ت</td>
                    <td colspan="4"></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
    <div class="card-footer bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="small text-muted">
            جمع کل فیلتر: <strong class="text-success">{{ number_format($totalFiltered) }} تومان</strong>
            &nbsp;|&nbsp; {{ number_format($countFiltered) }} رزرو
        </div>
        {{ $bookings->links() }}
    </div>
</div>
</div>

@push('scripts')
<script>
$(function(){
    $('.jalali-picker').pDatepicker({
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
    });

    // دکمه پاک کردن هر فیلد تاریخ
    $(document).on('click', '.btn-clear-date', function () {
        $(this).closest('.input-group').find('.jalali-picker').val('');
    });
});
</script>
@endpush


<div>

<div class="d-flex align-items-center gap-2 mb-3">
    <a wire:navigate href="{{ route('host.programs.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right"></i></a>
</div>

{{-- انتخاب سال --}}
<div class="card shadow-sm mb-4">
    <div class="card-body py-2">
        <div class="d-flex align-items-center gap-3">
            <label class="fw-semibold mb-0">سال شمسی:</label>
            <select wire:model.live="year" class="form-select form-select-sm" style="width:120px">
                @foreach($jalaliYears as $y)
                <option value="{{ $y }}">{{ $y }}</option>
                @endforeach
            </select>
            <span class="text-muted small">گزارش از ابتدای سال {{ $year }} تا پایان آن</span>
        </div>
    </div>
</div>

{{-- کارت‌های خلاصه --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm stat-card border-0" style="background:linear-gradient(135deg,#dc3545,#c82333);">
            <div class="card-body text-white text-center py-4">
                <div class="fs-1 fw-bold">{{ number_format($totalPrograms) }}</div>
                <div class="opacity-75">برنامه / اردو</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm stat-card border-0" style="background:linear-gradient(135deg,#198754,#146c43);">
            <div class="card-body text-white text-center py-4">
                <div class="fs-1 fw-bold">{{ number_format($totalGuests) }}</div>
                <div class="opacity-75">نفر بهره‌مند از خدمات</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm stat-card border-0" style="background:linear-gradient(135deg,#0d6efd,#0a58ca);">
            <div class="card-body text-white text-center py-4">
                <div class="fs-2 fw-bold">{{ number_format($totalDiscount) }} ﷼</div>
                <div class="opacity-75">جمع کل خدمات حمایتی</div>
            </div>
        </div>
    </div>
</div>

{{-- گروه‌بندی بر اساس نوع --}}
@if($byType->isNotEmpty())
<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">
        <i class="bi bi-pie-chart me-1"></i> تفکیک بر اساس نوع خدمت حمایتی
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr><th>نوع خدمت</th><th class="text-center">تعداد برنامه</th><th class="text-center">تعداد نفرات</th><th class="text-center">جمع تخفیف (ریال)</th></tr>
            </thead>
            <tbody>
                @foreach($byType as $type => $data)
                <tr>
                    <td class="fw-semibold">{{ $type }}</td>
                    <td class="text-center">{{ $data['count'] }}</td>
                    <td class="text-center">{{ number_format($data['guests']) }}</td>
                    <td class="text-center text-danger fw-semibold">{{ number_format($data['discount']) }}</td>
                </tr>
                @endforeach
                <tr class="table-light fw-bold">
                    <td>جمع کل</td>
                    <td class="text-center">{{ $totalPrograms }}</td>
                    <td class="text-center">{{ number_format($totalGuests) }}</td>
                    <td class="text-center text-danger">{{ number_format($totalDiscount) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- جدول جزئیات --}}
<div class="card shadow-sm">
    <div class="card-header fw-semibold">
        <i class="bi bi-list-ul me-1"></i> جزئیات برنامه‌های حمایتی - سال {{ $year }}
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="col-index">#</th><th>عنوان</th><th>نوع خدمت</th>
                    <th>تاریخ</th><th class="text-center">نفرات</th>
                    <th>کارفرما</th><th>مبلغ کل</th><th class="text-danger">تخفیف حمایتی</th>
                    <th>وضعیت</th>
                </tr>
            </thead>
            <tbody>
                @forelse($programs as $p)
                <tr>
                    <td class="small text-muted">{{ $loop->iteration }}</td>
                    <td class="small fw-semibold">
                        <a wire:navigate href="{{ route('host.programs.show', $p) }}">{{ Str::limit($p->title, 30) }}</a>
                    </td>
                    <td class="small">{{ $p->supportive_service_type ?: '—' }}</td>
                    <td class="small">@jalali($p->start_date) — @jalali($p->end_date)</td>
                    <td class="text-center small">{{ number_format($p->guest_count) }}</td>
                    <td class="small">{{ $p->employer ?: '—' }}</td>
                    <td class="small">{{ number_format($p->total_amount) }}</td>
                    <td class="small text-danger fw-semibold">{{ number_format($p->discount_amount) }}</td>
                    <td><span class="badge bg-{{ $p->statusColor() }}">{{ $p->statusLabel() }}</span></td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">هیچ برنامه حمایتی در این سال ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>
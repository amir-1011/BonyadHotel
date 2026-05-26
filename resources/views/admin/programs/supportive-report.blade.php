<div>

<div class="d-flex align-items-center gap-2 mb-3">
    <a wire:navigate href="{{ route('admin.programs.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right"></i></a>
    <h5 class="fw-bold mb-0"><i class="bi bi-heart-fill me-2 text-danger"></i>گزارش خدمات حمایتی بنیاد شهید</h5>
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

<div class="row g-3 mb-4">
    {{-- تفکیک بر اساس نوع خدمت --}}
    @if($byType->isNotEmpty())
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-pie-chart me-1"></i> تفکیک بر اساس نوع خدمت
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>نوع خدمت</th><th class="text-center">برنامه</th><th class="text-center">نفرات</th><th>تخفیف (ریال)</th></tr>
                    </thead>
                    <tbody>
                        @foreach($byType as $type => $data)
                        <tr>
                            <td class="fw-semibold small">{{ $type }}</td>
                            <td class="text-center small">{{ $data['count'] }}</td>
                            <td class="text-center small">{{ number_format($data['guests']) }}</td>
                            <td class="small text-danger fw-semibold">{{ number_format($data['discount']) }}</td>
                        </tr>
                        @endforeach
                        <tr class="table-light fw-bold">
                            <td>جمع</td>
                            <td class="text-center">{{ $totalPrograms }}</td>
                            <td class="text-center">{{ number_format($totalGuests) }}</td>
                            <td class="text-danger">{{ number_format($totalDiscount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    {{-- تفکیک بر اساس اقامتگاه --}}
    @if($byAccommodation->isNotEmpty())
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-building me-1"></i> تفکیک بر اساس اقامتگاه
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>اقامتگاه</th><th class="text-center">برنامه</th><th class="text-center">نفرات</th><th>تخفیف (ریال)</th></tr>
                    </thead>
                    <tbody>
                        @foreach($byAccommodation as $acc => $data)
                        <tr>
                            <td class="fw-semibold small">{{ $acc }}</td>
                            <td class="text-center small">{{ $data['count'] }}</td>
                            <td class="text-center small">{{ number_format($data['guests']) }}</td>
                            <td class="small text-danger fw-semibold">{{ number_format($data['discount']) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>

{{-- جدول جزئیات --}}
<div class="card shadow-sm">
    <div class="card-header fw-semibold">
        <i class="bi bi-list-ul me-1"></i> جزئیات برنامه‌های حمایتی - سال {{ $year }}
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th><th>عنوان</th><th>اقامتگاه</th><th>نوع خدمت</th>
                    <th>تاریخ شروع</th><th class="text-center">نفرات</th>
                    <th>کارفرما</th><th>مبلغ کل</th><th class="text-danger">تخفیف حمایتی</th>
                    <th>وضعیت</th>
                </tr>
            </thead>
            <tbody>
                @forelse($programs as $p)
                <tr>
                    <td class="small text-muted">{{ $loop->iteration }}</td>
                    <td class="small fw-semibold">
                        <a wire:navigate href="{{ route('admin.programs.show', $p) }}">{{ Str::limit($p->title, 28) }}</a>
                    </td>
                    <td class="small">{{ Str::limit($p->accommodation->name, 20) }}</td>
                    <td class="small">{{ $p->supportive_service_type ?: '—' }}</td>
                    <td class="small">@jalali($p->start_date)</td>
                    <td class="text-center small">{{ number_format($p->guest_count) }}</td>
                    <td class="small">{{ $p->employer ?: '—' }}</td>
                    <td class="small">{{ number_format($p->total_amount) }}</td>
                    <td class="small text-danger fw-semibold">{{ number_format($p->discount_amount) }}</td>
                    <td><span class="badge bg-{{ $p->statusColor() }}">{{ $p->statusLabel() }}</span></td>
                </tr>
                @empty
                <tr><td colspan="10" class="text-center text-muted py-4">هیچ برنامه حمایتی در این سال ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>
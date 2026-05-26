<div>

<div class="d-flex align-items-center gap-2 mb-3">
    <a wire:navigate href="{{ route('host.programs.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right"></i></a>
    <h5 class="fw-bold mb-0"><i class="bi bi-flag-fill me-2 text-success"></i>{{ $program->title }}</h5>
    <span class="badge bg-{{ $program->statusColor() }}">{{ $program->statusLabel() }}</span>
    @if($program->is_supportive_service)
    <span class="badge bg-danger"><i class="bi bi-heart-fill me-1"></i>خدمات حمایتی</span>
    @endif
</div>

<div class="row g-3">
<div class="col-lg-8">

    {{-- اطلاعات اصلی --}}
    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold bg-success text-white py-2">
            <i class="bi bi-info-circle me-1"></i> اطلاعات برنامه
        </div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-6"><span class="text-muted small">اقامتگاه:</span><br><strong>{{ $program->accommodation->name }}</strong></div>
                <div class="col-md-6"><span class="text-muted small">نوع:</span><br><span class="badge bg-info text-dark fs-6">{{ $program->programTypeLabel() }}</span></div>
                <div class="col-md-6"><span class="text-muted small">تاریخ شروع:</span><br><strong>@jalali($program->start_date)</strong></div>
                <div class="col-md-6"><span class="text-muted small">تاریخ پایان:</span><br><strong>@jalali($program->end_date)</strong></div>
                <div class="col-md-4"><span class="text-muted small">تعداد نفرات:</span><br><strong>{{ number_format($program->guest_count) }} نفر</strong></div>
                <div class="col-md-4"><span class="text-muted small">اتاق‌های رزرو شده:</span><br><strong>{{ $program->rooms_allocated }} اتاق</strong></div>
                @if($program->employer)
                <div class="col-md-6"><span class="text-muted small">کارفرما:</span><br><strong>{{ $program->employer }}</strong></div>
                @endif
                @if($program->contractor)
                <div class="col-md-6"><span class="text-muted small">پیمانکار:</span><br><strong>{{ $program->contractor }}</strong></div>
                @endif
                @if($program->description)
                <div class="col-12"><span class="text-muted small">توضیحات:</span><br>{{ $program->description }}</div>
                @endif
                @if($program->notes)
                <div class="col-12"><span class="text-muted small">یادداشت:</span><br><em>{{ $program->notes }}</em></div>
                @endif
            </div>
        </div>
    </div>

    {{-- اطلاعات مالی --}}
    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold bg-warning bg-opacity-75 py-2">
            <i class="bi bi-cash-stack me-1"></i> اطلاعات مالی
        </div>
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2">
                        <div class="text-muted small">مبلغ کل</div>
                        <div class="fw-bold text-success fs-5">{{ number_format($program->total_amount) }}</div>
                        <div class="text-muted small">ریال</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2">
                        <div class="text-muted small">بیعانه</div>
                        <div class="fw-bold text-primary fs-5">{{ number_format($program->deposit_amount) }}</div>
                        <div class="text-muted small">ریال</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2">
                        <div class="text-muted small">تخفیف</div>
                        <div class="fw-bold text-danger fs-5">{{ number_format($program->discount_amount) }}</div>
                        <div class="text-muted small">ریال {{ $program->discount_percentage > 0 ? '('.$program->discount_percentage.'%)' : '' }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2 bg-light">
                        <div class="text-muted small">مانده</div>
                        <div class="fw-bold fs-5">{{ number_format($program->remainingAmount()) }}</div>
                        <div class="text-muted small">ریال</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- اتاق‌های اختصاصی --}}
    @if($program->roomTypes->isNotEmpty())
    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold py-2"><i class="bi bi-door-open me-1"></i> اتاق‌های اختصاص داده شده</div>
        <div class="card-body">
            @foreach($program->roomTypes as $rt)
            <div class="d-flex justify-content-between align-items-center p-2 rounded mb-1" style="background:#f0fdf4;">
                <span>{{ $rt->name }}</span>
                <span class="badge bg-success">{{ $rt->pivot->rooms_count }} اتاق</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>

<div class="col-lg-4">
    {{-- خدمات حمایتی --}}
    @if($program->is_supportive_service)
    <div class="card shadow-sm mb-3 border-danger">
        <div class="card-header fw-semibold bg-danger text-white py-2">
            <i class="bi bi-heart-fill me-1"></i> خدمات حمایتی
        </div>
        <div class="card-body">
            <p class="mb-1"><strong>نوع:</strong> {{ $program->supportive_service_type ?: '—' }}</p>
            <p class="mb-1"><strong>مبلغ تخفیف:</strong> {{ number_format($program->discount_amount) }} ریال</p>
            <p class="mb-0"><strong>تعداد بهره‌مندان:</strong> {{ number_format($program->guest_count) }} نفر</p>
        </div>
    </div>
    @endif

    {{-- عملیات --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body d-grid gap-2">
            <a wire:navigate href="{{ route('host.programs.edit', $program) }}" class="btn btn-warning">
                <i class="bi bi-pencil me-1"></i> ویرایش برنامه
            </a>
            <button wire:click="destroy()" data-swal-confirm="برنامه حذف شود؟" class="btn btn-outline-danger w-100"><i class="bi bi-trash me-1"></i> حذف</button>
        </div>
    </div>

    {{-- اطلاعات کلی --}}
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="text-muted small mb-1">تاریخ ثبت:</div>
            <div>@jalali($program->created_at)</div>
            <div class="text-muted small mb-1 mt-2">آخرین ویرایش:</div>
            <div>@jalali($program->updated_at)</div>
        </div>
    </div>
</div>
</div>

</div>
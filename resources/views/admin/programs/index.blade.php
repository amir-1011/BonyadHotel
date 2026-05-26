<div>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-flag me-2"></i>برنامه‌ها و اردوها ({{ $programs->total() }})</h5>
    <a wire:navigate href="{{ route('admin.programs.supportive-report') }}" class="btn btn-sm btn-outline-danger">
        <i class="bi bi-heart-fill me-1"></i> گزارش خدمات حمایتی
    </a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2">
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="active"    {{ request('status')=='active'?'selected':'' }}>فعال</option>
                    <option value="completed" {{ request('status')=='completed'?'selected':'' }}>پایان‌یافته</option>
                    <option value="cancelled" {{ request('status')=='cancelled'?'selected':'' }}>لغو‌شده</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="is_supportive_service" class="form-select form-select-sm">
                    <option value="">همه انواع</option>
                    <option value="1" {{ request('is_supportive_service')==='1'?'selected':'' }}>فقط حمایتی</option>
                    <option value="0" {{ request('is_supportive_service')==='0'?'selected':'' }}>غیر حمایتی</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select name="accommodation_id" class="form-select form-select-sm">
                    <option value="">همه اقامتگاه‌ها</option>
                    @foreach($accommodations as $a)
                    <option value="{{ $a->id }}" {{ request('accommodation_id')==$a->id?'selected':'' }}>{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-3 col-md-2"><button class="btn btn-sm btn-primary w-100">فیلتر</button></div>
            <div class="col-3 col-md-1"><a wire:navigate href="{{ route('admin.programs.index') }}" class="btn btn-sm btn-outline-secondary w-100">پاک</a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>عنوان</th><th>اقامتگاه</th><th>نوع</th>
                    <th>شروع</th><th>پایان</th>
                    <th class="text-center">نفرات</th><th>مبلغ کل</th>
                    <th>تخفیف</th><th>حمایتی</th><th>وضعیت</th><th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($programs as $p)
                <tr>
                    <td class="small fw-semibold">{{ Str::limit($p->title, 26) }}</td>
                    <td class="small">{{ Str::limit($p->accommodation->name, 22) }}</td>
                    <td><span class="badge bg-info text-dark">{{ $p->programTypeLabel() }}</span></td>
                    <td class="small">@jalali($p->start_date)</td>
                    <td class="small">@jalali($p->end_date)</td>
                    <td class="text-center small">{{ number_format($p->guest_count) }}</td>
                    <td class="small">{{ number_format($p->total_amount) }} ﷼</td>
                    <td class="small {{ $p->discount_amount > 0 ? 'text-danger' : 'text-muted' }}">
                        {{ $p->discount_amount > 0 ? number_format($p->discount_amount).' ﷼' : '—' }}
                    </td>
                    <td class="text-center">
                        @if($p->is_supportive_service)
                        <span class="badge bg-danger" title="{{ $p->supportive_service_type }}"><i class="bi bi-heart-fill"></i></span>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td><span class="badge bg-{{ $p->statusColor() }}">{{ $p->statusLabel() }}</span></td>
                    <td>
                        <a wire:navigate href="{{ route('admin.programs.show', $p) }}" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem;"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="11" class="text-center text-muted py-4">هیچ برنامه‌ای ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($programs->hasPages())
    <div class="card-footer d-flex justify-content-center">{{ $programs->links() }}</div>
    @endif
</div>

</div>
<div>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-flag me-2"></i>برنامه‌ها و اردوها ({{ $programs->total() }})</h5>
    <div class="d-flex gap-2">
        <a wire:navigate href="{{ route('host.programs.supportive-report') }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-heart-fill me-1 text-danger"></i> گزارش خدمات حمایتی
        </a>
        <a wire:navigate href="{{ route('host.programs.create') }}" class="btn btn-sm btn-success">
            <i class="bi bi-plus-circle me-1"></i> برنامه جدید
        </a>
    </div>
</div>

{{-- فیلتر --}}
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2">
            <div class="col-6 col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="active"    {{ request('status')=='active'?'selected':'' }}>فعال</option>
                    <option value="completed" {{ request('status')=='completed'?'selected':'' }}>پایان‌یافته</option>
                    <option value="cancelled" {{ request('status')=='cancelled'?'selected':'' }}>لغو‌شده</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select name="is_supportive_service" class="form-select form-select-sm">
                    <option value="">همه انواع</option>
                    <option value="1" {{ request('is_supportive_service')==='1'?'selected':'' }}>فقط خدمات حمایتی</option>
                    <option value="0" {{ request('is_supportive_service')==='0'?'selected':'' }}>غیر حمایتی</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <select name="accommodation_id" class="form-select form-select-sm">
                    <option value="">همه اقامتگاه‌ها</option>
                    @foreach($myAccommodations as $a)
                    <option value="{{ $a->id }}" {{ request('accommodation_id')==$a->id?'selected':'' }}>{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-3 col-md-2"><button class="btn btn-sm btn-primary w-100">فیلتر</button></div>
            <div class="col-3 col-md-1"><a wire:navigate href="{{ route('host.programs.index') }}" class="btn btn-sm btn-outline-secondary w-100">پاک</a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th><th>عنوان</th><th>اقامتگاه</th><th>نوع</th>
                    <th>تاریخ شروع</th><th>تاریخ پایان</th>
                    <th>نفرات</th><th>مبلغ کل</th><th>تخفیف</th>
                    <th>حمایتی</th><th>وضعیت</th><th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($programs as $p)
                <tr>
                    <td class="small text-muted">{{ $p->id }}</td>
                    <td class="small fw-semibold">{{ Str::limit($p->title, 28) }}</td>
                    <td class="small">{{ Str::limit($p->accommodation->name, 22) }}</td>
                    <td><span class="badge bg-info text-dark">{{ $p->programTypeLabel() }}</span></td>
                    <td class="small">@jalali($p->start_date)</td>
                    <td class="small">@jalali($p->end_date)</td>
                    <td class="small text-center">{{ number_format($p->guest_count) }}</td>
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
                        <div class="d-flex gap-1">
                            <a wire:navigate href="{{ route('host.programs.show', $p) }}" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem;"><i class="bi bi-eye"></i></a>
                            <a wire:navigate href="{{ route('host.programs.edit', $p) }}" class="btn btn-xs btn-outline-secondary" style="padding:.2rem .5rem;font-size:.75rem;"><i class="bi bi-pencil"></i></a>
                            <button wire:click="destroy({{ $p->id }})" data-swal-confirm="برنامه «{{ $p->title }}» حذف شود؟" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem;"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="12" class="text-center text-muted py-4">هیچ برنامه‌ای ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($programs->hasPages())
    <div class="card-footer d-flex justify-content-center">{{ $programs->links() }}</div>
    @endif
</div>

</div>
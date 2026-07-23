<div>

<div class="d-flex align-items-center justify-content-end mb-3 flex-wrap gap-2">
    <div class="d-flex gap-2">
        <a wire:navigate href="{{ route('admin.programs.supportive-report') }}" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-heart-fill me-1"></i>گزارش خدمات حمایتی
        </a>
        <a wire:navigate href="{{ route('admin.programs.create') }}" class="btn btn-sm btn-success">
            <i class="bi bi-plus-circle me-1"></i>برنامه جدید
        </a>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="row g-2">
            <div class="col-md-3">
                <input type="text" wire:model.live.debounce.400ms="search" class="form-control form-control-sm" placeholder="جستجو...">
            </div>
            <div class="col-md-2">
                <select wire:model.live="status" class="form-select form-select-sm">
                    <option value="">همه وضعیت‌ها</option>
                    @foreach(\App\Models\Program::statusOptions() as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select wire:model.live="programType" class="form-select form-select-sm">
                    <option value="">همه انواع</option>
                    @foreach(\App\Models\Program::typeOptions() as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select wire:model.live="paymentType" class="form-select form-select-sm">
                    <option value="">همه پرداخت‌ها</option>
                    @foreach(\App\Models\Program::paymentTypeOptions() as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select wire:model.live="accommodationId" class="form-select form-select-sm">
                    <option value="0">همه اقامتگاه‌ها</option>
                    @foreach($accommodations as $a)
                        <option value="{{ $a->id }}">{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>عنوان</th><th>اقامتگاه</th><th>نوع</th><th>تاریخ</th>
                    <th>نفرات</th><th>مبلغ کل</th><th>پرداخت</th><th>وضعیت</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($programs as $p)
                <tr wire:key="admin-prog-{{ $p->id }}">
                    <td class="small fw-semibold">
                        <a wire:navigate href="{{ route('admin.programs.show', $p) }}" class="text-decoration-none">{{ Str::limit($p->title, 26) }}</a>
                    </td>
                    <td class="small">{{ Str::limit($p->accommodation->name, 22) }}</td>
                    <td><span class="badge bg-info text-dark">{{ $p->programTypeLabel() }}</span></td>
                    <td class="small">
                        @if($p->booking)@jalali($p->booking->check_in) — @jalali($p->booking->check_out)@else—@endif
                    </td>
                    <td class="text-center small">{{ $p->guest_count }}</td>
                    <td class="small">{{ number_format($p->total_amount) }} تومان</td>
                    <td class="small">{{ $p->paymentTypeLabel() }}</td>
                    <td><span class="badge bg-{{ $p->statusColor() }}">{{ $p->statusLabel() }}</span></td>
                    <td>
                        <a wire:navigate href="{{ route('admin.programs.show', $p) }}" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem;"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">هیچ برنامه‌ای ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($programs->hasPages())
    <div class="card-footer d-flex justify-content-center">{{ $programs->links() }}</div>
    @endif
</div>

</div>

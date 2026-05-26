<div>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-star me-2"></i>نظرات ({{ $reviews->total() }})</h5>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <select name="visible" class="form-select form-select-sm">
                    <option value="">همه</option>
                    <option value="1" {{ request('visible')=='1'?'selected':'' }}>نمایش داده شده</option>
                    <option value="0" {{ request('visible')=='0'?'selected':'' }}>مخفی</option>
                </select>
            </div>
            <div class="col-4 col-md-2"><button class="btn btn-sm btn-primary w-100">فیلتر</button></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>کاربر</th><th>اقامتگاه</th><th>امتیاز</th><th>متن</th><th>تاریخ</th><th>نمایش</th><th>عملیات</th></tr>
            </thead>
            <tbody>
                @forelse($reviews as $r)
                <tr class="{{ $r->is_visible ? '' : 'table-secondary' }}">
                    <td>{{ $r->id }}</td>
                    <td class="small">
                        <a wire:navigate href="{{ route('admin.users.show', $r->user) }}" class="text-decoration-none text-dark">
                            {{ $r->user->name ?? $r->user->mobile }}
                        </a>
                    </td>
                    <td class="small">
                        <a href="{{ route('accommodations.show', $r->accommodation) }}" class="text-decoration-none text-dark" target="_blank">
                            {{ Str::limit($r->accommodation->name ?? '', 22) }}
                        </a>
                        <br>
                        <a wire:navigate href="{{ route('admin.accommodations.edit', $r->accommodation) }}" class="text-muted" style="font-size:.7rem">
                            <i class="bi bi-pencil me-1"></i>ویرایش اقامتگاه
                        </a>
                    </td>
                    <td>
                        <span class="text-warning">{{ str_repeat('★', $r->rating) }}</span><span class="text-muted">{{ str_repeat('☆', 5-$r->rating) }}</span>
                    </td>
                    <td class="small text-muted">{{ Str::limit($r->comment, 50) }}</td>
                    <td class="small text-muted">{{ $r->created_at->format('Y/m/d') }}</td>
                    <td>
                        <button wire:click="toggle({{ $r->id }})" class="badge border-0 bg-{{ $r->is_visible ? 'success' : 'secondary' }}" title="{{ $r->is_visible ? 'مخفی کردن' : 'نمایش دادن' }}">
                                {{ $r->is_visible ? 'نمایش' : 'مخفی' }}
                            </button>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            @if($r->booking_id)
                            <a wire:navigate href="{{ route('admin.bookings.show', $r->booking_id) }}" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem;" title="رزرو مربوطه"><i class="bi bi-calendar-check"></i></a>
                            @endif
                            <button wire:click="destroy({{ $r->id }})" data-swal-confirm="حذف شود؟" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem;" title="حذف"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">نظری یافت نشد</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $reviews->links() }}</div>
</div>

</div>
<div>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-calendar-check me-2"></i>رزروها ({{ $bookings->total() }})</h5>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="row g-2">
            <div class="col-6 col-md-3">
                <select wire:model.live="status" class="form-select form-select-sm">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="pending">در انتظار</option>
                    <option value="confirmed">تأیید شده</option>
                    <option value="cancelled">لغو شده</option>
                </select>
            </div>
            <div class="col-6 col-md-4">
                <select wire:model.live="accommodationId" class="form-select form-select-sm">
                    <option value="0">همه اقامتگاه‌ها</option>
                    @foreach($myAccommodations as $a)
                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-3 col-md-2">
                <input wire:model.live="search" type="text" class="form-control form-control-sm" placeholder="جستجو...">
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>کد</th><th>مهمان</th><th>اقامتگاه</th><th>اتاق</th><th>ورود</th><th>خروج</th><th>مبلغ</th><th>وضعیت</th><th>عملیات</th></tr>
            </thead>
            <tbody>
                @forelse($bookings as $b)
                <tr>
                    <td><code class="small">{{ $b->tracking_code }}</code></td>
                    <td class="small">{{ $b->user->name ?? $b->user->mobile }}</td>
                    <td class="small">{{ Str::limit($b->accommodation->name, 22) }}</td>
                    <td class="small">{{ $b->roomType?->name ?? '—' }}</td>
                    <td class="small">@jalali($b->check_in)</td>
                    <td class="small">@jalali($b->check_out)</td>
                    <td class="small">{{ number_format($b->total_price) }}</td>
                    <td><span class="badge bg-{{ $b->statusColor() }}">{{ $b->statusLabel() }}</span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a wire:navigate href="{{ route('host.bookings.show', $b) }}" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem;"><i class="bi bi-eye"></i></a>
                            @if($b->status === 'pending')
                            <button wire:click="confirm({{ $b->id }})" class="btn btn-xs btn-outline-success" style="padding:.2rem .5rem;font-size:.75rem;" title="تأیید"><i class="bi bi-check"></i></button>
                            @endif
                            @if($b->status !== 'cancelled')
                            <button wire:click="cancel({{ $b->id }})" data-swal-confirm="لغو شود؟" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem;" title="لغو"><i class="bi bi-x"></i></button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">رزروی یافت نشد</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $bookings->links() }}</div>
</div>

</div>
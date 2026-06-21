<div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>کاربران (مهمانان اقامتگاه‌های من) ({{ $users->total() }})</h5>
    <a href="{{ route('host.users.export', $exportQuery) }}" class="btn btn-success btn-sm">
        <i class="bi bi-file-earmark-excel me-1"></i>خروجی اکسل
        @if($hasActiveFilters)
        <span class="badge bg-white text-success ms-1">فیلترشده</span>
        @endif
    </a>
</div>

<x-filters.host-user-panel
    :accommodations="$accommodations"
    :veteran-options="$veteranOptions"
    :has-active-filters="$hasActiveFilters"
/>

<x-filters.summary-stats :count-filtered="$countFiltered" />

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>نام</th>
                    <th>موبایل</th>
                    <th>کد ملی</th>
                    <th>گروه ایثارگری</th>
                    <th>تعداد رزرو</th>
                    <th>آخرین رزرو</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $u)
                <tr wire:key="host-user-{{ $u->id }}">
                    <td class="small fw-semibold">{{ $u->name ?? '—' }}</td>
                    <td class="small" dir="ltr">{{ $u->mobile }}</td>
                    <td class="small" dir="ltr">{{ $u->national_id ?? '—' }}</td>
                    <td class="small">{{ $u->veteranLabel() }}</td>
                    <td class="small">{{ $u->host_bookings_count }}</td>
                    <td class="small text-muted">
                        @if($u->last_booking_at)
                            @jalali($u->last_booking_at)
                        @else
                            —
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center text-muted small py-4">
                        @if($hasActiveFilters)
                            کاربری با این فیلترها یافت نشد.
                        @else
                            هنوز کاربری در اقامتگاه‌های شما رزرو نکرده است.
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
    <div class="card-footer bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="small text-muted">{{ number_format($countFiltered) }} کاربر</div>
        {{ $users->links() }}
    </div>
    @endif
</div>

</div>

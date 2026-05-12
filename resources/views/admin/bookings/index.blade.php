@extends('layouts.admin')
@section('title', 'مدیریت رزروها')
@section('page-title', 'رزروها')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-calendar-check me-2"></i>رزروها ({{ $bookings->total() }})</h5>
    <a href="{{ route('admin.bookings.export', request()->query()) }}" class="btn btn-success btn-sm">
        <i class="bi bi-file-earmark-excel me-1"></i>خروجی اکسل
    </a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="کد رزرو / نام / موبایل / اقامتگاه" value="{{ request('search') }}">
            </div>
            <div class="col-6 col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="pending" {{ request('status')=='pending'?'selected':'' }}>در انتظار</option>
                    <option value="confirmed" {{ request('status')=='confirmed'?'selected':'' }}>تأیید شده</option>
                    <option value="cancelled" {{ request('status')=='cancelled'?'selected':'' }}>لغو شده</option>
                </select>
            </div>
            <div class="col-3 col-md-2"><button class="btn btn-sm btn-primary w-100">فیلتر</button></div>
            <div class="col-3 col-md-2"><a href="{{ route('admin.bookings.index') }}" class="btn btn-sm btn-outline-secondary w-100">پاک</a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>کد</th><th>کاربر</th><th>اقامتگاه</th><th>اتاق</th><th>ورود</th><th>خروج</th><th>شب</th><th>مبلغ</th><th>وضعیت</th><th>عملیات</th></tr>
            </thead>
            <tbody>
                @forelse($bookings as $b)
                <tr>
                    <td>
                        <a href="{{ route('admin.bookings.show', $b) }}" class="text-decoration-none">
                            <code class="small">{{ $b->tracking_code }}</code>
                        </a>
                    </td>
                    <td class="small">
                        <a href="{{ route('admin.users.show', $b->user) }}" class="text-decoration-none text-dark">
                            {{ $b->user->name ?? $b->user->mobile }}
                        </a>
                        @if($b->user->discount_percentage > 0)
                        <br><span class="badge bg-warning text-dark" style="font-size:.65rem">{{ $b->user->discount_percentage }}% تخفیف</span>
                        @endif
                    </td>
                    <td class="small">
                        <a href="{{ route('admin.accommodations.edit', $b->accommodation) }}" class="text-decoration-none text-dark">
                            {{ Str::limit($b->accommodation->name ?? '', 22) }}
                        </a>
                    </td>
                    <td class="small">{{ $b->roomType?->name ?? '—' }}</td>
                    <td class="small">@jalali($b->check_in)</td>
                    <td class="small">@jalali($b->check_out)</td>
                    <td>{{ $b->nights }}</td>
                    <td class="small">{{ number_format($b->total_price) }} ت</td>
                    <td><span class="badge bg-{{ $b->statusColor() }}">{{ $b->statusLabel() }}</span></td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <a href="{{ route('admin.bookings.show', $b) }}" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem;" title="جزئیات"><i class="bi bi-eye"></i></a>
                            @if($b->status === 'pending')
                            <form action="{{ route('admin.bookings.status', $b) }}" method="POST">
                                @csrf
                                <input type="hidden" name="status" value="confirmed">
                                <button class="btn btn-xs btn-outline-success" style="padding:.2rem .5rem;font-size:.75rem;" title="تأیید"><i class="bi bi-check-lg"></i></button>
                            </form>
                            <form action="{{ route('admin.bookings.status', $b) }}" method="POST" onsubmit="return confirm('لغو شود؟')">
                                @csrf
                                <input type="hidden" name="status" value="cancelled">
                                <button class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem;" title="لغو"><i class="bi bi-x-lg"></i></button>
                            </form>
                            @elseif($b->status === 'confirmed')
                            <form action="{{ route('admin.bookings.status', $b) }}" method="POST" onsubmit="return confirm('لغو شود؟')">
                                @csrf
                                <input type="hidden" name="status" value="cancelled">
                                <button class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem;" title="لغو"><i class="bi bi-x-lg"></i></button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">رزروی یافت نشد</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $bookings->links() }}</div>
</div>
@endsection

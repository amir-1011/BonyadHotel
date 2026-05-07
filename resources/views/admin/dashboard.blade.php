@extends('layouts.admin')
@section('title', 'داشبورد مدیریت')
@section('page-title', 'داشبورد مدیریت')

@section('content')
{{-- Stats Row --}}
<div class="row g-3 mb-4">
    @php
    $cards = [
        ['label'=>'کل کاربران',       'value'=>$stats['users'],          'icon'=>'people-fill',        'bg'=>'bg-primary',   'text'=>'text-primary',   'href'=> route('admin.users.index')],
        ['label'=>'میزبان‌ها',          'value'=>$stats['hosts'],          'icon'=>'house-heart-fill',   'bg'=>'bg-success',   'text'=>'text-success',   'href'=> route('admin.users.index', ['role'=>'host'])],
        ['label'=>'اقامتگاه‌ها',        'value'=>$stats['accommodations'], 'icon'=>'building-fill',      'bg'=>'bg-info',      'text'=>'text-info',      'href'=> route('admin.accommodations.index')],
        ['label'=>'کل رزروها',         'value'=>$stats['bookings'],       'icon'=>'calendar-check-fill','bg'=>'bg-warning',   'text'=>'text-warning',   'href'=> route('admin.bookings.index')],
        ['label'=>'تأیید شده',          'value'=>$stats['confirmed'],      'icon'=>'check-circle-fill',  'bg'=>'bg-success',   'text'=>'text-success',   'href'=> route('admin.bookings.index', ['status'=>'confirmed'])],
        ['label'=>'در انتظار تأیید',    'value'=>$stats['pending'],        'icon'=>'clock-fill',         'bg'=>'bg-warning',   'text'=>'text-warning',   'href'=> route('admin.bookings.index', ['status'=>'pending'])],
        ['label'=>'درآمد کل (تومان)',   'value'=>number_format($stats['revenue']), 'icon'=>'currency-exchange','bg'=>'bg-danger','text'=>'text-danger',  'href'=> route('admin.bookings.index', ['status'=>'confirmed'])],
        ['label'=>'نظرات',             'value'=>$stats['reviews'],        'icon'=>'star-fill',          'bg'=>'bg-secondary', 'text'=>'text-secondary', 'href'=> route('admin.reviews.index')],
    ];
    @endphp
    @foreach($cards as $c)
    <div class="col-6 col-md-4 col-xl-3">
        <a href="{{ $c['href'] }}" class="text-decoration-none">
        <div class="card stat-card shadow-sm h-100 border-0" style="transition:.2s" onmouseenter="this.style.transform='translateY(-3px)'" onmouseleave="this.style.transform=''">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon {{ $c['bg'] }} bg-opacity-10">
                    <i class="bi bi-{{ $c['icon'] }} {{ $c['text'] }}"></i>
                </div>
                <div>
                    <div class="fw-bold fs-5 text-dark">{{ $c['value'] }}</div>
                    <div class="text-muted small">{{ $c['label'] }}</div>
                </div>
                <div class="me-auto"><i class="bi bi-arrow-left-short text-muted"></i></div>
            </div>
        </div>
        </a>
    </div>
    @endforeach
</div>

<div class="row g-3">
    {{-- Recent Bookings --}}
    <div class="col-12 col-xl-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold"><i class="bi bi-calendar-check me-2 text-primary"></i>آخرین رزروها</h6>
                <a href="{{ route('admin.bookings.index') }}" class="btn btn-sm btn-outline-primary">مشاهده همه</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>کد رزرو</th><th>کاربر</th><th>اقامتگاه</th><th>مبلغ</th><th>وضعیت</th><th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentBookings as $b)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.bookings.show', $b) }}" class="text-decoration-none">
                                        <code class="small">{{ $b->tracking_code }}</code>
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('admin.users.show', $b->user) }}" class="text-decoration-none text-dark">
                                        {{ $b->user->name ?? $b->user->mobile }}
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('admin.accommodations.edit', $b->accommodation) }}" class="text-decoration-none text-dark">
                                        {{ Str::limit($b->accommodation->name, 25) }}
                                    </a>
                                </td>
                                <td class="small">{{ number_format($b->total_price) }} ت</td>
                                <td><span class="badge bg-{{ $b->statusColor() }}">{{ $b->statusLabel() }}</span></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('admin.bookings.show', $b) }}" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem;" title="جزئیات"><i class="bi bi-eye"></i></a>
                                        @if($b->status === 'pending')
                                        <form action="{{ route('admin.bookings.status', $b) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="status" value="confirmed">
                                            <button class="btn btn-xs btn-outline-success" style="padding:.2rem .5rem;font-size:.75rem;" title="تأیید"><i class="bi bi-check-lg"></i></button>
                                        </form>
                                        <form action="{{ route('admin.bookings.status', $b) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="status" value="cancelled">
                                            <button class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem;" title="لغو"><i class="bi bi-x-lg"></i></button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">رزروی وجود ندارد</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Top Accommodations --}}
    <div class="col-12 col-xl-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-trophy me-2 text-warning"></i>برترین اقامتگاه‌ها</h6>
            </div>
            <div class="list-group list-group-flush">
                @forelse($topAccommodations as $i => $acc)
                <div class="list-group-item d-flex align-items-center gap-2">
                    <span class="badge bg-warning text-dark rounded-circle" style="width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:.75rem;">{{ $i+1 }}</span>
                    <div class="flex-grow-1">
                        <div class="small fw-semibold">{{ Str::limit($acc->name,26) }}</div>
                        <div class="text-muted" style="font-size:.75rem">{{ $acc->city->name ?? '' }}</div>
                    </div>
                    <span class="badge bg-primary">{{ $acc->bookings_count }}</span>
                    <a href="{{ route('admin.bookings.index', ['search'=> $acc->name]) }}" class="btn btn-xs btn-outline-primary" style="padding:.15rem .4rem;font-size:.7rem;" title="رزروها"><i class="bi bi-calendar-check"></i></a>
                    <a href="{{ route('admin.accommodations.edit', $acc) }}" class="btn btn-xs btn-outline-warning" style="padding:.15rem .4rem;font-size:.7rem;" title="ویرایش"><i class="bi bi-pencil"></i></a>
                </div>
                @empty
                <div class="list-group-item text-muted text-center small py-3">داده‌ای موجود نیست</div>
                @endforelse
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-plus me-2 text-success"></i>کاربران جدید</h6>
            </div>
            <div class="list-group list-group-flush">
                @foreach($recentUsers as $u)
                <div class="list-group-item d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:36px;height:36px;flex-shrink:0">
                        <i class="bi bi-person text-primary"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="small fw-semibold">{{ $u->name ?? 'بدون نام' }}</div>
                        <div class="text-muted" style="font-size:.72rem">{{ $u->mobile }}</div>
                    </div>
                    @if($u->hasRole('super_admin'))
                        <span class="badge bg-danger">ادمین</span>
                    @elseif($u->hasRole('host'))
                        <span class="badge bg-success">میزبان</span>
                    @else
                        <span class="badge bg-secondary">کاربر</span>
                    @endif
                    <a href="{{ route('admin.users.show', $u) }}" class="btn btn-xs btn-outline-primary ms-1" style="padding:.15rem .4rem;font-size:.7rem;" title="مشاهده"><i class="bi bi-eye"></i></a>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

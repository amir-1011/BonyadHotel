<div>

<div class="row g-3 mb-4">
    @php
    $cards = [
        ['label'=>'اقامتگاه‌هایم',   'value'=>$stats['accommodations'], 'icon'=>'building',         'color'=>'primary',   'href'=> route('host.accommodations.index')],
        ['label'=>'رزرو تأیید شده',   'value'=>$stats['confirmed'],      'icon'=>'check-circle',     'color'=>'success',   'href'=> route('host.bookings.index', ['status'=>'confirmed'])],
        ['label'=>'در انتظار تأیید',  'value'=>$stats['pending'],        'icon'=>'clock',            'color'=>'warning',   'href'=> route('host.bookings.index', ['status'=>'pending'])],
        ['label'=>'درآمد (تومان)',     'value'=>number_format($stats['revenue']), 'icon'=>'currency-exchange','color'=>'info', 'href'=> route('host.bookings.index', ['status'=>'confirmed'])],
        ['label'=>'نظرات بی‌پاسخ',   'value'=>$stats['pending_reviews'],'icon'=>'chat-square-text', 'color'=>'danger',    'href'=> route('host.reviews.index', ['replied'=>'0'])],
    ];
    @endphp
    @foreach($cards as $c)
    <div class="col-6 col-md-4 col-xl">
        <a href="{{ $c['href'] }}" class="text-decoration-none">
        <div class="card stat-card shadow-sm border-0" style="transition:.2s" onmouseenter="this.style.transform='translateY(-3px)'" onmouseleave="this.style.transform=''">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-3 bg-{{ $c['color'] }} bg-opacity-10 d-flex align-items-center justify-content-center" style="width:48px;height:48px;flex-shrink:0">
                    <i class="bi bi-{{ $c['icon'] }} text-{{ $c['color'] }} fs-4"></i>
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
    {{-- Recent bookings --}}
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-calendar-check me-2 text-success"></i>آخرین رزروها</h6>
                <a wire:navigate href="{{ route('host.bookings.index') }}" class="btn btn-sm btn-outline-success">همه</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>مهمان</th><th>اقامتگاه</th><th>ورود</th><th>مبلغ</th><th>وضعیت</th><th>عملیات</th></tr>
                    </thead>
                    <tbody>
                        @forelse($recentBookings as $b)
                        <tr>
                            <td class="small">{{ $b->user->name ?? $b->user->mobile }}</td>
                            <td class="small">
                                <a wire:navigate href="{{ route('host.accommodations.edit', $b->accommodation) }}" class="text-decoration-none text-dark">
                                    {{ Str::limit($b->accommodation->name, 22) }}
                                </a>
                            </td>
                            <td class="small">@jalali($b->check_in)</td>
                            <td class="small">{{ number_format($b->total_price) }}</td>
                            <td><span class="badge bg-{{ $b->statusColor() }}">{{ $b->statusLabel() }}</span></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a wire:navigate href="{{ route('host.bookings.show', $b) }}" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem;" title="جزئیات"><i class="bi bi-eye"></i></a>
                                    @if($b->status === 'pending')
                                    <button wire:click="confirm({{ $b->id }})" class="btn btn-xs btn-outline-success" style="padding:.2rem .5rem;font-size:.75rem;" title="تأیید"><i class="bi bi-check-lg"></i></button>
                                    <button wire:click="cancel({{ $b->id }})" data-swal-confirm="لغو شود؟" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem;" title="لغو"><i class="bi bi-x-lg"></i></button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">رزروی موجود نیست</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- My accommodations --}}
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-building me-2 text-primary"></i>اقامتگاه‌هایم</h6>
                <a wire:navigate href="{{ route('host.accommodations.create') }}" class="btn btn-sm btn-success"><i class="bi bi-plus"></i></a>
            </div>
            <div class="list-group list-group-flush">
                @forelse($myAccommodations as $acc)
                <div class="list-group-item d-flex align-items-center gap-2">
                    <div class="flex-grow-1">
                        <div class="small fw-semibold">{{ Str::limit($acc->name, 26) }}</div>
                        <div class="text-muted" style="font-size:.72rem">{{ $acc->city->name ?? '' }} — {{ $acc->bookings_count }} رزرو</div>
                    </div>
                    <span class="badge bg-{{ $acc->is_active ? 'success' : 'secondary' }}">{{ $acc->is_active ? 'فعال' : 'غیرفعال' }}</span>
                    <a wire:navigate href="{{ route('host.bookings.index', ['accommodation_id'=> $acc->id]) }}" class="btn btn-xs btn-outline-primary" style="padding:.15rem .4rem;font-size:.7rem;" title="رزروها"><i class="bi bi-calendar-check"></i></a>
                    <a wire:navigate href="{{ route('host.accommodations.edit', $acc) }}" class="btn btn-xs btn-outline-warning" style="padding:.15rem .4rem;font-size:.7rem;" title="ویرایش"><i class="bi bi-pencil"></i></a>
                    <a href="{{ route('accommodations.show', $acc) }}" class="btn btn-xs btn-outline-secondary" style="padding:.15rem .4rem;font-size:.7rem;" title="نمایش در سایت" target="_blank"><i class="bi bi-box-arrow-up-right"></i></a>
                </div>
                @empty
                <div class="list-group-item text-muted small text-center py-3">هنوز اقامتگاهی ثبت نکرده‌اید</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

</div>
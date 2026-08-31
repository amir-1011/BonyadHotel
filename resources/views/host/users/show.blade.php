<div>

<div class="ta-page-toolbar">
    <x-host.can page="users.edit" action="read">
        <a wire:navigate href="{{ route('host.users.edit', $user) }}" class="btn btn-sm btn-warning"><i class="bi bi-pencil me-1"></i>ویرایش</a>
    </x-host.can>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body text-center py-4">
                <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width:72px;height:72px">
                    <i class="bi bi-person-fill text-primary fs-2"></i>
                </div>
                <h6 class="fw-bold">{{ $user->name ?? 'بدون نام' }}</h6>
                <p class="text-muted small mb-2">{{ $user->mobile }}</p>
                <span class="badge bg-light text-dark border">{{ $user->roleBadgeLabel() }}</span>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between small">
                    <span class="text-muted">{{ $user->identityFieldLabel() }}</span>
                    <strong dir="ltr">{{ $user->identityNumber() ?? '—' }}</strong>
                </li>
                @if($user->isForeignGuestProfile())
                <li class="list-group-item d-flex justify-content-between small">
                    <span class="text-muted">محل اقامت</span>
                    <strong>{{ $user->residenceLocationLabel() ?? '—' }}</strong>
                </li>
                @endif
                @if($user->isHost())
                <li class="list-group-item d-flex justify-content-between small">
                    <span class="text-muted">سمت</span><strong>{{ $user->hostRoleLabel() }}</strong>
                </li>
                @php($accounting = $user->accountingProfileDetails())
                <li class="list-group-item d-flex justify-content-between small">
                    <span class="text-muted">کد حسابداری پرسنلی</span>
                    <strong dir="ltr">{{ $accounting['code'] ?? '—' }}</strong>
                </li>
                @if($accounting && $accounting['province_name'])
                <li class="list-group-item d-flex justify-content-between small">
                    <span class="text-muted">استان (کدینگ)</span>
                    <strong>{{ $accounting['province_name'] }}</strong>
                </li>
                @endif
                @endif
                <li class="list-group-item d-flex justify-content-between small">
                    <span class="text-muted">گروه ایثارگری</span><strong>{{ $user->veteranLabel() }}</strong>
                </li>
                <li class="list-group-item d-flex justify-content-between small">
                    <span class="text-muted">درصد تخفیف</span><strong>{{ $user->discount_percentage }}%</strong>
                </li>
                <li class="list-group-item d-flex justify-content-between small">
                    <span class="text-muted">تاریخ ثبت</span><strong>@jalali($user->created_at)</strong>
                </li>
            </ul>
        </div>

        @if($user->hasAccountingProfile())
        <x-profile.accounting-code-card :user="$user" class="mt-3" />
        @endif
    </div>

    <div class="col-12 col-lg-8">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-calendar-check me-2"></i>رزروها ({{ $user->bookings->count() }})</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>اقامتگاه</th><th>ورود</th><th>خروج</th><th>مبلغ</th><th>وضعیت</th></tr>
                    </thead>
                    <tbody>
                        @forelse($user->bookings->take(10) as $b)
                        <tr>
                            <td class="small">{{ Str::limit($b->accommodation->name ?? '', 25) }}</td>
                            <td class="small">@jalali($b->check_in)</td>
                            <td class="small">@jalali($b->check_out)</td>
                            <td class="small">{{ \App\Support\PdfPersian::toPersianDigits(number_format($b->total_price)) }}</td>
                            <td><span class="badge bg-{{ $b->statusColor() }}">{{ $b->statusLabel() }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted small py-2">رزروی ندارد</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($user->accommodations->count())
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-building me-2"></i>اقامتگاه‌ها ({{ $user->accommodations->count() }})</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>نام</th><th>شهر</th><th>قیمت/شب/تخت (ریال)</th><th>وضعیت</th></tr></thead>
                    <tbody>
                        @foreach($user->accommodations as $acc)
                        <tr>
                            <td class="small">{{ Str::limit($acc->name, 30) }}</td>
                            <td class="small">{{ $acc->city->name ?? '' }}</td>
                            <td class="small">{{ \App\Support\PdfPersian::toPersianDigits(number_format($acc->price_per_night)) }}</td>
                            <td><span class="badge bg-{{ $acc->is_active ? 'success' : 'secondary' }}">{{ $acc->is_active ? 'فعال' : 'غیرفعال' }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>

</div>

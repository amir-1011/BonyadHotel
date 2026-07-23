<div>

<div class="d-flex align-items-center gap-2 mb-3">
    <a wire:navigate href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right me-1"></i>بازگشت</a>
    <a wire:navigate href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-warning"><i class="bi bi-pencil me-1"></i>ویرایش</a>
</div>

<div class="row g-3">
    {{-- Info --}}
    <div class="col-12 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body text-center py-4">
                <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center mb-3" style="width:72px;height:72px">
                    <i class="bi bi-person-fill text-primary fs-2"></i>
                </div>
                <h6 class="fw-bold">{{ $user->name ?? 'بدون نام' }}</h6>
                <p class="text-muted small mb-2">{{ $user->mobile }}</p>
                @foreach($user->roles as $r)
                    <span class="badge {{ $r->name === 'super_admin' ? 'bg-danger' : ($r->name === 'host' ? 'bg-success' : 'bg-secondary') }} me-1">{{ $user->roleBadgeLabel($r->name) }}</span>
                @endforeach
                @if($user->roles->isEmpty()) <span class="badge bg-secondary">{{ $user->roleBadgeLabel('guest') }}</span> @endif
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

        {{-- Assign Role --}}
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-shield-check me-1"></i>تغییر نقش</div>
            <div class="card-body">
                <select wire:model="selectedRole" class="form-select form-select-sm mb-2">
                    <option value="">انتخاب نقش</option>
                    <option value="super_admin">super_admin</option>
                    <option value="host">host</option>
                    <option value="guest">مهمان</option>
                </select>
                <button wire:click="assignRole" class="btn btn-sm btn-primary w-100">ذخیره نقش</button>
            </div>
        </div>
    </div>

    {{-- Bookings & Accommodations --}}
    <div class="col-12 col-lg-8">
        {{-- Bookings --}}
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
                            <td class="small">{{ number_format($b->total_price) }}</td>
                            <td><span class="badge bg-{{ $b->statusColor() }}">{{ $b->statusLabel() }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted small py-2">رزروی ندارد</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Accommodations if host --}}
        @if($user->accommodations->count())
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold small"><i class="bi bi-building me-2"></i>اقامتگاه‌ها ({{ $user->accommodations->count() }})</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>نام</th><th>شهر</th><th>قیمت/شب/تخت</th><th>وضعیت</th></tr></thead>
                    <tbody>
                        @foreach($user->accommodations as $acc)
                        <tr>
                            <td class="small">{{ Str::limit($acc->name, 30) }}</td>
                            <td class="small">{{ $acc->city->name ?? '' }}</td>
                            <td class="small">{{ number_format($acc->price_per_night) }}</td>
                            <td><span class="badge bg-{{ $acc->is_active ? 'success' : 'secondary' }}">{{ $acc->is_active ? 'فعال' : 'غیرفعال' }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if($programBeneficiaryHistory->isNotEmpty() || $bookingBeneficiaryHistory->isNotEmpty())
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">تاریخچه ذی‌نفع</h5>
                </div>
                <div class="card-body">
                    @if($programBeneficiaryHistory->isNotEmpty())
                        <h6 class="text-muted mb-3">اردوها</h6>
                        <div class="table-responsive mb-4">
                            <table class="table table-sm table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>اردو</th>
                                        <th>اقامتگاه</th>
                                        <th>بدهی</th>
                                        <th>تاریخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($programBeneficiaryHistory as $row)
                                        <tr>
                                            <td>
                                                <a href="{{ route('admin.programs.show', $row->program) }}">
                                                    {{ $row->program?->title ?? '—' }}
                                                </a>
                                            </td>
                                            <td>{{ $row->program?->accommodation?->name ?? '—' }}</td>
                                            <td>{{ number_format((int) $row->debt_amount) }} تومان</td>
                                            <td class="small">@jalali($row->created_at)</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if($bookingBeneficiaryHistory->isNotEmpty())
                        <h6 class="text-muted mb-3">رزروهای دستی</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>رزرو</th>
                                        <th>اقامتگاه</th>
                                        <th>بدهی</th>
                                        <th>تاریخ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bookingBeneficiaryHistory as $row)
                                        <tr>
                                            <td>
                                                <a href="{{ route('admin.bookings.show', $row->booking) }}">
                                                    #{{ $row->booking_id }}
                                                </a>
                                            </td>
                                            <td>{{ $row->booking?->accommodation?->name ?? '—' }}</td>
                                            <td>{{ number_format((int) $row->debt_amount) }} تومان</td>
                                            <td class="small">@jalali($row->created_at)</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

</div>
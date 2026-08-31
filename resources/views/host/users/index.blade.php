<div>

@php($hostUser = auth()->user())

<x-filters.host-user-panel
    :accommodations="$accommodations"
    :provinces="$provinces"
    :veteran-options="$veteranOptions"
    :user-type-options="$userTypeOptions"
    :has-bookings-options="$hasBookingsOptions"
    :sort-options="$sortOptions"
    :has-active-filters="$hasActiveFilters"
    :count-filtered="$countFiltered"
>
    <x-slot:actions>
        <x-host.can page="users.create-host" action="write">
        <a wire:navigate href="{{ route('host.users.create-host') }}" class="btn btn-success btn-sm">
            <i class="bi bi-person-plus me-1"></i>افزودن میزبان
        </a>
        </x-host.can>
        <x-host.can page="users.export" action="read">
        <a href="{{ route('host.users.export', $exportQuery) }}" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>خروجی اکسل
            @if($hasActiveFilters)
            <span class="badge bg-white text-success ms-1">فیلترشده</span>
            @endif
        </a>
        </x-host.can>
    </x-slot>
</x-filters.host-user-panel>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>نام</th>
                    <th>نوع کاربر</th>
                    <th>موبایل</th>
                    <th>شناسه</th>
                    <th>محل اقامت</th>
                    <th>گروه ایثارگری</th>
                    <th>تعداد رزرو</th>
                    <th>آخرین رزرو</th>
                    <th class="text-end">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $u)
                <tr wire:key="host-user-{{ $u->id }}">
                    <td class="small fw-semibold">
                        @if(auth()->user()->hostCan('users.show', 'read'))
                        <a wire:navigate href="{{ route('host.users.show', $u) }}" class="text-decoration-none text-dark">{{ $u->name ?? '—' }}</a>
                        @else
                        {{ $u->name ?? '—' }}
                        @endif
                    </td>
                    <td class="small">
                        <span class="badge bg-light text-dark border">{{ $u->roleBadgeLabel() }}</span>
                    </td>
                    <td class="small" dir="ltr">{{ $u->mobile }}</td>
                    <td class="small" dir="ltr">
                        @if($u->identityNumber())
                        <span class="text-muted">{{ $u->identityFieldLabel() }}:</span> {{ $u->identityNumber() }}
                        @else
                        —
                        @endif
                    </td>
                    <td class="small">{{ $u->residenceLocationLabel() ?? '—' }}</td>
                    <td class="small">{{ $u->veteranLabel() }}</td>
                    <td class="small">{{ $u->host_bookings_count }}</td>
                    <td class="small text-muted">
                        @if($u->last_booking_at)
                            @jalali($u->last_booking_at)
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <x-host.can page="users.show" action="read">
                        <a wire:navigate href="{{ route('host.users.show', $u) }}" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem;" title="مشاهده">
                            <i class="bi bi-eye"></i>
                        </a>
                        </x-host.can>
                        <x-host.can page="users.edit" action="read">
                        <a wire:navigate href="{{ route('host.users.edit', $u) }}" class="btn btn-xs btn-outline-warning" style="padding:.2rem .5rem;font-size:.75rem;" title="ویرایش">
                            <i class="bi bi-pencil"></i>
                        </a>
                        </x-host.can>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted small py-4">
                        @if($hasActiveFilters)
                            کاربری با این فیلترها یافت نشد.
                        @else
                            هنوز کاربری در محدوده دسترسی استانی شما ثبت نشده است.
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
    <div class="card-footer bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="small text-muted">{{ \App\Support\PdfPersian::toPersianDigits(number_format($countFiltered)) }} کاربر</div>
        {{ $users->links() }}
    </div>
    @endif
</div>

</div>

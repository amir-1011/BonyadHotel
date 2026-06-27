<div>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-calendar-check me-2"></i>رزروها ({{ $bookings->total() }})</h5>
    <a href="{{ route('host.bookings.export', $exportQuery) }}" class="btn btn-success btn-sm">
        <i class="bi bi-file-earmark-excel me-1"></i>خروجی اکسل
        @if($hasActiveFilters)
        <span class="badge bg-white text-success ms-1">فیلترشده</span>
        @endif
    </a>
</div>

<x-filters.booking-panel
    :accommodations="$accommodations"
    :provinces="$provinces"
    :cities="$cities"
    :counties="$counties"
    :service-catalogs="$serviceCatalogs"
    :service-variants="$serviceVariants"
    :show-service-accommodation="$showServiceAccommodation"
    :draft-province-id="$draftProvinceId"
    :draft-service-catalog-id="$draftServiceCatalogId"
    :has-active-filters="$hasActiveFilters"
/>

<x-filters.summary-stats :count-filtered="$countFiltered" :total-filtered="$totalFiltered" />

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>
                        <button type="button" wire:click="sortBy('id')" class="btn btn-link btn-sm p-0 text-dark text-decoration-none border-0">
                            کد <x-filters.booking-sort-icon column="id" :sort="$sort" :dir="$dir" />
                        </button>
                    </th>
                    <th>مهمان</th>
                    <th>اقامتگاه</th>
                    <th>اتاق</th>
                    <th>
                        <button type="button" wire:click="sortBy('check_in')" class="btn btn-link btn-sm p-0 text-dark text-decoration-none border-0">
                            ورود <x-filters.booking-sort-icon column="check_in" :sort="$sort" :dir="$dir" />
                        </button>
                    </th>
                    <th>
                        <button type="button" wire:click="sortBy('check_out')" class="btn btn-link btn-sm p-0 text-dark text-decoration-none border-0">
                            خروج <x-filters.booking-sort-icon column="check_out" :sort="$sort" :dir="$dir" />
                        </button>
                    </th>
                    <th>
                        <button type="button" wire:click="sortBy('nights')" class="btn btn-link btn-sm p-0 text-dark text-decoration-none border-0">
                            شب <x-filters.booking-sort-icon column="nights" :sort="$sort" :dir="$dir" />
                        </button>
                    </th>
                    <th>
                        <button type="button" wire:click="sortBy('total_price')" class="btn btn-link btn-sm p-0 text-dark text-decoration-none border-0">
                            مبلغ <x-filters.booking-sort-icon column="total_price" :sort="$sort" :dir="$dir" />
                        </button>
                    </th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($bookings as $b)
                <tr wire:key="host-booking-{{ $b->id }}">
                    <td><code class="small">{{ $b->tracking_code }}</code></td>
                    <td class="small">{{ $b->bookerName() }}</td>
                    <td class="small">{{ Str::limit($b->accommodation->name, 22) }}</td>
                    <td class="small">{{ $b->roomType?->name ?? '—' }}</td>
                    <td class="small">@jalali($b->check_in)</td>
                    <td class="small">@jalali($b->check_out)</td>
                    <td>{{ $b->nights }}</td>
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
                <tr><td colspan="10" class="text-center text-muted py-4">رزروی یافت نشد</td></tr>
                @endforelse
            </tbody>
            @if($bookings->count() > 0)
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="7" class="text-end small text-muted">جمع این صفحه:</td>
                    <td class="text-success small">{{ number_format($bookings->sum('total_price')) }} ت</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
    <div class="card-footer bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="small text-muted">
            جمع کل فیلتر: <strong class="text-success">{{ number_format($totalFiltered) }} تومان</strong>
            &nbsp;|&nbsp; {{ number_format($countFiltered) }} رزرو
        </div>
        {{ $bookings->links() }}
    </div>
</div>

<x-filters.booking-scripts />

</div>

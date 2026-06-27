<div>

<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-building me-2"></i>اقامتگاه‌ها ({{ $accommodations->total() }})</h5>
    <div class="d-flex gap-2">
        <a wire:navigate href="{{ route('admin.accommodations.import') }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-upload me-1"></i>درون‌ریزی CSV
        </a>
        <a wire:navigate href="{{ route('admin.accommodations.create') }}" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg me-1"></i>اقامتگاه جدید
        </a>
    </div>
</div>

<x-tutorial-videos :videos="[
    ['label' => 'ثبت اقامتگاه', 'file' => 'اقامتگاه.mp4'],
    ['label' => 'رزرو دستی', 'file' => 'رزرو.mp4'],
]" />

<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="جستجو نام یا میزبان..." value="{{ request('search') }}">
            </div>
            <div class="col-6 col-md-2">
                <select name="type" class="form-select form-select-sm">
                    @php $typeOptions = \App\Models\AccommodationType::options(); @endphp
                    <option value="">همه انواع</option>
                    @foreach($typeOptions as $v => $l)
                    <option value="{{ $v }}" {{ request('type')==$v?'selected':'' }}>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">همه</option>
                    <option value="active" {{ request('status')=='active'?'selected':'' }}>فعال</option>
                    <option value="inactive" {{ request('status')=='inactive'?'selected':'' }}>غیرفعال</option>
                </select>
            </div>
            <div class="col-6 col-md-2"><button class="btn btn-sm btn-primary w-100">فیلتر</button></div>
            <div class="col-6 col-md-1"><a wire:navigate href="{{ route('admin.accommodations.index') }}" class="btn btn-sm btn-outline-secondary w-100">پاک</a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>نام / نوع</th><th>شهر</th><th>قیمت/شب</th><th>میزبان</th><th>وضعیت اداره</th><th>وضعیت</th><th>عملیات</th></tr>
            </thead>
            <tbody>
                @forelse($accommodations as $acc)
                <tr>
                    <td>{{ $acc->id }}</td>
                    <td>
                        <a href="{{ route('accommodations.show', $acc) }}" class="text-decoration-none fw-semibold text-dark" target="_blank">
                            {{ Str::limit($acc->name,30) }}
                        </a>
                        <br><span class="badge bg-info text-dark" style="font-size:.68rem">{{ $acc->typeLabel() }}</span>
                    </td>
                    <td class="small text-muted">
                        {{ $acc->city->name ?? '—' }}
                        @if($acc->county)
                        <br><span style="font-size:.7rem">شهرستان {{ $acc->county->name }}</span>
                        @endif
                        @if($acc->city->province)
                        <br><span style="font-size:.7rem">{{ $acc->city->province->name }}</span>
                        @endif
                    </td>
                    <td class="small">{{ number_format($acc->price_per_night) }} ت</td>
                    <td class="small">
                        @if($acc->host)
                        <a wire:navigate href="{{ route('admin.users.show', $acc->host) }}" class="text-decoration-none text-dark">
                            {{ $acc->host->name }}
                        </a>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="small">
                        @if($acc->managementStatusLabel())
                        <span class="badge bg-{{ $acc->management_status === 'self_governing' ? 'primary' : 'warning' }} text-dark" style="font-size:.68rem">
                            {{ $acc->managementStatusLabel() }}
                        </span>
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <button wire:click="toggleActive({{ $acc->id }})" class="badge border-0 bg-{{ $acc->is_active ? 'success' : 'secondary' }}" title="کلیک برای تغییر وضعیت">
                                {{ $acc->is_active ? 'فعال' : 'غیرفعال' }}
                            </button>
                    </td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <a href="{{ route('accommodations.show', $acc) }}" class="btn btn-xs btn-outline-secondary" style="padding:.2rem .5rem;font-size:.75rem;" title="نمایش در سایت" target="_blank"><i class="bi bi-box-arrow-up-right"></i></a>
                            <a wire:navigate href="{{ route('admin.accommodations.manual-booking', $acc) }}" class="btn btn-xs btn-success" style="padding:.2rem .5rem;font-size:.75rem;" title="رزرو دستی"><i class="bi bi-plus-circle"></i></a>
                            <a wire:navigate href="{{ route('admin.room-types.index', $acc) }}" class="btn btn-xs btn-outline-success" style="padding:.2rem .5rem;font-size:.75rem;" title="مدیریت اتاق‌ها"><i class="bi bi-door-open"></i></a>
                            <a wire:navigate href="{{ route('admin.accommodations.veteran-policy', $acc) }}" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem;" title="ایثارگری و خدمات"><i class="bi bi-shield-check"></i></a>
                            <a wire:navigate href="{{ route('admin.bookings.index', ['search'=> $acc->name]) }}" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem;" title="رزروها"><i class="bi bi-calendar-check"></i></a>
                            <a wire:navigate href="{{ route('admin.accommodations.edit', $acc) }}" class="btn btn-xs btn-outline-warning" style="padding:.2rem .5rem;font-size:.75rem;" title="ویرایش"><i class="bi bi-pencil"></i></a>
                            <button wire:click="destroy({{ $acc->id }})" data-swal-confirm="حذف شود؟" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem;" title="حذف"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">اقامتگاهی یافت نشد</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $accommodations->links() }}</div>
</div>

</div>
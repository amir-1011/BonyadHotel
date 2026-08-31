<div>

<div class="card shadow-sm mb-3">
    <div class="ta-list-chrome">
        <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1 min-w-0">
            <input type="text" wire:model.live.debounce.400ms="search" class="form-control form-control-sm" style="max-width:14rem" placeholder="جستجو عنوان، کارفرما، کد رزرو...">
            <select wire:model.live="status" class="form-select form-select-sm" style="max-width:9rem">
                <option value="">همه وضعیت‌ها</option>
                @foreach(\App\Models\Program::statusOptions() as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="programType" class="form-select form-select-sm" style="max-width:9rem">
                <option value="">همه انواع</option>
                @foreach(\App\Models\Program::typeOptions() as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="paymentType" class="form-select form-select-sm" style="max-width:9rem">
                <option value="">همه پرداخت‌ها</option>
                @foreach(\App\Models\Program::paymentTypeOptions() as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="accommodationId" class="form-select form-select-sm" style="max-width:12rem">
                <option value="0">همه اقامتگاه‌ها</option>
                @foreach($myAccommodations as $a)
                    <option value="{{ $a->id }}">{{ $a->name }}</option>
                @endforeach
            </select>
            <select wire:model.live="employerId" class="form-select form-select-sm" style="max-width:12rem">
                <option value="0">همه کارفرمایان</option>
                @foreach($employers as $employer)
                    <option value="{{ $employer->id }}">{{ $employer->displayLabel() }}</option>
                @endforeach
            </select>
            <select wire:model.live="beneficiaryId" class="form-select form-select-sm" style="max-width:12rem">
                <option value="0">همه ذینفعان</option>
                @foreach($beneficiaries as $b)
                    <option value="{{ $b->id }}">{{ $b->displayLabel() }}</option>
                @endforeach
            </select>
        </div>
        <div class="ta-page-toolbar">
            <x-host.can page="programs.supportive-report" action="read">
            <a wire:navigate href="{{ route('host.programs.supportive-report') }}" class="btn btn-sm btn-outline-success">
                <i class="bi bi-heart-fill me-1 text-danger"></i>گزارش خدمات حمایتی
            </a>
            </x-host.can>
            <x-host.can page="programs.create" action="write">
            <a wire:navigate href="{{ route('host.programs.create') }}" class="btn btn-sm btn-success">
                <i class="bi bi-plus-circle me-1"></i>برنامه جدید
            </a>
            </x-host.can>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>عنوان</th>
                    <th>اقامتگاه</th>
                    <th>نوع</th>
                    <th>تاریخ</th>
                    <th>نفرات</th>
                    <th>مبلغ کل</th>
                    <th>پرداخت</th>
                    <th>وضعیت</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($programs as $p)
                <tr wire:key="prog-{{ $p->id }}">
                    <td>{{ $p->id }}</td>
                    <td>
                        <a wire:navigate href="{{ route('host.programs.show', $p) }}" class="fw-semibold text-decoration-none">{{ $p->title }}</a>
                        @if($p->booking)<div class="small text-muted" dir="ltr">{{ $p->booking->tracking_code }}</div>@endif
                    </td>
                    <td class="small">{{ $p->accommodation?->name }}</td>
                    <td><span class="badge bg-info-subtle text-info border">{{ $p->programTypeLabel() }}</span></td>
                    <td class="small">
                        @if($p->booking)
                            @jalali($p->booking->check_in) — @jalali($p->booking->check_out)
                        @else — @endif
                    </td>
                    <td>{{ $p->guest_count }}</td>
                    <td>{{ \App\Support\PdfPersian::toPersianDigits(number_format($p->total_amount)) }}</td>
                    <td class="small">{{ $p->paymentTypeLabel() }}</td>
                    <td><span class="badge bg-{{ $p->statusColor() }}">{{ $p->statusLabel() }}</span></td>
                    <td>
                        <a wire:navigate href="{{ route('host.programs.show', $p) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" class="text-center text-muted py-4">برنامه‌ای یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($programs->hasPages())
    <div class="card-footer">{{ $programs->links() }}</div>
    @endif
</div>

</div>

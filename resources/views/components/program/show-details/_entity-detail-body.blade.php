@props([
    'entity',
    'type' => 'employer',
    'panel' => 'host',
    'debtAmount' => null,
    'debtDescription' => null,
    'documents' => [],
])

@php
    use App\Support\ProgramDocumentPaths;

    $isEmployer = $type === 'employer';
    $typeLabel = $isEmployer ? 'کارفرما' : 'ذینفع';
    $icon = $isEmployer ? 'building' : 'people';
    $accent = $isEmployer ? 'primary' : 'info';
    $accountingCode = $entity->employer_code ?? $entity->beneficiary_code ?? null;
    $accountingDetails = $entity->accountingProfileDetails();
    $userShowRoute = $panel === 'admin' ? 'admin.users.show' : null;
    $documentPaths = ProgramDocumentPaths::normalize($documents);
@endphp

<div class="program-entity-profile">
    <div class="d-flex align-items-center gap-3 rounded-3 border bg-light p-3 mb-3">
        <div class="rounded-circle bg-{{ $accent }} bg-opacity-10 text-{{ $accent }} d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:52px;height:52px;font-size:1.35rem">
            <i class="bi bi-{{ $icon }}"></i>
        </div>
        <div class="flex-grow-1 min-w-0">
            <div class="text-muted small mb-1">{{ $typeLabel }}</div>
            <div class="fw-bold fs-6 text-truncate">{{ $entity->name }}</div>
            @if(filled($accountingCode))
            <div class="small text-muted mt-1" dir="ltr">{{ $accountingCode }}</div>
            @endif
        </div>
    </div>

    <div class="mb-3">
        <div class="fw-semibold small text-muted mb-2 pb-1 border-bottom">اطلاعات تماس و هویت</div>
        <ul class="list-group list-group-flush">
            <li class="list-group-item d-flex justify-content-between align-items-start gap-3 px-0 py-2">
                <span class="text-muted">شناسه ملی / اقتصادی</span>
                <strong class="text-end" dir="ltr">{{ $entity->national_or_economic_id ?: '—' }}</strong>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-start gap-3 px-0 py-2">
                <span class="text-muted">موبایل</span>
                <code class="text-end mb-0" dir="ltr">{{ $entity->mobile ?: '—' }}</code>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-start gap-3 px-0 py-2">
                <span class="text-muted">استان</span>
                <strong class="text-end">
                    {{ $entity->province?->name ?? '—' }}
                    @if($entity->province?->accounting_code)
                    <span class="text-muted fw-normal d-block" dir="ltr">کد {{ $entity->province->accounting_code }}</span>
                    @endif
                </strong>
            </li>
        </ul>
    </div>

    @if($debtAmount !== null && (int) $debtAmount > 0)
    <div class="mb-3">
        <div class="fw-semibold small text-muted mb-2 pb-1 border-bottom">بدهی این برنامه</div>
        <div class="rounded-3 border border-danger border-opacity-25 bg-danger bg-opacity-10 p-3 d-flex justify-content-between align-items-center">
            <span class="text-muted small">مبلغ بدهی</span>
            <span class="fw-bold text-danger">{{ \App\Support\PdfPersian::toPersianDigits(number_format((int) $debtAmount)) }} <span class="fw-normal small">ریال</span></span>
        </div>
        @if($debtDescription)
        <div class="small text-muted mt-2 px-1">{{ $debtDescription }}</div>
        @endif
    </div>
    @elseif($debtDescription)
    <div class="mb-3">
        <div class="fw-semibold small text-muted mb-2 pb-1 border-bottom">توضیحات</div>
        <div class="small text-muted">{{ $debtDescription }}</div>
    </div>
    @endif

    @if($accountingDetails)
    <div class="mb-3">
        <div class="fw-semibold small text-muted mb-2 pb-1 border-bottom">کدینگ حسابداری</div>
        <div class="border rounded-3 overflow-hidden">
            <div class="text-center py-3 px-2 bg-{{ $accent }} bg-opacity-10 border-bottom">
                <div class="text-muted small mb-1">{{ $accountingDetails['entity_type_label'] }}</div>
                <div class="fs-4 fw-bold mb-0" dir="ltr" style="letter-spacing:.06em">{{ $accountingDetails['code'] }}</div>
            </div>
            <div class="row g-0 text-center small">
                <div class="col-6 col-md-3 border-end border-bottom p-2">
                    <div class="text-muted">کد استان</div>
                    <div class="fw-semibold" dir="ltr">{{ $accountingDetails['province_code'] ?? '—' }}</div>
                </div>
                <div class="col-6 col-md-3 border-end border-bottom p-2">
                    <div class="text-muted">استان</div>
                    <div class="fw-semibold">{{ $accountingDetails['province_name'] ?? '—' }}</div>
                </div>
                <div class="col-6 col-md-3 border-end border-bottom p-2">
                    <div class="text-muted">شاخص</div>
                    <div class="fw-semibold" dir="ltr">{{ $accountingDetails['indicator'] ?? '—' }}</div>
                    @if($accountingDetails['indicator_label'])
                    <div class="text-muted" style="font-size:.72rem">{{ $accountingDetails['indicator_label'] }}</div>
                    @endif
                </div>
                <div class="col-6 col-md-3 border-bottom p-2">
                    <div class="text-muted">شمارنده</div>
                    <div class="fw-semibold" dir="ltr">
                        {{ isset($accountingDetails['counter']) ? str_pad((string) $accountingDetails['counter'], 2, '0', STR_PAD_LEFT) : '—' }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($documentPaths !== [])
    <div class="mb-3">
        <div class="fw-semibold small text-muted mb-2 pb-1 border-bottom">
            <i class="bi bi-paperclip me-1"></i>مدارک ضمیمه ({{ count($documentPaths) }})
        </div>
        <x-program.document-list :paths="$documentPaths" :compact="true" />
    </div>
    @endif

    @if($entity->user)
    <div class="mb-0">
        <div class="fw-semibold small text-muted mb-2 pb-1 border-bottom">کاربر مرتبط در سامانه</div>
        <div class="d-flex justify-content-between align-items-center gap-2 rounded-3 border p-3">
            <div class="min-w-0">
                <div class="fw-semibold text-truncate">{{ $entity->user->name }}</div>
                @if($entity->user->mobile)
                <div class="text-muted small" dir="ltr">{{ $entity->user->mobile }}</div>
                @endif
            </div>
            @if($userShowRoute)
            <a wire:navigate href="{{ route($userShowRoute, $entity->user) }}" class="btn btn-sm btn-outline-secondary flex-shrink-0">
                <i class="bi bi-person me-1"></i>پروفایل
            </a>
            @endif
        </div>
    </div>
    @endif
</div>

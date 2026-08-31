@php
    use App\Support\ProgramDocumentPaths;

    $beneficiaryCosts = $program->beneficiaryCosts;
    $totalDebt = (int) $beneficiaryCosts->sum('debt_amount');
    $tabId = 'pg-beneficiaries-tabs-' . $program->id;
@endphp

@if($beneficiaryCosts->isEmpty())
<div class="text-muted text-center py-3">ذینفعی برای این برنامه ثبت نشده است.</div>
@else
@if($totalDebt > 0)
<div class="rounded-3 border bg-light p-3 mb-3 d-flex justify-content-between align-items-center">
    <span class="text-muted small"><i class="bi bi-cash-stack me-1"></i>جمع بدهی ذینفعان</span>
    <strong class="text-danger">{{ \App\Support\PdfPersian::toPersianDigits(number_format($totalDebt)) }} ریال</strong>
</div>
@endif

@if($beneficiaryCosts->count() > 1)
<ul class="nav nav-pills nav-fill gap-1 mb-3 flex-nowrap overflow-auto pb-1" role="tablist">
    @foreach($beneficiaryCosts as $cost)
    @php $beneficiary = $cost->beneficiary; @endphp
    @if($beneficiary)
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $loop->first ? 'active' : '' }} small text-truncate"
                id="{{ $tabId }}-tab-{{ $cost->id }}"
                data-bs-toggle="tab"
                data-bs-target="#{{ $tabId }}-pane-{{ $cost->id }}"
                type="button"
                role="tab"
                style="max-width:180px">
            {{ $beneficiary->name }}
        </button>
    </li>
    @endif
    @endforeach
</ul>
@endif

<div class="tab-content">
    @foreach($beneficiaryCosts as $cost)
    @php
        $beneficiary = $cost->beneficiary;
        $docCount = ProgramDocumentPaths::count($cost->documents);
    @endphp
    @if($beneficiary)
    <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}"
         id="{{ $tabId }}-pane-{{ $cost->id }}"
         role="tabpanel">
        @if($beneficiaryCosts->count() === 1)
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="fw-semibold">{{ $beneficiary->name }}</span>
            <span class="badge bg-secondary-subtle text-secondary border" dir="ltr">{{ $beneficiary->beneficiary_code }}</span>
            @if((int) $cost->debt_amount > 0)
            <span class="badge bg-danger-subtle text-danger border">{{ \App\Support\PdfPersian::toPersianDigits(number_format((int) $cost->debt_amount)) }} ریال</span>
            @endif
            @if($docCount > 0)
            <span class="badge bg-success-subtle text-success border"><i class="bi bi-paperclip me-1"></i>{{ $docCount }} مدرک</span>
            @endif
        </div>
        @endif

        @include('components.program.show-details._entity-detail-body', [
            'entity' => $beneficiary,
            'type' => 'beneficiary',
            'panel' => $panel,
            'debtAmount' => $cost->debt_amount,
            'debtDescription' => $cost->description,
            'documents' => $cost->documents ?? [],
        ])
    </div>
    @endif
    @endforeach
</div>
@endif

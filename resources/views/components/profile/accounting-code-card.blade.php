@props([
    'user' => null,
    'details' => null,
    'variant' => 'card',
])

@php
    $details = $details ?? ($user?->accountingProfileDetails());
@endphp

@if($details)
    @if($variant === 'inline')
        <div {{ $attributes->class(['small']) }}>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge bg-dark-subtle text-dark border">{{ $details['entity_type_label'] }}</span>
                <strong dir="ltr">{{ $details['code'] }}</strong>
                @if($details['province_name'])
                    <span class="text-muted">· {{ $details['province_name'] }} ({{ $details['province_code'] }})</span>
                @endif
            </div>
        </div>
    @elseif($variant === 'compact')
        <div {{ $attributes->class(['border rounded p-3 bg-light']) }}>
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <span class="badge text-bg-primary">{{ $details['entity_type_label'] }}</span>
                <span class="fw-bold fs-5" dir="ltr">{{ $details['code'] }}</span>
            </div>
            <div class="row g-2 small">
                <div class="col-6">
                    <div class="text-muted">کد استان</div>
                    <div class="fw-semibold" dir="ltr">{{ $details['province_code'] ?? '—' }}</div>
                </div>
                <div class="col-6">
                    <div class="text-muted">استان</div>
                    <div class="fw-semibold">{{ $details['province_name'] ?? '—' }}</div>
                </div>
                <div class="col-6">
                    <div class="text-muted">شاخص</div>
                    <div class="fw-semibold" dir="ltr">
                        {{ $details['indicator'] ?? '—' }}
                        @if($details['indicator_label'])
                            <span class="text-muted fw-normal">({{ $details['indicator_label'] }})</span>
                        @endif
                    </div>
                </div>
                <div class="col-6">
                    <div class="text-muted">شمارنده</div>
                    <div class="fw-semibold" dir="ltr">{{ isset($details['counter']) ? str_pad((string) $details['counter'], 2, '0', STR_PAD_LEFT) : '—' }}</div>
                </div>
            </div>
        </div>
    @else
        <div {{ $attributes->class(['card shadow-sm border-0']) }} style="border-top: 4px solid #0d6efd !important;">
            <div class="card-header bg-white fw-semibold small d-flex align-items-center gap-2">
                <i class="bi bi-upc-scan text-primary"></i>
                کدینگ حسابداری
            </div>
            <div class="card-body">
                <div class="text-center mb-3 pb-3 border-bottom">
                    <div class="text-muted small mb-1">{{ $details['entity_type_label'] }}</div>
                    <div class="display-6 fw-bold text-primary mb-0" dir="ltr" style="letter-spacing: .08em;">{{ $details['code'] }}</div>
                </div>
                <div class="row g-3 small">
                    <div class="col-6">
                        <div class="text-muted">کد استان</div>
                        <div class="fw-semibold" dir="ltr">{{ $details['province_code'] ?? '—' }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">نام استان</div>
                        <div class="fw-semibold">{{ $details['province_name'] ?? '—' }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">عدد شاخص</div>
                        <div class="fw-semibold" dir="ltr">
                            {{ $details['indicator'] ?? '—' }}
                            @if($details['indicator_label'])
                                <span class="text-muted fw-normal d-block" style="font-size: .85em;">{{ $details['indicator_label'] }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted">شمارنده</div>
                        <div class="fw-semibold" dir="ltr">{{ isset($details['counter']) ? str_pad((string) $details['counter'], 2, '0', STR_PAD_LEFT) : '—' }}</div>
                    </div>
                </div>
                <div class="alert alert-light border small mb-0 mt-3 py-2">
                    <i class="bi bi-info-circle me-1 text-muted"></i>
                    ساختار: <span dir="ltr">{{ $details['province_code'] ?? '???' }}{{ $details['indicator'] ?? '?' }}{{ isset($details['counter']) ? str_pad((string) $details['counter'], 2, '0', STR_PAD_LEFT) : '??' }}</span>
                    <span class="text-muted"> = استان + شاخص + شمارنده</span>
                </div>
            </div>
        </div>
    @endif
@endif

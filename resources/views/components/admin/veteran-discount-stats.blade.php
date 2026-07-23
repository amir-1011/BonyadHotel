@props([
    'groups',
    'totals',
])

@php
    $icons = [
        'veteran_70_spouses'          => 'award-fill',
        'veteran_50_69_dependents'    => 'patch-check-fill',
        'veteran_25_49_dependents'    => 'shield-fill',
        'martyr_children'             => 'heart-fill',
        'martyr_parents_dependents'   => 'people-fill',
        'martyr_spouse_dependents'    => 'person-heart',
        'freed_prisoner_dependents'   => 'flag-fill',
    ];
@endphp

<div class="row g-4 mb-4">
    @foreach($groups as $group)
        @php
            $active = (int) $group['nights'] > 0 || (int) $group['discount_amount'] > 0;
            $icon   = $icons[$group['key']] ?? 'shield-check';
        @endphp
        <div class="col-6 col-lg-4 col-xl-3">
            <a href="{{ route('admin.bookings.index', ['status' => 'confirmed', 'veteran_type' => $group['key']]) }}"
               wire:navigate
               class="text-decoration-none">
                <div class="ta-metric h-100">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="ta-metric__icon"><i class="bi bi-{{ $icon }}"></i></div>
                        <span class="ta-trend up">{{ $group['discount_pct'] }}٪ اقامت</span>
                    </div>
                    <div class="ta-metric__label">{{ $group['label'] }}</div>
                    <div class="ta-metric__value" style="font-size:1.15rem">
                        @if($active)
                            {{ number_format($group['discount_amount']) }}
                            <small class="fs-6 fw-normal text-muted">تخفیف</small>
                        @else
                            —
                        @endif
                    </div>
                    @if($active)
                        <div class="text-muted small mt-1">
                            {{ number_format($group['nights']) }} شب
                            @if((int) $group['bookings_count'] > 0)
                                · {{ number_format($group['bookings_count']) }} رزرو
                            @endif
                        </div>
                    @endif
                </div>
            </a>
        </div>
    @endforeach
</div>

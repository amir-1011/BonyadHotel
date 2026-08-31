{{-- ویرایشگر پله‌های تخفیف اقامت (رایگان، مبلغ ثابت، درصد) --}}
@props([
    'groupIndex',
    'group' => [],
])

@php
    $base = "groups.{$groupIndex}";
    $useTiered = (bool) ($group['use_tiered_accommodation_discount'] ?? false);
    $tiers = $group['accommodation_discount_tiers'] ?? [];
@endphp

<div style="min-width:{{ $useTiered ? '220px' : '90px' }}">
    <label class="d-flex align-items-center gap-1 mb-1" style="font-size:.7rem">
        <input type="checkbox"
               wire:model.live="{{ $base }}.use_tiered_accommodation_discount"
               class="form-check-input m-0">
        پله‌ای
    </label>

    @if($useTiered)
        <div class="text-start border rounded bg-white p-2" style="font-size:.72rem">
            <div class="text-muted mb-1" style="font-size:.65rem">پله‌های تخفیف اقامت (رایگان، مبلغ ثابت، درصد)</div>
            @foreach($tiers as $ti => $tier)
            <div class="border-bottom pb-1 mb-1" wire:key="acc-tier-{{ $groupIndex }}-{{ $ti }}">
                <select wire:model.live="{{ $base }}.accommodation_discount_tiers.{{ $ti }}.type"
                        class="form-select form-select-sm mb-1">
                    <option value="free">رایگان</option>
                    <option value="fixed_pay">مبلغ ثابت</option>
                    <option value="percentage">درصد تخفیف</option>
                </select>
                @if(($tier['type'] ?? '') !== 'percentage' || ($ti < count($tiers) - 1))
                @if($ti < count($tiers) - 1)
                <input type="number"
                       wire:model="{{ $base }}.accommodation_discount_tiers.{{ $ti }}.night_count"
                       min="1" max="365"
                       class="form-control form-control-sm mb-1"
                       placeholder="تعداد شب">
                @else
                <div class="text-muted mb-1" style="font-size:.65rem">از این پله به بعد</div>
                @endif
                @endif
                @if(($tier['type'] ?? '') === 'fixed_pay')
                <x-money-input wire:model="{{ $base }}.accommodation_discount_tiers.{{ $ti }}.pay_amount"
                               min="0" class="form-control form-control-sm mb-1"
                               placeholder="مبلغ پرداختی" />
                @endif
                @if(($tier['type'] ?? '') === 'percentage')
                <input type="number"
                       wire:model="{{ $base }}.accommodation_discount_tiers.{{ $ti }}.discount_percentage"
                       min="0" max="100"
                       class="form-control form-control-sm mb-1"
                       placeholder="درصد تخفیف">
                @endif
                <button type="button"
                        wire:click="removeGroupAccommodationTier({{ $groupIndex }}, {{ $ti }})"
                        class="btn btn-xs btn-outline-danger w-100">حذف پله</button>
            </div>
            @endforeach
            <button type="button"
                    wire:click="addGroupAccommodationTier({{ $groupIndex }})"
                    class="btn btn-xs btn-outline-primary w-100">+ پله</button>
        </div>
    @else
        <input type="number"
               wire:model="{{ $base }}.accommodation_discount"
               min="0" max="100"
               class="form-control form-control-sm"
               title="درصد تخفیف ثابت اقامت">
    @endif
</div>

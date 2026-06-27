{{-- سلول ماتریس تخفیف: درصد همیشگی (ساده) یا پله‌ای (پیشرفته) --}}
@props([
    'groupKey',
    'serviceRef',
    'cell' => [],
])

@php
    $base = "discountMatrix.{$groupKey}.{$serviceRef}";
    $useTiered = (bool) ($cell['use_tiered_discount'] ?? false);
    $tiers = $cell['discount_tiers'] ?? [];
@endphp

<div class="text-center" style="min-width:{{ $useTiered ? '180px' : '100px' }}">
    <label class="d-flex align-items-center justify-content-center gap-1 mb-1" style="font-size:.7rem">
        <input type="checkbox"
               wire:model.live="{{ $base }}.use_tiered_discount"
               class="form-check-input m-0">
        پله‌ای
    </label>

    @if($useTiered)
        <div class="text-start border rounded bg-white p-2 mb-1" style="font-size:.72rem">
            <div class="text-muted mb-1" style="font-size:.65rem">جزئیات پله‌ها (رایگان، مبلغ ثابت، درصد)</div>
            @foreach($cell['discount_tiers'] ?? [] as $ti => $tier)
            <div class="border-bottom pb-1 mb-1" wire:key="tier-{{ $groupKey }}-{{ $serviceRef }}-{{ $ti }}">
                <select wire:model.live="{{ $base }}.discount_tiers.{{ $ti }}.type"
                        class="form-select form-select-sm mb-1">
                    <option value="free">رایگان</option>
                    <option value="fixed_pay">مبلغ ثابت</option>
                    <option value="percentage">درصد تخفیف</option>
                </select>
                @if(($tier['type'] ?? '') !== 'percentage' || ($ti < count($tiers) - 1))
                <input type="number"
                       wire:model="{{ $base }}.discount_tiers.{{ $ti }}.session_count"
                       min="1" max="21"
                       class="form-control form-control-sm mb-1"
                       placeholder="تعداد جلسه">
                @endif
                @if(($tier['type'] ?? '') === 'fixed_pay')
                <x-money-input wire:model="{{ $base }}.discount_tiers.{{ $ti }}.pay_amount"
                               min="0" class="form-control form-control-sm mb-1"
                               placeholder="مبلغ پرداختی" />
                @endif
                @if(($tier['type'] ?? '') === 'percentage')
                <input type="number"
                       wire:model="{{ $base }}.discount_tiers.{{ $ti }}.discount_percentage"
                       min="0" max="100"
                       class="form-control form-control-sm mb-1"
                       placeholder="درصد">
                @endif
                <button type="button"
                        wire:click="removeMatrixTier('{{ $groupKey }}', @js($serviceRef), {{ $ti }})"
                        class="btn btn-xs btn-outline-danger w-100">حذف پله</button>
            </div>
            @endforeach
            <button type="button"
                    wire:click="addMatrixTier('{{ $groupKey }}', @js($serviceRef))"
                    class="btn btn-xs btn-outline-primary w-100">+ پله</button>
        </div>
    @else
        <label class="text-muted d-block mb-1" style="font-size:.65rem">درصد تخفیف همیشگی</label>
        <input type="number"
               wire:model="{{ $base }}.discount_percentage"
               min="0" max="100"
               class="form-control form-control-sm"
               style="width:72px;margin:0 auto"
               title="درصد تخفیف ثابت این خدمت برای این گروه">
        <div class="text-muted mt-1" style="font-size:.6rem">٪</div>
    @endif
</div>

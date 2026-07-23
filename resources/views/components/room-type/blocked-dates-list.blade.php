@props([
    'blockedDates',
    'roomType',
    'accommodation',
    'rangeDestroyRouteName',
    'panel' => null,
])

@php
    $panelKey = $panel ?? (str_starts_with($rangeDestroyRouteName ?? '', 'host.') ? 'host' : 'admin');
    $sorted = $blockedDates->sortBy(fn ($bd) => [
        $bd->room_id ?? 0,
        (string) ($bd->reason ?? ''),
        $bd->date->toDateString(),
    ])->values();

    $roomRanges = [];
    $current = null;

    foreach ($sorted as $bd) {
        $dateStr = $bd->date->toDateString();
        $fingerprint = ($bd->room_id ?? 0) . '::' . (string) ($bd->reason ?? '');

        if ($current !== null && $current['fingerprint'] === $fingerprint) {
            $nextExpected = (new \DateTime($current['date_to']))->modify('+1 day')->format('Y-m-d');
            if ($dateStr === $nextExpected) {
                $current['date_to'] = $dateStr;
                $current['days_count']++;
                $current['items'][] = $bd;
                continue;
            }
        }

        if ($current !== null) {
            $roomRanges[] = $current;
        }

        $current = [
            'fingerprint' => $fingerprint,
            'room_id'     => $bd->room_id,
            'room_label'  => $bd->roomLabel(),
            'reason'      => $bd->reason,
            'date_from'   => $dateStr,
            'date_to'     => $dateStr,
            'days_count'  => 1,
            'items'       => [$bd],
        ];
    }

    if ($current !== null) {
        $roomRanges[] = $current;
    }

    $merged = [];
    foreach ($roomRanges as $range) {
        $mergeKey = $range['date_from'] . '::' . $range['date_to'] . '::' . (string) ($range['reason'] ?? '');
        $existingIndex = null;

        foreach ($merged as $idx => $group) {
            if ($group['merge_key'] === $mergeKey) {
                $existingIndex = $idx;
                break;
            }
        }

        if ($existingIndex !== null) {
            $merged[$existingIndex]['room_labels'][] = $range['room_label'];
            $merged[$existingIndex]['room_ids'][] = $range['room_id'];
            $merged[$existingIndex]['items'] = array_merge($merged[$existingIndex]['items'], $range['items']);
            continue;
        }

        $merged[] = [
            'merge_key'   => $mergeKey,
            'date_from'   => $range['date_from'],
            'date_to'     => $range['date_to'],
            'days_count'  => $range['days_count'],
            'reason'      => $range['reason'],
            'room_labels' => [$range['room_label']],
            'room_ids'    => [$range['room_id']],
            'items'       => $range['items'],
        ];
    }

    $blockedRanges = array_reverse($merged);
    $rangeCount = count($blockedRanges);
@endphp

<div class="card shadow-sm border-0 rounded-4 mt-3">
    <div class="card-header bg-white border-bottom rounded-top-4">
        <h6 class="mb-0 fw-bold">
            <i class="bi bi-list-ul me-2 text-danger"></i>
            تاریخ‌های مسدود شده
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1">{{ $rangeCount }}</span>
        </h6>
    </div>
    <div class="card-body p-3">
        @if($rangeCount === 0)
        <div class="text-center py-4 text-muted small">
            <i class="bi bi-calendar-check fs-3 d-block mb-2 opacity-50"></i>
            هیچ تاریخی مسدود نشده است.
        </div>
        @else
        <div class="blocked-bd-list">
            @foreach($blockedRanges as $range)
            @php
                $fromJ = \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($range['date_from']))->format('Y/m/d');
                $toJ = \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($range['date_to']))->format('Y/m/d');
                $isSingleDay = $range['date_from'] === $range['date_to'];
                $roomLabels = array_values(array_unique($range['room_labels']));
                $roomIds = collect($range['room_ids'])->unique()->values();
                $deleteMessage = $isSingleDay
                    ? 'این تاریخ از مسدودی حذف شود؟'
                    : 'کل این بازه (' . $range['days_count'] . ' روز) حذف شود؟';
                if (count($roomLabels) > 1) {
                    $deleteMessage = $isSingleDay
                        ? 'مسدودی ' . count($roomLabels) . ' اتاق در این تاریخ حذف شود؟'
                        : 'مسدودی ' . count($roomLabels) . ' اتاق در این بازه (' . $range['days_count'] . ' روز) حذف شود؟';
                }
            @endphp
            <div class="blocked-bd-card">
                <div class="blocked-bd-card__head">
                    <div>
                        @if($isSingleDay)
                        <div class="blocked-bd-card__date">{{ $fromJ }}</div>
                        <div class="blocked-bd-card__greg text-muted">{{ $range['date_from'] }}</div>
                        @else
                        <div class="blocked-bd-card__date">{{ $fromJ }} <span class="text-muted fw-normal">تا</span> {{ $toJ }}</div>
                        <div class="blocked-bd-card__greg text-muted">{{ $range['date_from'] }} — {{ $range['date_to'] }}</div>
                        <div class="blocked-bd-card__days">
                            <span class="badge bg-light text-dark border">{{ $range['days_count'] }} روز</span>
                        </div>
                        @endif
                    </div>
                    <x-host.can page="room-types.blocked-dates" action="delete" :panel="$panelKey">
                    <form action="{{ route($rangeDestroyRouteName, [$accommodation, $roomType]) }}"
                          method="POST" class="ms-2">
                        @csrf @method('DELETE')
                        <input type="hidden" name="date_from" value="{{ $range['date_from'] }}">
                        <input type="hidden" name="date_to" value="{{ $range['date_to'] }}">
                        <input type="hidden" name="reason" value="{{ $range['reason'] }}">
                        @foreach($roomIds as $roomId)
                        <input type="hidden" name="room_ids[]" value="{{ $roomId }}">
                        @endforeach
                        <button type="submit" data-swal-confirm="{{ $deleteMessage }}"
                                class="btn btn-sm btn-outline-danger" title="حذف بازه">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                    </x-host.can>
                </div>

                <div class="blocked-bd-card__rooms">
                    @foreach($roomLabels as $label)
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">{{ $label }}</span>
                    @endforeach
                </div>

                @if($range['reason'])
                <div class="blocked-bd-card__reason">
                    <i class="bi bi-chat-left-text me-1"></i>{{ $range['reason'] }}
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

@once
@push('styles')
<style>
.blocked-bd-list {
    display: flex;
    flex-direction: column;
    gap: .75rem;
    max-height: 28rem;
    overflow-y: auto;
    padding-inline-end: 2px;
}
.blocked-bd-card {
    border: 1px solid rgba(var(--bs-danger-rgb), .25);
    border-radius: .75rem;
    padding: .75rem .85rem;
    background: rgba(var(--bs-danger-rgb), .03);
    transition: border-color .15s, box-shadow .15s;
}
.blocked-bd-card:hover {
    border-color: rgba(var(--bs-danger-rgb), .45);
    box-shadow: 0 4px 14px rgba(15, 23, 42, .06);
}
.blocked-bd-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .5rem;
    margin-bottom: .5rem;
}
.blocked-bd-card__date {
    font-weight: 700;
    font-size: .95rem;
    line-height: 1.35;
}
.blocked-bd-card__greg {
    font-size: .7rem;
    margin-top: 2px;
    direction: ltr;
    text-align: right;
}
.blocked-bd-card__days { margin-top: .35rem; }
.blocked-bd-card__rooms {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
    margin-bottom: .25rem;
}
.blocked-bd-card__reason {
    font-size: .75rem;
    color: var(--bs-secondary-color);
    border-top: 1px dashed var(--bs-border-color);
    padding-top: .45rem;
    margin-top: .25rem;
}
</style>
@endpush
@endonce

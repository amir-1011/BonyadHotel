@props([
    'hosts',
    'month',
    'month_label',
    'prev_month_label',
    'check_in_from',
    'check_in_to',
    'month_options',
    'all_time' => false,
    'title' => 'برترین میزبان‌ها',
])

@php
    $podium = $hosts->take(3);
    $leader = $hosts->first();

    $medalColors = ['#f59e0b', '#94a3b8', '#cd7f32'];
    $medalIcons  = ['trophy-fill', 'award-fill', 'award'];

    $initials = function (?string $name): string {
        $name = trim((string) $name);
        if ($name === '') {
            return '؟';
        }
        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) >= 2) {
            return mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1);
        }

        return mb_substr($name, 0, 2);
    };

    $avatarHue = fn (int $id): int => ($id * 47) % 360;

    $bookingLink = fn (object $host) => route('admin.bookings.index', array_filter([
        'reserver_id'   => $host->id,
        'check_in_from' => $all_time ? null : $check_in_from,
        'check_in_to'   => $all_time ? null : $check_in_to,
    ], fn ($v) => $v !== null && $v !== ''));

    $growthLabel = function (?float $pct): array {
        if ($pct === null) {
            return ['class' => 'new', 'text' => 'جدید', 'icon' => 'stars'];
        }
        if ($pct > 0) {
            return ['class' => 'up', 'text' => abs($pct) . '٪', 'icon' => 'arrow-up-short'];
        }
        if ($pct < 0) {
            return ['class' => 'down', 'text' => abs($pct) . '٪', 'icon' => 'arrow-down-short'];
        }

        return ['class' => 'neutral', 'text' => '۰٪', 'icon' => 'dash'];
    };
@endphp

<div class="ta-card host-leaderboard">
    <div class="ta-card__head flex-wrap gap-3">
        <div>
            <h2 class="ta-card__title">
                <i class="bi bi-trophy-fill text-warning me-2"></i>{{ $title }}
            </h2>
            <div class="ta-card__sub">
                @if($all_time)
                    رتبه‌بندی کل دوره بر اساس فروش رزروهای تأیید‌شده
                @else
                    رتبه‌بندی ماهانه بر اساس فروش رزروهای تأیید‌شده — {{ $month_label }}
                @endif
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="btn-group btn-group-sm" role="group">
                <button type="button"
                        wire:click="showAllTime"
                        class="btn {{ $all_time ? 'btn-primary' : 'btn-outline-secondary' }}">
                    <i class="bi bi-clock-history me-1"></i>همه تاریخ‌ها
                </button>
                <button type="button"
                        wire:click="showMonthly"
                        class="btn {{ !$all_time ? 'btn-primary' : 'btn-outline-secondary' }}">
                    <i class="bi bi-calendar3 me-1"></i>ماهانه
                </button>
            </div>
            @if(!$all_time)
            <label class="host-leaderboard__month-picker mb-0">
                <select wire:model.live="month" class="form-select form-select-sm" style="min-width:9.5rem">
                    @foreach($month_options as $opt)
                        <option value="{{ $opt['value'] }}">{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            </label>
            @endif
            @if($leader)
                <div class="host-leaderboard__leader-badge text-end">
                    <div class="text-muted" style="font-size:.72rem">میزبان اول{{ $all_time ? ' (کل دوره)' : ' ' . $month_label }}</div>
                    <div class="fw-bold" style="font-size:.88rem;color:#101828">{{ $leader->name ?? 'بدون نام' }}</div>
                    <div class="text-primary fw-semibold tabular-nums" style="font-size:.8rem">
                        {{ number_format($leader->revenue) }} تومان
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="ta-card__body">
        @if($hosts->isEmpty())
            <div class="text-center text-muted py-5">
                <i class="bi bi-calendar-x fs-1 opacity-25 d-block mb-2"></i>
                در {{ $month_label }} رزرو تأیید‌شده‌ای برای میزبان‌ها ثبت نشده است
            </div>
        @else
            @if($podium->count() >= 2)
                <div class="host-leaderboard__podium mb-4">
                    @foreach([1, 0, 2] as $slot)
                        @php
                            $host = $podium->get($slot);
                            if (!$host) continue;
                            $rank = $slot + 1;
                            $h    = $rank === 1 ? 100 : ($rank === 2 ? 72 : 56);
                            $growth = $growthLabel($host->revenue_growth_pct);
                        @endphp
                        <a wire:navigate href="{{ $bookingLink($host) }}"
                           class="host-leaderboard__podium-item host-leaderboard__podium-item--{{ $rank }} text-decoration-none">
                            <div class="host-leaderboard__podium-rank" style="color:{{ $medalColors[$slot] }}">
                                <i class="bi bi-{{ $medalIcons[$slot] }}"></i>
                            </div>
                            <div class="host-leaderboard__avatar host-leaderboard__avatar--lg"
                                 style="--avatar-hue:{{ $avatarHue($host->id) }}">
                                {{ $initials($host->name) }}
                            </div>
                            <div class="host-leaderboard__podium-name">{{ Str::limit($host->name ?? 'بدون نام', 18) }}</div>
                            <div class="host-leaderboard__podium-score tabular-nums">{{ number_format($host->revenue) }}</div>
                            <div class="host-leaderboard__podium-label">تومان · {{ number_format($host->bookings_count) }} رزرو</div>
                            @if(!$all_time)
                            <span class="host-leaderboard__delta host-leaderboard__delta--sm {{ $growth['class'] }}">
                                <i class="bi bi-{{ $growth['icon'] }}"></i>{{ $growth['text'] }}
                                <small>نسبت به {{ $prev_month_label }}</small>
                            </span>
                            @endif
                            <div class="host-leaderboard__podium-bar" style="height:{{ $h }}px;background:{{ $medalColors[$slot] }}"></div>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="host-leaderboard__list">
                @foreach($hosts as $i => $host)
                    @php
                        $rank   = $i + 1;
                        $growth = $growthLabel($host->revenue_growth_pct);
                    @endphp
                    <a wire:navigate href="{{ $bookingLink($host) }}"
                       class="host-leaderboard__row {{ $rank === 1 ? 'host-leaderboard__row--first' : '' }}{{ $all_time ? ' host-leaderboard__row--all-time' : '' }}">
                        <span class="host-leaderboard__rank tabular-nums">{{ $rank }}</span>
                        <span class="host-leaderboard__avatar" style="--avatar-hue:{{ $avatarHue($host->id) }}">
                            {{ $initials($host->name) }}
                        </span>
                        <span class="host-leaderboard__info">
                            <span class="host-leaderboard__name">{{ $host->name ?? 'بدون نام' }}</span>
                            @if(!empty($host->accommodations))
                                <span class="host-leaderboard__accs">
                                    @foreach($host->accommodations as $accName)
                                        <span class="host-leaderboard__acc-chip">{{ $accName }}</span>
                                    @endforeach
                                </span>
                            @else
                                <span class="host-leaderboard__meta text-muted">بدون اقامتگاه ثبت‌شده</span>
                            @endif
                        </span>
                        <span class="host-leaderboard__stats">
                            <span class="host-leaderboard__revenue tabular-nums">
                                {{ number_format($host->revenue) }}
                                <small>تومان</small>
                            </span>
                            <span class="host-leaderboard__bookings tabular-nums">
                                {{ number_format($host->bookings_count) }}
                                <small>رزرو</small>
                            </span>
                        </span>
                        @if(!$all_time)
                        <span class="host-leaderboard__compare">
                            <span class="host-leaderboard__delta {{ $growth['class'] }}">
                                <i class="bi bi-{{ $growth['icon'] }}"></i>{{ $growth['text'] }}
                            </span>
                            <span class="host-leaderboard__compare-sub">
                                @if($host->bookings_delta > 0)
                                    <span class="text-success">+{{ $host->bookings_delta }} رزرو</span>
                                @elseif($host->bookings_delta < 0)
                                    <span class="text-danger">{{ $host->bookings_delta }} رزرو</span>
                                @else
                                    <span class="text-muted">بدون تغییر رزرو</span>
                                @endif
                                <span class="text-muted">· {{ $prev_month_label }}</span>
                            </span>
                        </span>
                        @endif
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Styles must live inside the Livewire root element so deferred loads keep them. --}}
    <style>
        .host-leaderboard .tabular-nums { font-variant-numeric: tabular-nums; }
        .host-leaderboard__month-picker {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            font-size: .82rem;
            color: #667085;
        }
        .host-leaderboard__leader-badge {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border: 1px solid #fde68a;
            border-radius: 12px;
            padding: .5rem .85rem;
            min-width: 9rem;
        }
        .host-leaderboard__podium {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 1rem;
            padding: .5rem 0 0;
        }
        .host-leaderboard__podium-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 0 0 8.5rem;
            color: inherit;
            transition: transform .15s ease;
        }
        .host-leaderboard__podium-item:hover { transform: translateY(-3px); }
        .host-leaderboard__podium-item--1 { order: 2; }
        .host-leaderboard__podium-item--2 { order: 1; }
        .host-leaderboard__podium-item--3 { order: 3; }
        .host-leaderboard__podium-rank { font-size: 1.1rem; margin-bottom: .35rem; }
        .host-leaderboard__avatar {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .72rem;
            font-weight: 700;
            color: #fff;
            background: hsl(var(--avatar-hue, 220) 65% 48%);
            flex-shrink: 0;
        }
        .host-leaderboard__avatar--lg {
            width: 3rem;
            height: 3rem;
            font-size: .95rem;
            box-shadow: 0 4px 14px rgba(16, 24, 40, .12);
        }
        .host-leaderboard__podium-name {
            font-size: .8rem;
            font-weight: 600;
            color: #101828;
            margin-top: .45rem;
            text-align: center;
            line-height: 1.3;
        }
        .host-leaderboard__podium-score {
            font-size: 1rem;
            font-weight: 800;
            color: #465fff;
            line-height: 1.1;
        }
        .host-leaderboard__podium-label {
            font-size: .68rem;
            color: #667085;
            margin-bottom: .35rem;
        }
        .host-leaderboard__podium-bar {
            width: 100%;
            border-radius: 10px 10px 4px 4px;
            opacity: .85;
            margin-top: .35rem;
        }
        .host-leaderboard__list {
            display: flex;
            flex-direction: column;
            gap: .45rem;
        }
        .host-leaderboard__row {
            display: grid;
            grid-template-columns: 2rem 2rem 1fr auto 6.5rem;
            align-items: center;
            gap: .65rem;
            padding: .7rem .85rem;
            border-radius: 12px;
            border: 1px solid #f2f4f7;
            background: #fff;
            color: inherit;
            text-decoration: none;
            transition: background .15s, border-color .15s, box-shadow .15s;
        }
        .host-leaderboard__row:hover {
            background: #f9fafb;
            border-color: #e4e7ec;
            box-shadow: 0 2px 8px rgba(16, 24, 40, .06);
        }
        .host-leaderboard__row--first {
            background: linear-gradient(90deg, #fffbeb 0%, #fff 45%);
            border-color: #fde68a;
        }
        .host-leaderboard__row--all-time {
            grid-template-columns: 2rem 2rem 1fr auto;
        }
        .host-leaderboard__rank {
            font-size: .82rem;
            font-weight: 700;
            color: #98a2b3;
            text-align: center;
        }
        .host-leaderboard__info { min-width: 0; }
        .host-leaderboard__name {
            display: block;
            font-size: .88rem;
            font-weight: 600;
            color: #101828;
            margin-bottom: .25rem;
        }
        .host-leaderboard__meta { font-size: .72rem; }
        .host-leaderboard__accs {
            display: flex;
            flex-wrap: wrap;
            gap: .3rem;
        }
        .host-leaderboard__acc-chip {
            display: inline-block;
            font-size: .68rem;
            padding: .15rem .45rem;
            border-radius: 99px;
            background: #eef2ff;
            color: #3538cd;
            border: 1px solid #c7d7fe;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .host-leaderboard__stats {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: .15rem;
            min-width: 5.5rem;
        }
        .host-leaderboard__bookings {
            font-size: .78rem;
            font-weight: 600;
            color: #667085;
        }
        .host-leaderboard__bookings small,
        .host-leaderboard__revenue small {
            font-size: .68rem;
            font-weight: 500;
            color: #98a2b3;
            margin-right: .15rem;
        }
        .host-leaderboard__revenue {
            font-size: .92rem;
            font-weight: 700;
            color: #465fff;
        }
        .host-leaderboard__compare {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: .15rem;
        }
        .host-leaderboard__compare-sub {
            font-size: .65rem;
            line-height: 1.3;
            text-align: left;
        }
        .host-leaderboard__delta {
            font-size: .72rem;
            font-weight: 700;
            padding: .2rem .5rem;
            border-radius: 99px;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: .15rem;
        }
        .host-leaderboard__delta--sm {
            font-size: .65rem;
            padding: .15rem .4rem;
            margin-bottom: .25rem;
            flex-direction: column;
            line-height: 1.2;
            text-align: center;
        }
        .host-leaderboard__delta--sm small {
            font-size: .58rem;
            font-weight: 500;
            opacity: .85;
        }
        .host-leaderboard__delta.up { background: #ecfdf3; color: #027a48; }
        .host-leaderboard__delta.down { background: #fef3f2; color: #b42318; }
        .host-leaderboard__delta.neutral { background: #f2f4f7; color: #667085; }
        .host-leaderboard__delta.new { background: #eff8ff; color: #175cd3; }
        @media (max-width: 767.98px) {
            .host-leaderboard__row {
                grid-template-columns: 1.75rem 1.75rem 1fr;
                grid-template-rows: auto auto;
            }
            .host-leaderboard__stats,
            .host-leaderboard__compare {
                grid-column: 1 / -1;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding-top: .35rem;
                border-top: 1px dashed #f2f4f7;
            }
            .host-leaderboard__leader-badge { display: none; }
            .host-leaderboard__podium { gap: .5rem; }
            .host-leaderboard__podium-item { flex-basis: 6.5rem; }
        }
    </style>
</div>

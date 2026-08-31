@php
    $panel = $panel ?? 'host';
    $isOwner = $panel === 'host' && $item->user_id === auth()->id();
    $canManage = $panel === 'admin' || $isOwner;
    $editRoute = null;
    $editPage = null;
    if ($panel === 'admin') {
        $editRoute = $type === 'surplus'
            ? route('admin.facility.surplus.edit', $item)
            : route('admin.facility.needed.edit', $item);
    } elseif ($isOwner) {
        $editRoute = $type === 'surplus'
            ? route('host.facility.surplus.edit', $item)
            : route('host.facility.needed.edit', $item);
        $editPage = $type === 'surplus' ? 'facility-surplus.edit' : 'facility-needed.edit';
    }
    $unitVolumeLabel = trim((string) $item->unit_volume);
    $quantityLabel = \App\Support\PdfPersian::toPersianDigits(number_format($item->quantity)) . ' عدد';
    $showQuantityLine = $unitVolumeLabel !== '' || $item->quantity > 1;
    $timeLabel = $item->created_at
        ? \App\Support\PersianRelativeTime::diffForHumans($item->created_at)
        : null;
    $locationLabel = $item->province?->name;
@endphp

<article class="facility-divar-card" wire:key="facility-card-{{ $type }}-{{ $item->id }}">
    <button
        type="button"
        class="facility-divar-card__hit"
        wire:click="openDetail({{ $item->id }})"
        onclick="window.facilityDetailSetOrigin?.(this.closest('.facility-divar-card'))"
        aria-label="مشاهده جزئیات {{ $item->name }}"
    >
        <div class="facility-divar-card__body">
            <div class="facility-divar-card__info">
                <h3 class="facility-divar-card__title">{{ $item->name }}</h3>

                @if($item->brand)
                    <div class="facility-divar-card__desc">{{ $item->brand->name }}</div>
                @elseif($item->category)
                    <div class="facility-divar-card__desc">{{ $item->category->name }}</div>
                @endif

                @if($showQuantityLine)
                    <div class="facility-divar-card__desc">
                        @if($item->quantity > 1)
                            {{ $quantityLabel }}
                            @if($unitVolumeLabel !== '')
                                × {{ $unitVolumeLabel }}
                            @endif
                        @else
                            {{ $unitVolumeLabel }}
                        @endif
                    </div>
                @endif

                <div class="facility-divar-card__bottom">
                    @if($item->category)
                        <span class="facility-divar-card__badge">{{ $item->category->name }}</span>
                    @endif
                </div>

                @if($timeLabel || $locationLabel)
                    <div class="facility-divar-card__foot">
                        @if($timeLabel)
                            <span class="facility-divar-card__foot-item" title="{{ $timeLabel }}">
                                <i class="bi bi-clock" aria-hidden="true"></i>
                                {{ $timeLabel }}
                            </span>
                        @endif
                        @if($locationLabel)
                            <span class="facility-divar-card__foot-item facility-divar-card__foot-item--city" title="{{ $locationLabel }}">
                                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                {{ $locationLabel }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            <div class="facility-divar-card__thumb">
                @if($type === 'surplus' && $item->image_path)
                    <img
                        src="{{ $item->imageUrl() }}"
                        alt=""
                        class="facility-divar-card__image"
                        loading="lazy"
                    >
                @else
                    <div class="facility-divar-card__placeholder" aria-hidden="true">
                        @if($type === 'needed' && $item->category)
                            <span class="facility-divar-card__placeholder-label">{{ $item->category->name }}</span>
                        @else
                            <i class="bi bi-image"></i>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </button>

    @if($canManage)
        <div class="facility-divar-card__owner-bar">
            @if($panel === 'admin')
                <a wire:navigate href="{{ $editRoute }}" class="facility-divar-card__owner-btn" title="ویرایش">
                    <i class="bi bi-pencil"></i>
                </a>
                <button
                    type="button"
                    class="facility-divar-card__owner-btn facility-divar-card__owner-btn--danger"
                    title="حذف"
                    wire:click.stop="destroy({{ $item->id }})"
                    data-swal-confirm="آیا از حذف این مورد مطمئن هستید؟"
                >
                    <i class="bi bi-trash"></i>
                </button>
            @else
                <x-host.can :page="$editPage" action="edit">
                    <a wire:navigate href="{{ $editRoute }}" class="facility-divar-card__owner-btn" title="ویرایش">
                        <i class="bi bi-pencil"></i>
                    </a>
                </x-host.can>
                <x-host.can :page="$editPage" action="delete">
                    <button
                        type="button"
                        class="facility-divar-card__owner-btn facility-divar-card__owner-btn--danger"
                        title="حذف"
                        wire:click.stop="destroy({{ $item->id }})"
                        data-swal-confirm="آیا از حذف این مورد مطمئن هستید؟"
                    >
                        <i class="bi bi-trash"></i>
                    </button>
                </x-host.can>
            @endif
        </div>
    @endif
</article>

@once
    @include('components.facility._listing-styles')
@endonce

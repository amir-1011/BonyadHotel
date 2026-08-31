@php
    $panel = $panel ?? 'host';
    $isOwner = $panel === 'host' && $detailItem->user_id === auth()->id();
    $canManage = $panel === 'admin' || $isOwner;
    $editRoute = null;
    $editPage = null;
    if ($panel === 'admin') {
        $editRoute = $type === 'surplus'
            ? route('admin.facility.surplus.edit', $detailItem)
            : route('admin.facility.needed.edit', $detailItem);
    } elseif ($isOwner) {
        $editRoute = $type === 'surplus'
            ? route('host.facility.surplus.edit', $detailItem)
            : route('host.facility.needed.edit', $detailItem);
        $editPage = $type === 'surplus' ? 'facility-surplus.edit' : 'facility-needed.edit';
    }
    $unitVolumeLabel = trim((string) $detailItem->unit_volume);
    $modalImageUrls = $type === 'surplus' ? $detailItem->imageUrls() : [];
    $quantityLabel = \App\Support\PdfPersian::toPersianDigits(number_format($detailItem->quantity)) . ' عدد';
    $timeLabel = $detailItem->created_at
        ? \App\Support\PersianRelativeTime::diffForHumans($detailItem->created_at)
        : null;

    $detailSpecs = [
        [
            'show' => $detailItem->brand,
            'icon' => 'bi-award',
            'label' => 'برند',
            'value' => $detailItem->brand?->name,
        ],
        [
            'show' => $unitVolumeLabel !== '',
            'icon' => 'bi-box-seam',
            'label' => 'حجم واحد',
            'value' => $unitVolumeLabel,
        ],
        [
            'show' => $detailItem->quantity > 1,
            'icon' => 'bi-layers',
            'label' => 'تعداد',
            'value' => $quantityLabel,
        ],
        [
            'show' => $detailItem->province,
            'icon' => 'bi-geo-alt',
            'label' => 'شهر',
            'value' => $detailItem->province?->name,
        ],
        [
            'show' => $detailItem->expiry_date,
            'icon' => 'bi-calendar-event',
            'label' => 'تاریخ انقضا',
            'value' => $detailItem->expiry_date
                ? \App\Support\PdfPersian::jalali($detailItem->expiry_date)
                : null,
        ],
        [
            'show' => true,
            'icon' => 'bi-person',
            'label' => 'ثبت‌کننده',
            'value' => $detailItem->user?->name ?? 'میزبان',
        ],
        [
            'show' => $timeLabel,
            'icon' => 'bi-clock',
            'label' => 'زمان ثبت',
            'value' => $timeLabel,
        ],
    ];
@endphp

<div
    id="facility-detail-overlay"
    class="facility-detail-overlay"
    data-livewire-id="{{ $this->getId() }}"
    wire:ignore
>
    <button
        type="button"
        class="facility-detail-backdrop"
        aria-label="بستن"
        onclick="window.facilityDetailRequestClose?.()"
    ></button>

    <div class="facility-detail-panel" role="dialog" aria-modal="true" aria-labelledby="facility-detail-title">
        <div class="facility-detail-panel__inner">
            <button
                type="button"
                class="facility-detail-close"
                aria-label="بستن"
                onclick="window.facilityDetailRequestClose?.()"
            >
                <i class="bi bi-x-lg"></i>
            </button>

            <div class="facility-detail-scroll">
                @if(count($modalImageUrls) > 0)
                    <div class="facility-detail-hero facility-detail-carousel" data-facility-carousel>
                        <div class="facility-detail-carousel__viewport">
                            <div class="facility-detail-carousel__track" data-facility-carousel-track>
                                @foreach($modalImageUrls as $imageUrl)
                                    <div class="facility-detail-carousel__slide" data-facility-carousel-slide>
                                        <img
                                            src="{{ $imageUrl }}"
                                            alt="{{ $detailItem->name }}"
                                            class="facility-detail-hero__image"
                                            draggable="false"
                                        >
                                    </div>
                                @endforeach
                            </div>
                            @if(count($modalImageUrls) > 1)
                                <div class="facility-detail-carousel__dots" data-facility-carousel-dots>
                                    @foreach($modalImageUrls as $index => $imageUrl)
                                        <button
                                            type="button"
                                            class="facility-detail-carousel__dot {{ $index === 0 ? 'is-active' : '' }}"
                                            data-facility-carousel-dot="{{ $index }}"
                                            aria-label="اسلاید {{ $index + 1 }}"
                                        ></button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @elseif($type === 'surplus')
                    <div class="facility-detail-hero facility-detail-hero--placeholder">
                        <i class="bi bi-image"></i>
                    </div>
                @endif

                <div class="facility-detail-content">
                    <div class="facility-detail-head-card">
                        @if($detailItem->category)
                            <span class="facility-detail-head-card__badge">{{ $detailItem->category->name }}</span>
                        @endif
                        <h2 id="facility-detail-title" class="facility-detail-head-card__title">{{ $detailItem->name }}</h2>
                    </div>

                    <div class="facility-detail-spec-grid">
                        @foreach($detailSpecs as $spec)
                            @if($spec['show'])
                                <div class="facility-detail-spec-tile">
                                    <span class="facility-detail-spec-tile__icon" aria-hidden="true">
                                        <i class="bi {{ $spec['icon'] }}"></i>
                                    </span>
                                    <div class="facility-detail-spec-tile__text">
                                        <span class="facility-detail-spec-tile__label">{{ $spec['label'] }}</span>
                                        <span class="facility-detail-spec-tile__value">{{ $spec['value'] }}</span>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    @if($detailItem->description)
                        <div class="facility-detail-desc-card">
                            <div class="facility-detail-desc-card__head">
                                <i class="bi bi-card-text" aria-hidden="true"></i>
                                <h3 class="facility-detail-desc-card__title">توضیحات</h3>
                            </div>
                            <p class="facility-detail-desc-card__text">{{ $detailItem->description }}</p>
                        </div>
                    @endif

                    @if($canManage)
                        <div class="facility-detail-actions">
                            @if($panel === 'admin')
                                <a wire:navigate href="{{ $editRoute }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-pencil me-1"></i>ویرایش
                                </a>
                                <button
                                    type="button"
                                    class="btn btn-outline-danger btn-sm"
                                    wire:click="destroy({{ $detailItem->id }})"
                                    data-swal-confirm="آیا از حذف این مورد مطمئن هستید؟"
                                >
                                    <i class="bi bi-trash me-1"></i>حذف
                                </button>
                            @else
                                <x-host.can :page="$editPage" action="edit">
                                    <a wire:navigate href="{{ $editRoute }}" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-pencil me-1"></i>ویرایش
                                    </a>
                                </x-host.can>
                                <x-host.can :page="$editPage" action="delete">
                                    <button
                                        type="button"
                                        class="btn btn-outline-danger btn-sm"
                                        wire:click="destroy({{ $detailItem->id }})"
                                        data-swal-confirm="آیا از حذف این مورد مطمئن هستید؟"
                                    >
                                        <i class="bi bi-trash me-1"></i>حذف
                                    </button>
                                </x-host.can>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <a href="tel:{{ $detailItem->contact_phone }}" class="facility-detail-phone btn btn-primary">
                <i class="bi bi-telephone me-1"></i>
                تماس {{ \App\Support\PdfPersian::toPersianDigits($detailItem->contact_phone) }}
            </a>
        </div>
    </div>
</div>

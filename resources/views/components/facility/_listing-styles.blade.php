@push('styles')
    <style>
        .facility-listing-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.5rem;
        }

        .facility-divar-card {
            position: relative;
            min-width: 0;
            width: auto;
            max-width: none;
            height: 152px;
            background: var(--bs-body-bg);
            border: 1px solid #e4e4e7;
            border-radius: 4px;
            overflow: hidden;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .facility-divar-card:hover {
            border-color: #d4d4d8;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
        }

        [data-bs-theme="dark"] .facility-divar-card {
            border-color: var(--bs-border-color);
        }

        @media (max-width: 991.98px) {
            .facility-listing-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 575.98px) {
            .facility-listing-grid {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        .facility-divar-card__hit {
            display: block;
            width: 100%;
            height: 100%;
            padding: 0;
            border: none;
            background: transparent;
            text-align: inherit;
            color: inherit;
            cursor: pointer;
        }

        .facility-divar-card__hit:focus-visible {
            outline: 2px solid var(--bs-primary);
            outline-offset: -2px;
        }

        .facility-divar-card__body {
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            height: 100%;
        }

        .facility-divar-card__info {
            flex: 1 1 auto;
            min-width: 0;
            height: 100%;
            display: flex;
            flex-direction: column;
            padding: 0.5rem 0.4rem 0.5rem 0.65rem;
            overflow: hidden;
        }

        .facility-divar-card__title {
            margin: 0;
            font-size: 0.9375rem;
            font-weight: 500;
            line-height: 1.4;
            color: var(--bs-body-color);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            word-break: break-word;
        }

        .facility-divar-card__desc {
            margin-top: 0.15rem;
            font-size: 0.8125rem;
            line-height: 1.35;
            color: #71717a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .facility-divar-card__bottom {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            margin-top: auto;
            padding-top: 0.35rem;
            min-width: 0;
        }

        .facility-divar-card__foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.45rem;
            margin-top: 0.2rem;
            min-width: 0;
        }

        .facility-divar-card__foot-item {
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            font-size: 0.6875rem;
            line-height: 1.3;
            color: #71717a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 0;
        }

        .facility-divar-card__foot-item i {
            font-size: 0.72rem;
            flex-shrink: 0;
            opacity: 0.85;
        }

        .facility-divar-card__foot-item--city {
            flex-shrink: 0;
            max-width: 48%;
            color: #52525b;
            font-weight: 500;
        }

        .facility-divar-card__badge {
            flex-shrink: 0;
            font-size: 0.6875rem;
            line-height: 1.3;
            color: #c32e2e;
            white-space: nowrap;
        }

        .facility-divar-card__thumb {
            flex: 0 0 136px;
            width: 136px;
            height: 136px;
            margin: 0.5rem 0.5rem 0.5rem 0;
            align-self: flex-start;
        }

        .facility-divar-card__image,
        .facility-divar-card__placeholder {
            width: 136px;
            height: 136px;
            border-radius: 4px;
            object-fit: cover;
            display: block;
        }

        .facility-divar-card__placeholder {
            background: #f4f4f5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #a1a1aa;
        }

        [data-bs-theme="dark"] .facility-divar-card__placeholder {
            background: var(--bs-secondary-bg);
        }

        .facility-divar-card__placeholder i {
            font-size: 1.5rem;
        }

        .facility-divar-card__placeholder-label {
            font-size: 0.78rem;
            font-weight: 500;
            line-height: 1.45;
            color: #71717a;
            text-align: center;
            padding: 0.35rem;
            word-break: break-word;
        }

        .facility-divar-card__owner-bar {
            position: absolute;
            top: 0.3rem;
            left: 0.3rem;
            z-index: 2;
            display: flex;
            gap: 0.2rem;
        }

        .facility-divar-card__owner-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.45rem;
            height: 1.45rem;
            border: none;
            border-radius: 4px;
            background: rgba(255, 255, 255, 0.94);
            color: #71717a;
            padding: 0;
            line-height: 1;
            text-decoration: none;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
        }

        [data-bs-theme="dark"] .facility-divar-card__owner-btn {
            background: rgba(33, 37, 41, 0.94);
            color: var(--bs-secondary-color);
        }

        .facility-divar-card__owner-btn:hover {
            color: var(--bs-body-color);
        }

        .facility-divar-card__owner-btn--danger:hover {
            color: #c32e2e;
        }

        /* Detail popup — iOS-style expand */
        body.facility-detail-open {
            overflow: hidden;
        }

        .facility-detail-overlay {
            position: fixed;
            inset: 0;
            z-index: 1080;
            display: none;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        .facility-detail-overlay.is-visible {
            display: block;
            pointer-events: auto;
            opacity: 1;
        }

        .facility-detail-overlay.is-closing {
            opacity: 0;
            pointer-events: none;
        }

        .facility-detail-backdrop {
            position: absolute;
            inset: 0;
            border: none;
            background: rgba(15, 15, 20, 0.45);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            cursor: pointer;
            padding: 0;
        }

        .facility-detail-panel {
            position: fixed;
            z-index: 1;
            overflow: hidden;
            background: var(--bs-body-bg);
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.28);
            will-change: top, left, width, height, border-radius;
            transform: none;
        }

        .facility-detail-panel__inner {
            position: relative;
            min-height: 0;
            opacity: 0;
            transition: opacity 0.22s ease 0.12s;
            overflow: hidden;
        }

        .facility-detail-panel.is-fitted .facility-detail-panel__inner {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .facility-detail-panel.is-expanded .facility-detail-panel__inner {
            opacity: 1;
        }

        .facility-detail-panel.is-closing .facility-detail-panel__inner {
            opacity: 0;
            transition: opacity 0.15s ease 0s;
        }

        .facility-detail-close {
            position: absolute;
            top: 0.65rem;
            left: 0.65rem;
            z-index: 3;
            width: 2rem;
            height: 2rem;
            border: none;
            border-radius: 999px;
            background: rgba(0, 0, 0, 0.55);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }

        .facility-detail-scroll {
            flex: 1 1 auto;
            min-height: 0;
            overflow-x: hidden;
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
            padding-bottom: 3.25rem;
        }

        .facility-detail-panel.is-fitted:not(.is-scrollable) .facility-detail-scroll {
            overflow-y: visible;
        }

        .facility-detail-phone {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            transform: none;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.65rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            z-index: 2;
            border-radius: 0 !important;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            border-bottom-left-radius: 0 !important;
            border-bottom-right-radius: 0 !important;
            box-shadow: none;
        }

        .facility-detail-phone:hover {
            color: #fff;
        }

        .facility-detail-scroll::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }

        .facility-detail-hero {
            background: #f4f4f5;
            max-height: 52vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .facility-detail-hero__image {
            width: 100%;
            max-height: 52vh;
            object-fit: contain;
            display: block;
            background: #f4f4f5;
        }

        .facility-detail-carousel .facility-detail-hero__image {
            -webkit-user-drag: none;
            user-drag: none;
            pointer-events: none;
            width: 100%;
            max-width: 100%;
            max-height: 52vh;
            height: auto;
            object-fit: contain;
            object-position: center center;
        }

        .facility-detail-hero--placeholder {
            min-height: 4.5rem;
            color: #a1a1aa;
            font-size: 1.75rem;
        }

        .facility-detail-hero--empty {
            display: none;
        }

        .facility-detail-carousel {
            position: relative;
            display: flex;
            flex-direction: column;
            cursor: grab;
            user-select: none;
            touch-action: pan-y;
            width: 100%;
            align-items: stretch;
            justify-content: flex-start;
        }

        .facility-detail-carousel.is-dragging {
            cursor: grabbing;
        }

        .facility-detail-carousel__viewport {
            position: relative;
            width: 100%;
            max-height: 52vh;
            overflow: hidden;
        }

        .facility-detail-carousel__track {
            display: flex;
            width: 100%;
            will-change: transform;
            direction: ltr;
            align-items: stretch;
        }

        .facility-detail-carousel__slide {
            flex: 0 0 100%;
            width: 100%;
            min-width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            max-height: 52vh;
            min-height: 10rem;
        }

        .facility-detail-carousel__dots {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 2;
            display: flex;
            flex-direction: row;
            direction: ltr;
            justify-content: center;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 0.5rem 0.55rem;
            background: linear-gradient(to top, rgba(0, 0, 0, 0.18), transparent);
            pointer-events: none;
        }

        .facility-detail-carousel__dot {
            pointer-events: auto;
            width: 0.4rem;
            height: 0.4rem;
            border: none;
            border-radius: 999px;
            padding: 0;
            background: rgba(255, 255, 255, 0.72);
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.12);
            transition: width 0.2s ease, background-color 0.2s ease;
        }

        .facility-detail-carousel__dot.is-active {
            width: 1.1rem;
            background: var(--bs-primary);
            box-shadow: none;
        }

        .facility-detail-content {
            padding: 0.85rem 1rem 1.25rem;
        }

        .facility-detail-head-card {
            margin-bottom: 0.75rem;
        }

        .facility-detail-head-card__badge {
            display: inline-block;
            font-size: 0.75rem;
            padding: 0.15rem 0.55rem;
            border-radius: 999px;
            background: var(--bs-secondary-bg);
            color: var(--bs-secondary-color);
            margin-bottom: 0.4rem;
        }

        .facility-detail-head-card__title {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 600;
            line-height: 1.45;
            word-break: break-word;
        }

        .facility-detail-spec-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(8.75rem, 1fr));
            gap: 0.4rem;
            align-items: start;
        }

        .facility-detail-spec-tile {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.4rem 0.5rem;
            border: 1px solid var(--bs-border-color-translucent);
            border-radius: 8px;
            background: var(--bs-body-bg);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            min-width: 0;
            height: auto;
        }

        [data-bs-theme="dark"] .facility-detail-spec-tile {
            background: rgba(255, 255, 255, 0.02);
            box-shadow: none;
        }

        .facility-detail-spec-tile__icon {
            flex-shrink: 0;
            width: 1.5rem;
            height: 1.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: var(--bs-secondary-bg);
            color: var(--bs-primary);
            font-size: 0.8rem;
        }

        [data-bs-theme="dark"] .facility-detail-spec-tile__icon {
            background: rgba(255, 255, 255, 0.06);
        }

        .facility-detail-spec-tile__text {
            flex: 1 1 auto;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 0.05rem;
        }

        .facility-detail-spec-tile__label {
            font-size: 0.68rem;
            line-height: 1.25;
            color: #71717a;
        }

        .facility-detail-spec-tile__value {
            font-size: 0.78rem;
            line-height: 1.35;
            font-weight: 600;
            color: var(--bs-body-color);
            word-break: break-word;
        }

        .facility-detail-desc-card {
            margin-top: 0.75rem;
            padding: 0.75rem;
            border: 1px solid var(--bs-border-color-translucent);
            border-radius: 12px;
            background: var(--bs-body-bg);
        }

        .facility-detail-desc-card__head {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-bottom: 0.45rem;
            color: #71717a;
            font-size: 0.8125rem;
        }

        .facility-detail-desc-card__title {
            margin: 0;
            font-size: 0.8125rem;
            font-weight: 600;
        }

        .facility-detail-desc-card__text {
            margin: 0;
            font-size: 0.875rem;
            line-height: 1.65;
            word-break: break-word;
            color: var(--bs-body-color);
        }

        .facility-detail-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
            justify-content: flex-end;
        }
    </style>
@endpush

@once
    @push('scripts')
        <script type="module" src="{{ Vite::asset('resources/js/facility-detail-modal.js') }}" data-navigate-once></script>
    @endpush
@endonce

@once
<style>
    /*
     * Admin / host panel: keep the shell, topbar, breadcrumbs and dashboard
     * widgets usable on phones without changing backend logic.
     */
    .ta-main,
    .ta-page {
        min-width: 0;
        max-width: 100%;
    }

    .ta-page > * {
        max-width: 100%;
        min-width: 0;
    }

    .ta-topbar {
        min-width: 0;
    }

    .ta-topbar > .d-flex {
        min-width: 0;
    }

    .ta-topbar__meta,
    .ta-topbar__breadcrumb {
        min-width: 0;
        max-width: 100%;
        flex: 1 1 auto;
    }

    .ta-breadcrumb-compact {
        display: none;
        align-items: center;
        gap: 4px;
        min-width: 0;
        max-width: 100%;
    }

    .ta-breadcrumb-back,
    .ta-breadcrumb-more {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        flex-shrink: 0;
        padding: 0;
        border-radius: 8px;
        border: 1px solid var(--ta-border);
        background: #fff;
        color: var(--ta-gray-600);
        box-shadow: var(--ta-shadow-xs);
        line-height: 1;
    }

    .ta-breadcrumb-back:hover,
    .ta-breadcrumb-more:hover {
        background: var(--ta-brand-50);
        border-color: var(--ta-brand-200);
        color: var(--ta-brand-600);
    }

    .ta-breadcrumb-current-label {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ta-card,
    .ta-metric,
    .ta-stat-card {
        max-width: 100%;
        min-width: 0;
    }

    #manual-booking-form .mbf-layout-main,
    #manual-booking-form .mbf-layout-aside {
        min-width: 0;
    }

    #manual-booking-form .mbf-aside-sticky {
        position: sticky;
        top: 80px;
        max-height: calc(100vh - 96px);
        overflow-y: auto;
        padding-bottom: 4px;
    }

    #manual-booking-form .mbf-stepper {
        display: flex;
        flex-direction: column;
    }

    #manual-booking-form .mbf-stepper-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        width: 100%;
        margin: 0;
        padding: 0 0 18px;
        border: 0;
        background: transparent;
        text-align: start;
        color: inherit;
        position: relative;
        appearance: none;
        -webkit-appearance: none;
        font-family: inherit;
    }

    #manual-booking-form .mbf-stepper-item:disabled {
        opacity: 1;
    }

    #manual-booking-form .mbf-stepper-item:last-child {
        padding-bottom: 0;
    }

    #manual-booking-form .mbf-stepper-item:focus-visible {
        outline: 0;
    }

    #manual-booking-form .mbf-stepper-item:focus-visible .mbf-stepper-icon {
        box-shadow: 0 0 0 4px rgba(115, 103, 240, .22);
    }

    #manual-booking-form .mbf-stepper-item.is-done,
    #manual-booking-form .mbf-stepper-item.is-active {
        cursor: pointer;
    }

    #manual-booking-form .mbf-stepper-item.is-pending {
        cursor: default;
    }

    #manual-booking-form .mbf-stepper-track {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        flex-shrink: 0;
        width: 32px;
    }

    #manual-booking-form .mbf-stepper-item:not(:last-child) .mbf-stepper-track::after {
        content: '';
        position: absolute;
        top: 32px;
        bottom: -18px;
        width: 2px;
        border-radius: 2px;
        background: #e9eaec;
    }

    #manual-booking-form .mbf-stepper-item.is-done:not(:last-child) .mbf-stepper-track::after {
        background: #c8eed6;
    }

    #manual-booking-form .mbf-stepper-item.is-done .mbf-stepper-icon {
        background: #28c76f;
        color: #fff;
    }

    #manual-booking-form .mbf-stepper-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        position: relative;
        z-index: 1;
        font-size: .95rem;
        line-height: 1;
    }

    #manual-booking-form .mbf-stepper-item.is-active .mbf-stepper-icon {
        background: #7367f0;
        color: #fff;
        box-shadow: 0 0 0 6px rgba(115, 103, 240, .18);
    }

    #manual-booking-form .mbf-stepper-item.is-pending .mbf-stepper-icon {
        background: #fff;
        color: #b9b9c3;
        border: 1.5px solid #d8d6de;
    }

    #manual-booking-form .mbf-stepper-body {
        display: flex;
        flex-direction: column;
        min-width: 0;
        padding-top: 2px;
    }

    #manual-booking-form .mbf-stepper-kicker {
        font-size: .7rem;
        color: #b9b9c3;
        line-height: 1.2;
        margin-bottom: 2px;
    }

    #manual-booking-form .mbf-stepper-title {
        font-size: .86rem;
        font-weight: 700;
        color: #4b465c;
        line-height: 1.35;
    }

    #manual-booking-form .mbf-stepper-item.is-pending .mbf-stepper-title {
        color: #b9b9c3;
        font-weight: 500;
    }

    #manual-booking-form .mbf-pay {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 8px;
        margin-bottom: 1.25rem;
    }

    #manual-booking-form .mbf-pay-option {
        position: relative;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin: 0;
        padding: 14px 6px 12px;
        border: 1px solid #e6e5ea;
        border-radius: 12px;
        background: #fff;
        cursor: pointer;
        user-select: none;
        transition: border-color .18s ease, background .18s ease, box-shadow .18s ease;
    }

    #manual-booking-form .mbf-pay-option:hover {
        border-color: #cfcbe8;
        background: #fbfbfe;
    }

    #manual-booking-form .mbf-pay-option.is-active {
        border-color: #7367f0;
        background: #f6f5ff;
        box-shadow: 0 0 0 3px rgba(115, 103, 240, .12);
    }

    #manual-booking-form .mbf-pay-input {
        position: absolute;
        inset: 0;
        opacity: 0;
        margin: 0;
        cursor: pointer;
        width: 100%;
        height: 100%;
        z-index: 1;
    }

    #manual-booking-form .mbf-pay-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
        background: #f3f2f7;
        color: #6e6b7b;
        transition: background .18s ease, color .18s ease, box-shadow .18s ease;
    }

    #manual-booking-form .mbf-pay-option[data-kind="cash"] .mbf-pay-icon {
        color: #3d8b65;
        background: #eef7f2;
    }

    #manual-booking-form .mbf-pay-option[data-kind="card"] .mbf-pay-icon {
        color: #4d6aa5;
        background: #eef2f8;
    }

    #manual-booking-form .mbf-pay-option[data-kind="medical"] .mbf-pay-icon {
        color: #3d8a96;
        background: #eef6f7;
    }

    #manual-booking-form .mbf-pay-option[data-kind="credit"] .mbf-pay-icon {
        color: #9a7a42;
        background: #f7f3ea;
    }

    #manual-booking-form .mbf-pay-option.is-active .mbf-pay-icon {
        background: #7367f0;
        color: #fff;
        box-shadow: 0 6px 14px rgba(115, 103, 240, .28);
    }

    #manual-booking-form .mbf-pay-label {
        font-size: .78rem;
        font-weight: 600;
        color: #6e6b7b;
        line-height: 1.25;
        text-align: center;
    }

    #manual-booking-form .mbf-pay-option.is-active .mbf-pay-label {
        color: #5e50ee;
    }

    #manual-booking-form .mbf-pay-option:focus-within {
        outline: 0;
    }

    #manual-booking-form .mbf-pay-input:focus-visible ~ .mbf-pay-icon {
        box-shadow: 0 0 0 4px rgba(115, 103, 240, .22);
    }

    @media (max-width: 575.98px) {
        #manual-booking-form .mbf-pay {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    .ta-metric__value {
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    @media (max-width: 991.98px) {
        .ta-page {
            padding: 12px;
        }

        .ta-footer {
            padding: 16px 12px;
        }

        .ta-topbar {
            padding: 8px 10px;
            gap: 8px;
        }

        .ta-card__head,
        .ta-card__body {
            padding: 14px;
        }

        .ta-page-head {
            gap: 10px;
            margin-bottom: 16px;
        }

        .ta-page-head > .d-flex {
            width: 100%;
        }

        .ta-page .row {
            --bs-gutter-x: 1rem;
        }

        #manual-booking-form .mbf-aside-sticky {
            position: static;
            top: auto;
            max-height: none;
            overflow: visible;
        }

        #iranMap,
        #medicalIranMap,
        .med-iran-map {
            height: 260px !important;
        }

        .ta-metric {
            padding: 14px;
        }

        .ta-metric__icon {
            width: 36px;
            height: 36px;
            font-size: 1.1rem;
        }

        .ta-metric__label {
            margin-top: 8px;
            font-size: .72rem;
        }

        .ta-metric__value {
            font-size: 1.05rem !important;
        }
    }

    @media (max-width: 767.98px) {
        .ta-breadcrumb-trail {
            display: none !important;
        }

        .ta-breadcrumb-compact {
            display: flex;
        }

        .ta-breadcrumb-btn--current {
            min-width: 0;
            max-width: 100%;
            flex: 1 1 auto;
            padding: 6px 10px;
            gap: 6px;
            overflow: hidden;
        }

        .ta-breadcrumb-current-label {
            max-width: none;
            flex: 1 1 auto;
        }

        .ta-hamburger {
            width: 38px;
            height: 38px;
        }

        .ta-icon-btn,
        .ta-user__avatar {
            width: 38px;
            height: 38px;
        }

        .ta-card__title {
            font-size: .95rem;
        }

        .ta-card__sub {
            font-size: .75rem;
            line-height: 1.45;
        }

        .ta-card__head > .d-flex,
        .ta-card__head > div:last-child {
            max-width: 100%;
        }

        .ta-card__head .form-select {
            min-width: 0 !important;
            max-width: 100%;
        }
    }

    @media (max-width: 575.98px) {
        .ta-page {
            padding: 10px;
        }

        .ta-page-head .btn {
            font-size: .78rem;
            padding: .35rem .65rem;
        }

        .dashboard-acc-filter__label {
            max-width: 9.5rem !important;
        }

        .host-leaderboard__podium {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 4px;
        }
    }

    [data-bs-theme="dark"] .ta-breadcrumb-back,
    [data-bs-theme="dark"] .ta-breadcrumb-more {
        background: var(--ta-card-bg, #1d2939);
        border-color: var(--ta-border);
        color: var(--ta-gray-300);
    }
</style>
@endonce

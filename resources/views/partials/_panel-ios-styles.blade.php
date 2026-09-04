@once
<style>
    /*
     * Admin / host shell — iOS 27 Liquid Glass
     * Floating frosted sidebar, pill chrome, airy content.
     */
    body.ta-ios {
        --ta-sidebar-w: 276px;
        --ta-sidebar-collapsed-w: 92px;
        --ta-shell-gap: 14px;
        --ta-topbar-h: 56px;
        --ta-radius: 16px;
        --ta-radius-lg: 22px;

        --ios-label: #0d0d0f;
        --ios-secondary: rgba(28, 28, 30, .84);
        --ios-tertiary: rgba(28, 28, 30, .64);
        --ios-fill: rgba(120, 120, 128, .12);
        --ios-fill-strong: rgba(120, 120, 128, .18);
        --ios-separator: rgba(60, 60, 67, .12);
        --ios-blue: #007aff;
        --ios-blue-press: #0066d6;
        --ios-red: #ff3b30;
        --ios-green: #34c759;
        --ios-glass: rgba(255, 255, 255, 0);
        --ios-glass-strong: rgba(255, 255, 255, .78);
        --ios-stroke: rgba(255, 255, 255, .82);
        --ios-hairline: rgba(255, 255, 255, .94);
        --ios-shadow: 0 10px 32px rgba(31, 45, 61, .1), 0 2px 8px rgba(31, 45, 61, .05);
        --ios-shadow-float: 0 8px 28px rgba(15, 23, 42, .08), 0 1px 2px rgba(15, 23, 42, .04);
        --ios-ease: cubic-bezier(.22, 1, .36, 1);
        --ios-blur: 42px;

        font-family: 'Vazirmatn', -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Segoe UI', sans-serif;
        color: var(--ios-label);
        min-height: 100vh;
        min-height: 100dvh;
        line-height: 0;
        background: transparent !important;
        position: relative;
        z-index: 1;
        -webkit-font-smoothing: auto;
    }

    /*
     * Body has whitespace text nodes between the fixed sidebar and .ta-main.
     * With the sidebar out of flow those newlines become an anonymous ~21px
     * line box, so every page is 100vh + one line and can be scrolled for
     * no reason. Zero the body's line-height, then restore it on children.
     */
    body.ta-ios > * {
        line-height: var(--bs-body-line-height, 1.5);
    }

    html.ta-ios {
        background: #d7e4f2;
        /* Keep classic scrollbar space reserved so overflow:hidden on confirm
           cannot widen the page. Overlay scrollbars reserve nothing. */
        scrollbar-gutter: stable;
    }

    html.ta-ios::before {
        content: "";
        position: fixed;
        inset: 0;
        z-index: 0;
        pointer-events: none;
        transform: translateZ(0);
        contain: strict;
        background:
            radial-gradient(ellipse 78% 52% at 100% -8%, rgba(120, 198, 255, .52), transparent 58%),
            radial-gradient(ellipse 58% 46% at -6% 18%, rgba(170, 240, 208, .4), transparent 54%),
            radial-gradient(ellipse 52% 42% at 78% 108%, rgba(186, 170, 255, .38), transparent 56%),
            radial-gradient(ellipse 40% 34% at 8% 92%, rgba(255, 214, 140, .28), transparent 50%),
            linear-gradient(180deg, #e8eef8 0%, #d7e4f2 100%);
    }

    body.ta-ios .ta-sidebar,
    body.ta-ios .ta-main {
        transition:
            width .32s var(--ios-ease),
            margin .32s var(--ios-ease);
    }

    /*
     * SweetAlert2 locks body overflow and injects padding-right equal to a
     * measured scrollbar width. Overlay / thin scrollbars often measure a few
     * pixels while the page layout has no gutter, so .ta-main shrinks and the
     * width transition makes it look like the column slides.
     */
    html.ta-ios.swal2-shown,
    html.ta-ios.swal2-height-auto,
    body.ta-ios.swal2-shown,
    body.ta-ios.swal2-height-auto {
        padding-right: 0 !important;
        padding-left: 0 !important;
    }

    body.ta-ios .ta-nav-link,
    body.ta-ios .ta-icon-btn,
    body.ta-ios .ta-hamburger,
    body.ta-ios .ta-breadcrumb-btn {
        transition:
            background-color .2s var(--ios-ease),
            color .2s var(--ios-ease);
    }

    /* ── Sidebar ─────────────────────────────────────────────── */
    body.ta-ios .ta-sidebar {
        width: var(--ta-sidebar-w);
        top: var(--ta-shell-gap);
        right: var(--ta-shell-gap);
        bottom: var(--ta-shell-gap);
        height: auto;
        overflow: visible;
        isolation: isolate;
        background: transparent;
        border: 0;
        border-radius: 28px;
        z-index: 1045;
    }

    body.ta-ios .ta-sidebar::before {
        content: "";
        position: absolute;
        inset: 0;
        border-radius: inherit;
        z-index: 0;
        pointer-events: none;
        transform: translateZ(0);
        background:
            linear-gradient(180deg, rgba(255, 255, 255, .42) 0%, rgba(255, 255, 255, 0) 28%),
            var(--ios-glass);
        -webkit-backdrop-filter: blur(var(--ios-blur)) saturate(1.85);
        backdrop-filter: blur(var(--ios-blur)) saturate(1.85);
        border: 1px solid var(--ios-stroke);
        box-shadow:
            inset 0 1px 0 var(--ios-hairline),
            inset 0 0 0 1px rgba(255, 255, 255, .28),
            var(--ios-shadow);
    }

    body.ta-ios .ta-sidebar::after {
        content: "";
        position: absolute;
        inset: 10px 14px auto 14px;
        height: 52px;
        border-radius: 22px;
        z-index: 0;
        pointer-events: none;
        background: linear-gradient(180deg, rgba(255, 255, 255, .35), rgba(255, 255, 255, 0));
        opacity: .7;
    }

    body.ta-ios .ta-sidebar > * {
        position: relative;
        z-index: 1;
    }

    body.ta-ios .ta-sidebar__brand {
        height: auto;
        min-height: 76px;
        padding: 18px 18px 14px;
        gap: 12px;
        align-items: center;
        border-bottom: 1px solid var(--ios-separator);
        margin-bottom: 2px;
    }

    body.ta-ios .ta-sidebar__logo {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: #fff;
        padding: 4px;
        box-shadow:
            0 0 0 2px rgba(255, 255, 255, .95),
            0 8px 18px rgba(15, 23, 42, .12);
    }

    body.ta-ios .ta-sidebar__brand-text {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }

    body.ta-ios .ta-sidebar__kicker {
        font-size: .68rem;
        font-weight: 650;
        letter-spacing: .04em;
        color: var(--ios-tertiary);
        line-height: 1.2;
    }

    body.ta-ios .ta-sidebar__title {
        font-size: 1.02rem;
        font-weight: 750;
        color: var(--ios-label);
        letter-spacing: -.02em;
        line-height: 1.35;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    body.ta-ios .ta-sidebar__toggle {
        position: absolute;
        top: 28px;
        left: 0;
        width: 28px;
        height: 28px;
        margin: 0;
        border-radius: 50%;
        border: 1px solid rgba(60, 60, 67, .08);
        background: #fff;
        color: var(--ios-label);
        box-shadow: 0 4px 14px rgba(15, 23, 42, .14);
        transform: translate(-50%, 0);
        z-index: 6;
    }

    body.ta-ios .ta-sidebar__toggle:hover {
        background: #fff;
        color: var(--ios-label);
        box-shadow: 0 6px 18px rgba(15, 23, 42, .18);
    }

    body.ta-ios .ta-sidebar__toggle:active {
        transform: translate(-50%, 0) scale(.94);
    }

    body.ta-ios .ta-sidebar__toggle-icon {
        width: 14px;
        height: 14px;
    }

    [dir="rtl"] body.ta-ios .ta-sidebar__toggle-icon {
        transform: scaleX(-1);
    }

    body.ta-ios .ta-sidebar__nav {
        padding: 6px 12px 8px;
        overflow-x: hidden;
        overflow-y: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
        scroll-behavior: smooth;
        scroll-padding-block: 12px;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
    }

    body.ta-ios .ta-sidebar__nav::-webkit-scrollbar {
        display: none;
        width: 0;
    }

    body.ta-ios .ta-sidebar__section {
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .08em;
        color: var(--ios-tertiary);
        text-transform: none;
        padding: 2px 12px;
        margin: 16px 0 8px;
    }

    body.ta-ios .ta-nav-link {
        min-height: 42px;
        gap: 11px;
        padding: 8px 12px;
        margin-bottom: 3px;
        border-radius: 14px;
        color: var(--ios-secondary);
        font-size: .9rem;
        font-weight: 550;
        background: transparent;
    }

    body.ta-ios .ta-nav-link i {
        font-size: 1.12rem;
        width: 22px;
        color: var(--ios-secondary);
    }

    body.ta-ios .ta-nav-link:hover {
        background: var(--ios-fill);
        color: var(--ios-label);
    }

    body.ta-ios .ta-nav-link:hover i {
        color: var(--ios-label);
    }

    body.ta-ios .ta-nav-link:active {
        transform: scale(.985);
    }

    body.ta-ios .ta-nav-group {
        border-radius: 16px;
        padding: 2px;
        margin-bottom: 3px;
        transition: background-color .32s var(--ios-ease);
    }

    body.ta-ios .ta-nav-group.open {
        background: rgba(255, 255, 255, .2);
        box-shadow: inset 0 0.5px 0 rgba(255, 255, 255, .48);
        padding: 3px;
        margin-bottom: 8px;
    }

    body.ta-ios .ta-nav-group > .ta-nav-link {
        width: 100%;
        display: flex;
        align-items: center;
        margin-bottom: 0;
        border: 0;
        appearance: none;
        text-align: inherit;
    }

    body.ta-ios .ta-nav-group.open > .ta-nav-link {
        background: transparent;
        color: var(--ios-label);
        font-weight: 620;
        box-shadow: none;
    }

    body.ta-ios .ta-nav-group.open > .ta-nav-link:hover {
        background: rgba(255, 255, 255, .28);
    }

    body.ta-ios .ta-nav-group.open > .ta-nav-link i:first-child {
        color: var(--ios-label);
    }

    body.ta-ios .ta-nav-link.active {
        background: linear-gradient(
            180deg,
            rgba(255, 255, 255, .84) 0%,
            rgba(255, 255, 255, .56) 100%
        );
        color: var(--ios-label);
        font-weight: 650;
        -webkit-backdrop-filter: blur(24px) saturate(1.9);
        backdrop-filter: blur(24px) saturate(1.9);
        box-shadow:
            inset 0 1px 0 var(--ios-hairline),
            inset 0 0 0 0.5px rgba(255, 255, 255, .72);
    }

    body.ta-ios .ta-nav-link.active i {
        color: var(--ios-blue);
    }

    body.ta-ios .ta-nav-link__arrow {
        font-size: .72rem !important;
        width: auto !important;
        margin-inline-start: auto;
        color: var(--ios-tertiary) !important;
        transition: transform .44s cubic-bezier(.32, .72, 0, 1);
    }

    body.ta-ios .ta-nav-group.open > .ta-nav-link .ta-nav-link__arrow {
        transform: rotate(-180deg);
    }

    body.ta-ios .ta-submenu-panel {
        display: grid;
        grid-template-rows: 0fr;
        max-height: 0;
        overflow: hidden;
        transition:
            grid-template-rows .44s cubic-bezier(.32, .72, 0, 1),
            max-height .44s cubic-bezier(.32, .72, 0, 1);
    }

    body.ta-ios .ta-nav-group.open > .ta-submenu-panel {
        grid-template-rows: 1fr;
        max-height: 28rem;
    }

    body.ta-ios .ta-submenu-panel > .ta-submenu {
        overflow: hidden;
        display: block !important;
        max-height: none !important;
        visibility: hidden;
        opacity: 0;
        transform: translateY(-6px);
        transition:
            opacity .28s cubic-bezier(.32, .72, 0, 1) .06s,
            transform .44s cubic-bezier(.32, .72, 0, 1),
            visibility 0s linear .44s;
    }

    body.ta-ios .ta-nav-group.open > .ta-submenu-panel > .ta-submenu {
        visibility: visible;
        opacity: 1;
        transform: none;
        transition:
            opacity .28s cubic-bezier(.32, .72, 0, 1) .04s,
            transform .44s cubic-bezier(.32, .72, 0, 1),
            visibility 0s;
    }

    body.ta-ios .ta-nav-link .badge {
        font-size: .65rem;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--ios-label) !important;
        color: #fff !important;
        font-weight: 700;
    }

    body.ta-ios .ta-submenu {
        position: relative;
        display: block;
        list-style: none;
        margin: 2px 0 6px;
        padding: 2px 0 4px;
        padding-inline-start: 0;
        margin-inline-start: 22px;
        max-height: none !important;
    }

    body.ta-ios .ta-submenu::before {
        content: "";
        position: absolute;
        top: 2px;
        bottom: 10px;
        inset-inline-start: 11px;
        width: 1.5px;
        border-radius: 99px;
        background: var(--ios-separator);
    }

    body.ta-ios .ta-submenu a {
        position: relative;
        display: block;
        margin-inline-start: 22px;
        margin-bottom: 3px;
        padding: 8px 12px;
        border-radius: 12px;
        color: var(--ios-secondary);
        font-size: .84rem;
        font-weight: 520;
    }

    body.ta-ios .ta-submenu a::before {
        content: "";
        position: absolute;
        width: 11px;
        height: 1.5px;
        inset-inline-start: auto;
        inset-inline-end: 100%;
        top: 50%;
        transform: translateY(-50%);
        background: var(--ios-separator);
        border-radius: 99px;
    }

    body.ta-ios .ta-submenu a:hover {
        background: var(--ios-fill);
        color: var(--ios-label);
    }

    body.ta-ios .ta-submenu a.active {
        background: linear-gradient(
            180deg,
            rgba(255, 255, 255, .84) 0%,
            rgba(255, 255, 255, .56) 100%
        );
        color: var(--ios-label);
        font-weight: 650;
        -webkit-backdrop-filter: blur(24px) saturate(1.9);
        backdrop-filter: blur(24px) saturate(1.9);
        box-shadow:
            inset 0 1px 0 var(--ios-hairline),
            inset 0 0 0 0.5px rgba(255, 255, 255, .72);
    }

    body.ta-ios .ta-submenu a.active::before {
        background: var(--ios-blue);
        opacity: .55;
    }

    body.ta-ios .ta-sidebar__foot {
        flex-shrink: 0;
        padding: 8px 14px 16px;
    }

    body.ta-ios .ta-sidebar__foot form {
        margin: 0;
    }

    body.ta-ios .ta-nav-link--logout {
        justify-content: center;
        min-height: 44px;
        margin: 0;
        border-radius: 999px;
        background: #fff;
        color: var(--ios-label);
        font-weight: 650;
        box-shadow:
            0 1px 2px rgba(15, 23, 42, .05),
            0 8px 20px rgba(15, 23, 42, .08);
    }

    body.ta-ios .ta-nav-link--logout i {
        color: var(--ios-label);
    }

    body.ta-ios .ta-nav-link--logout:hover,
    body.ta-ios .ta-nav-link--logout:hover i {
        color: var(--ios-red);
        background: #fff;
    }

    /* ── Main + topbar ───────────────────────────────────────── */
    body.ta-ios .ta-main {
        margin-right: calc(var(--ta-sidebar-w) + var(--ta-shell-gap) * 2);
        min-height: 100vh;
        min-height: 100dvh;
        background: transparent;
    }

    body.ta-ios .ta-topbar {
        min-height: var(--ta-topbar-h);
        margin: var(--ta-shell-gap);
        margin-bottom: 0;
        padding: 8px 10px;
        gap: 10px;
        background: var(--ios-glass);
        -webkit-backdrop-filter: blur(28px) saturate(1.8);
        backdrop-filter: blur(28px) saturate(1.8);
        border: 1px solid var(--ios-stroke);
        border-radius: 22px;
        box-shadow:
            inset 0 1px 0 var(--ios-hairline),
            var(--ios-shadow-float);
        position: sticky;
        top: var(--ta-shell-gap);
        z-index: 1030;
    }

    body.ta-ios .ta-hamburger,
    body.ta-ios .ta-icon-btn,
    body.ta-ios .ta-breadcrumb-back,
    body.ta-ios .ta-breadcrumb-more {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: var(--ios-glass-strong);
        border: 1px solid var(--ios-hairline);
        color: var(--ios-label);
        box-shadow: 0 4px 14px rgba(15, 23, 42, .08);
    }

    body.ta-ios .ta-hamburger:hover,
    body.ta-ios .ta-icon-btn:hover,
    body.ta-ios .ta-breadcrumb-back:hover,
    body.ta-ios .ta-breadcrumb-more:hover {
        background: #fff;
        color: var(--ios-label);
        border-color: var(--ios-hairline);
    }

    body.ta-ios .ta-icon-btn .ta-dot {
        top: 7px;
        right: 8px;
        width: 9px;
        height: 9px;
        background: var(--ios-label);
        border: 2px solid #fff;
    }

    body.ta-ios .ta-breadcrumb-btn {
        border-radius: 999px;
        background: rgba(255, 255, 255, .7);
        border: 1px solid var(--ios-hairline);
        color: var(--ios-secondary);
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        padding: 7px 13px;
        font-weight: 560;
    }

    body.ta-ios a.ta-breadcrumb-btn:hover {
        background: #fff;
        color: var(--ios-label);
        border-color: var(--ios-hairline);
        box-shadow: 0 4px 12px rgba(15, 23, 42, .08);
    }

    body.ta-ios .ta-breadcrumb-btn--current {
        background: var(--ios-label);
        border-color: var(--ios-label);
        color: #fff;
        font-weight: 700;
        box-shadow: 0 6px 16px rgba(28, 28, 30, .2);
    }

    body.ta-ios .ta-breadcrumb-sep {
        color: var(--ios-tertiary);
    }

    body.ta-ios .ta-user {
        border-radius: 999px;
        background: rgba(255, 255, 255, .72);
        border: 1px solid var(--ios-hairline);
        padding: 4px 12px 4px 5px;
        gap: 8px;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .08);
    }

    body.ta-ios .ta-user:hover {
        background: #fff;
    }

    body.ta-ios .ta-user__avatar {
        width: 34px;
        height: 34px;
        background: linear-gradient(165deg, #ffd29a 0%, #ff8a3d 100%);
        color: #fff;
        font-weight: 750;
        box-shadow: 0 0 0 2px #fff;
        font-size: .85rem;
    }

    body.ta-ios .ta-user__name {
        font-size: .82rem;
        font-weight: 700;
        color: var(--ios-label);
        line-height: 1.2;
    }

    body.ta-ios .ta-user__role {
        font-size: .68rem;
        color: var(--ios-tertiary);
        line-height: 1.2;
    }

    body.ta-ios .dropdown-menu {
        border-radius: 16px;
        border: 1px solid var(--ios-separator);
        background: #fff;
        -webkit-backdrop-filter: none;
        backdrop-filter: none;
        box-shadow: 0 18px 48px rgba(15, 23, 42, .16);
        padding: .45rem;
    }

    body.ta-ios .dropdown-item {
        border-radius: 10px;
        padding: .55rem .75rem;
        font-weight: 550;
    }

    body.ta-ios .dropdown-item:hover {
        background: var(--ios-fill);
        color: var(--ios-label);
    }

    body.ta-ios .ta-page {
        padding: 6px var(--ta-shell-gap) 8px;
    }

    body.ta-ios .ta-page-toolbar .text-muted,
    body.ta-ios .ta-page-toolbar p {
        margin-inline-end: auto;
        margin-bottom: 0;
        color: var(--ios-secondary);
    }

    body.ta-ios .ta-page-toolbar,
    body.ta-ios .ta-page-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
        width: max-content;
        max-width: 100%;
        margin: 0 0 8px auto;
        min-height: 0;
    }

    body.ta-ios .ta-page-toolbar:empty {
        display: none !important;
        margin: 0;
    }

    body.ta-ios .ta-list-chrome {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        border-bottom: 1px solid var(--ios-separator);
    }

    body.ta-ios .card > .ta-list-chrome:last-child {
        border-bottom: 0;
    }

    body.ta-ios .ta-list-chrome .nav-tabs,
    body.ta-ios .ta-list-chrome .nav-pills {
        flex: 1 1 12rem;
        width: auto !important;
        min-width: 0;
        margin: 0;
    }

    body.ta-ios .ta-list-chrome .ta-page-toolbar,
    body.ta-ios .ta-list-chrome .ta-page-head {
        width: auto;
        margin: 0;
        margin-inline-start: auto;
    }

    body.ta-ios .ta-filter-stats {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        min-width: 0;
    }

    body.ta-ios .ta-filter-stat {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 12px;
        background: var(--ios-fill);
        min-height: 31px;
        line-height: 1;
    }

    body.ta-ios .ta-filter-stat > i {
        font-size: .95rem;
        color: var(--ios-blue);
        line-height: 1;
    }

    body.ta-ios .ta-filter-stat-label {
        font-size: .72rem;
        color: var(--ios-secondary);
        font-weight: 520;
        white-space: nowrap;
    }

    body.ta-ios .ta-filter-stat-value {
        font-size: .82rem;
        font-weight: 750;
        color: var(--ios-label);
        white-space: nowrap;
    }

    body.ta-ios .ta-filter-stat-value--ok {
        color: var(--ios-green);
    }

    body.ta-ios .ta-page-head h1,
    body.ta-ios .ta-page-head h2 {
        display: none;
    }

    body.ta-ios .ta-page-head .text-muted,
    body.ta-ios .ta-page-head .small {
        color: var(--ios-secondary) !important;
        font-weight: 520;
        max-width: 42rem;
        margin-inline-end: auto;
    }

    body.ta-ios .ta-page .card,
    body.ta-ios .ta-card,
    body.ta-ios .ta-metric,
    body.ta-ios .ta-stat-card,
    body.ta-ios .stat-card {
        border-radius: 20px !important;
        border: 1px solid rgba(255, 255, 255, .9) !important;
        background: rgba(255, 255, 255, .86);
        box-shadow: var(--ios-shadow-float) !important;
        contain: layout;
    }

    body.ta-ios .ta-page .card:has(.dashboard-acc-filter),
    body.ta-ios .ta-page .card:has(.dropdown-menu) {
        contain: none;
    }

    body.ta-ios .ta-page .card.host-acc-card {
        background: #9bb3c9 !important;
        padding: 0;
        overflow: hidden;
        isolation: isolate;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        min-height: 460px;
    }

    .host-acc-card {
        position: relative;
        overflow: hidden;
        isolation: isolate;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        min-height: 460px;
    }

    .host-acc-card__media {
        position: absolute;
        inset: 0;
        z-index: 0;
    }

    .host-acc-card__media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .host-acc-card__placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background:
            radial-gradient(ellipse 80% 60% at 80% 10%, rgba(120, 198, 255, .45), transparent 58%),
            linear-gradient(165deg, #c9d8ea 0%, #8ea8c4 100%);
        color: rgba(255, 255, 255, .92);
        font-size: 3.25rem;
    }

    .host-acc-card__content {
        position: relative;
        z-index: 2;
        padding: 1.05rem 1.15rem 1rem;
    }

    .host-acc-card__content::before {
        content: '';
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        top: -88px;
        z-index: -1;
        pointer-events: none;
        background: linear-gradient(
            to top,
            rgba(255, 255, 255, .9) 0%,
            rgba(255, 255, 255, .52) 58%,
            rgba(255, 255, 255, .1) 86%,
            transparent 100%
        );
        -webkit-backdrop-filter: blur(22px) saturate(1.45);
        backdrop-filter: blur(22px) saturate(1.45);
        -webkit-mask-image: linear-gradient(to top, #000 64%, transparent 100%);
        mask-image: linear-gradient(to top, #000 64%, transparent 100%);
    }

    .host-acc-card__actions {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        margin-top: .85rem;
    }

    body.ta-ios .ta-page a.card.host-acc-card--add,
    a.card.host-acc-card--add {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        text-decoration: none;
        min-height: 460px;
        background:
            radial-gradient(ellipse 70% 55% at 50% 42%, rgba(255, 255, 255, .45), transparent 62%),
            linear-gradient(165deg, rgba(255, 255, 255, .42) 0%, rgba(201, 216, 234, .55) 100%) !important;
        border: 1.5px dashed rgba(255, 255, 255, .95) !important;
        box-shadow: none !important;
        color: var(--ios-label);
        transition:
            transform .2s var(--ios-ease),
            background-color .2s var(--ios-ease),
            border-color .2s var(--ios-ease);
    }

    body.ta-ios .ta-page a.card.host-acc-card--add:hover,
    a.card.host-acc-card--add:hover {
        color: var(--ios-label);
        border-color: rgba(255, 255, 255, 1) !important;
        background:
            radial-gradient(ellipse 70% 55% at 50% 42%, rgba(255, 255, 255, .62), transparent 62%),
            linear-gradient(165deg, rgba(255, 255, 255, .58) 0%, rgba(201, 216, 234, .7) 100%) !important;
        transform: scale(.985);
    }

    .host-acc-card__plus {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, .72);
        box-shadow:
            inset 0 1px 0 var(--ios-hairline),
            0 8px 24px rgba(15, 23, 42, .08);
        color: var(--ios-blue);
        font-size: 2rem;
        line-height: 1;
    }

    .host-acc-card__add-label {
        font-size: .95rem;
        font-weight: 650;
        color: var(--ios-secondary);
    }

    [data-bs-theme="dark"] .host-acc-card__content::before {
        background: linear-gradient(
            to top,
            rgba(28, 28, 30, .9) 0%,
            rgba(28, 28, 30, .52) 58%,
            rgba(28, 28, 30, .12) 86%,
            transparent 100%
        );
    }

    body.ta-ios .card-header {
        background: transparent;
        border-bottom: 1px solid var(--ios-separator);
        padding: 14px 18px;
        font-weight: 700;
        color: var(--ios-label);
    }

    body.ta-ios .card-header.bg-white {
        background-color: transparent !important;
    }

    body.ta-ios .card-header.bg-light,
    body.ta-ios .p-3.border-bottom.bg-light {
        background-color: var(--ios-fill) !important;
        color: var(--ios-label) !important;
    }

    body.ta-ios .card-body {
        padding: 1.05rem 1.2rem;
    }

    body.ta-ios .card-footer {
        background: transparent;
        border-top: 1px solid var(--ios-separator);
    }

    body.ta-ios .ta-card__head {
        padding: 16px 20px;
        border-bottom: 1px solid var(--ios-separator);
    }

    body.ta-ios .ta-card__title {
        font-weight: 750;
        letter-spacing: -.02em;
        color: var(--ios-label);
    }

    body.ta-ios .ta-card__sub {
        color: var(--ios-tertiary);
    }

    body.ta-ios .ta-page .nav-tabs,
    body.ta-ios .ta-page .nav-pills {
        border: 0;
        background: var(--ios-fill-strong);
        border-radius: 12px;
        padding: 3px;
        gap: 0;
        display: flex;
        flex-wrap: wrap;
        width: 100%;
    }

    body.ta-ios .ta-page .nav-tabs .nav-item,
    body.ta-ios .ta-page .nav-pills .nav-item {
        flex: 1 1 auto;
    }

    body.ta-ios .ta-page .nav-tabs .nav-link,
    body.ta-ios .ta-page .nav-pills .nav-link {
        border: 0 !important;
        border-radius: 9px;
        color: var(--ios-secondary);
        font-weight: 650;
        width: 100%;
        text-align: center;
        padding: .45rem .7rem;
        background: transparent;
    }

    body.ta-ios .ta-page .nav-tabs .nav-link:hover,
    body.ta-ios .ta-page .nav-pills .nav-link:hover {
        color: var(--ios-label);
        background: transparent;
    }

    body.ta-ios .ta-page .nav-tabs .nav-link.active,
    body.ta-ios .ta-page .nav-pills .nav-link.active {
        background: #fff;
        color: var(--ios-label);
        box-shadow: 0 1px 3px rgba(15, 23, 42, .1), 0 6px 14px rgba(15, 23, 42, .04);
    }

    body.ta-ios .btn-group {
        background: var(--ios-fill-strong);
        border-radius: 12px;
        padding: 3px;
        gap: 2px;
    }

    body.ta-ios .btn-group > .btn-outline-secondary,
    body.ta-ios .btn-group > .btn-outline-primary,
    body.ta-ios .btn-group > .btn-light {
        border: 0;
        border-radius: 9px !important;
        background: transparent;
        color: var(--ios-secondary);
        box-shadow: none;
        font-weight: 650;
    }

    body.ta-ios .btn-group > .btn-outline-secondary.active,
    body.ta-ios .btn-group > .btn-outline-primary.active,
    body.ta-ios .btn-group > .btn-light.active,
    body.ta-ios .btn-group > .btn-outline-secondary:hover,
    body.ta-ios .btn-group > .btn-light:hover,
    body.ta-ios .btn-group > .btn-primary {
        background: #fff !important;
        color: var(--ios-label) !important;
        border: 0;
        border-radius: 9px !important;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .1), 0 6px 14px rgba(15, 23, 42, .04);
    }

    body.ta-ios .btn-group.occ-cal__month-nav {
        background: transparent;
        padding: 0;
        gap: 4px;
    }

    body.ta-ios .btn-group.occ-cal__month-nav > .btn-light {
        background: #fff;
        border: 1px solid var(--ios-separator);
        color: var(--ios-label);
        box-shadow:
            inset 0 1px 0 var(--ios-hairline),
            0 1px 2px rgba(15, 23, 42, .06),
            0 6px 16px rgba(15, 23, 42, .05);
    }

    body.ta-ios .btn-group.occ-cal__month-nav > .btn-light:hover {
        background: #fff !important;
        color: var(--ios-label) !important;
        box-shadow:
            inset 0 1px 0 var(--ios-hairline),
            0 1px 2px rgba(15, 23, 42, .06),
            0 6px 16px rgba(15, 23, 42, .05);
    }

    body.ta-ios .btn-group.occ-cal__month-nav > .occ-cal__month-label,
    body.ta-ios .btn-group.occ-cal__month-nav > .btn.disabled {
        opacity: 1 !important;
        pointer-events: none;
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        color: var(--ios-label) !important;
        font-weight: 700;
    }

    body.ta-ios .btn {
        border-radius: 12px;
        font-weight: 650;
        box-shadow: none;
    }

    body.ta-ios .btn-sm {
        border-radius: 10px;
        min-height: 34px;
    }

    body.ta-ios .btn-primary {
        background: var(--ios-blue);
        border-color: var(--ios-blue);
    }

    body.ta-ios .btn-primary:hover,
    body.ta-ios .btn-primary:focus,
    body.ta-ios .btn-primary:active {
        background: var(--ios-blue-press) !important;
        border-color: var(--ios-blue-press) !important;
    }

    body.ta-ios .btn-success {
        background: var(--ios-green);
        border-color: var(--ios-green);
        color: #fff;
    }

    body.ta-ios .btn-danger {
        background: var(--ios-red);
        border-color: var(--ios-red);
    }

    body.ta-ios .btn-outline-secondary,
    body.ta-ios .btn-outline-primary,
    body.ta-ios .btn-outline-success,
    body.ta-ios .btn-outline-danger,
    body.ta-ios .btn-outline-warning,
    body.ta-ios .btn-outline-info {
        border: 0;
        background: var(--ios-fill);
        color: var(--ios-label);
    }

    body.ta-ios .btn-outline-primary { color: var(--ios-blue); background: rgba(0, 122, 255, .12); }
    body.ta-ios .btn-outline-success { color: #1f8a3a; background: rgba(52, 199, 89, .14); }
    body.ta-ios .btn-outline-danger { color: var(--ios-red); background: rgba(255, 59, 48, .12); }
    body.ta-ios .btn-outline-warning { color: #b86e00; background: rgba(255, 159, 10, .16); }
    body.ta-ios .btn-outline-info { color: #0071a4; background: rgba(90, 200, 245, .18); }

    body.ta-ios .btn-outline-secondary:hover,
    body.ta-ios .btn-light:hover {
        background: #fff;
        color: var(--ios-label);
        border-color: transparent;
    }

    body.ta-ios .btn-check:checked + .btn-outline-secondary,
    body.ta-ios .btn-outline-secondary.active,
    body.ta-ios .btn-outline-secondary:active {
        background: var(--ios-blue) !important;
        color: #fff !important;
        border-color: transparent !important;
    }

    body.ta-ios .btn-check:checked + .btn-outline-secondary:hover,
    body.ta-ios .btn-outline-secondary.active:hover {
        background: var(--ios-blue-press) !important;
        color: #fff !important;
    }

    body.ta-ios .weekday-chip-group .weekday-chip {
        cursor: pointer;
        min-width: 4.25rem;
        font-weight: 700;
        user-select: none;
    }

    body.ta-ios .btn-light {
        background: #fff;
        border: 1px solid var(--ios-separator);
        color: var(--ios-label);
        border-radius: 999px;
        box-shadow:
            inset 0 1px 0 var(--ios-hairline),
            0 1px 2px rgba(15, 23, 42, .06),
            0 6px 16px rgba(15, 23, 42, .05);
    }

    body.ta-ios .dashboard-acc-filter > .btn-light {
        background: #fff;
        border: 1px solid var(--ios-separator);
        box-shadow:
            inset 0 1px 0 var(--ios-hairline),
            0 1px 2px rgba(15, 23, 42, .06),
            0 6px 16px rgba(15, 23, 42, .05);
    }

    body.ta-ios .btn-link {
        color: var(--ios-blue);
        font-weight: 650;
        text-decoration: none;
    }

    body.ta-ios .btn-xs:has(> i:only-child) {
        width: 32px !important;
        height: 32px !important;
        min-width: 32px;
        padding: 0 !important;
        font-size: .85rem !important;
        border-radius: 10px !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }

    body.ta-ios .ta-page .card-header .btn {
        width: auto !important;
        height: auto !important;
        min-width: 0;
        min-height: 32px;
        padding: .35rem .85rem !important;
        border-radius: 999px !important;
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        white-space: nowrap;
        line-height: 1.2;
        font-size: .8rem !important;
    }

    body.ta-ios .form-control,
    body.ta-ios .form-select,
    body.ta-ios .form-control-sm,
    body.ta-ios .form-select-sm {
        border-radius: 12px;
        background: var(--ios-fill);
        border: 1px solid transparent;
        box-shadow: none;
        color: var(--ios-label);
        min-height: 38px;
    }

    body.ta-ios .form-control-sm,
    body.ta-ios .form-select-sm {
        min-height: 34px;
        font-size: .84rem;
    }

    body.ta-ios .form-control:focus,
    body.ta-ios .form-select:focus {
        background: #fff;
        border-color: rgba(0, 122, 255, .45);
        box-shadow: 0 0 0 4px rgba(0, 122, 255, .16);
    }

    body.ta-ios .form-label,
    body.ta-ios .form-label-sm {
        color: var(--ios-label);
        font-size: .78rem;
    }

    body.ta-ios .input-group-text {
        background: var(--ios-fill);
        border: 0;
        color: var(--ios-secondary);
        border-radius: 12px;
    }

    body.ta-ios .input-group > .form-control,
    body.ta-ios .input-group > .form-select,
    body.ta-ios .input-group > .btn {
        box-shadow: none;
    }

    body.ta-ios .form-check-input {
        width: 1.15rem;
        height: 1.15rem;
        margin-top: .12rem;
        border-radius: 6px;
        border: 1.5px solid rgba(60, 60, 67, .28);
        background-color: #fff;
        box-shadow: none;
    }

    body.ta-ios .form-check-input:checked {
        background-color: var(--ios-blue);
        border-color: var(--ios-blue);
    }

    body.ta-ios .form-check-input:focus {
        box-shadow: 0 0 0 4px rgba(0, 122, 255, .16);
        border-color: var(--ios-blue);
    }

    body.ta-ios .form-switch {
        display: flex;
        align-items: center;
        gap: 10px;
        min-height: 31px;
        padding: 0 !important;
    }

    body.ta-ios .form-switch .form-check-label {
        margin: 0;
        padding: 0;
        line-height: 1.3;
        cursor: pointer;
    }

    body.ta-ios .form-switch .form-check-input {
        --ios-switch-w: 51px;
        --ios-switch-h: 31px;
        --ios-switch-thumb: 27px;
        --ios-switch-pad: 2px;
        appearance: none;
        -webkit-appearance: none;
        width: var(--ios-switch-w);
        height: var(--ios-switch-h);
        margin: 0 !important;
        float: none !important;
        flex-shrink: 0;
        cursor: pointer;
        position: relative;
        border: 0 !important;
        border-radius: 999px;
        background-color: #e9e9eb !important;
        background-image: none !important;
        box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .04);
        transition: background-color .35s cubic-bezier(.22, 1, .36, 1),
                    box-shadow .2s ease;
    }

    body.ta-ios .form-switch .form-check-input::after {
        content: "";
        position: absolute;
        top: var(--ios-switch-pad);
        left: var(--ios-switch-pad);
        width: var(--ios-switch-thumb);
        height: var(--ios-switch-thumb);
        border-radius: 50%;
        background: #fff;
        box-shadow:
            0 3px 8px rgba(0, 0, 0, .15),
            0 1px 1px rgba(0, 0, 0, .06),
            0 0 0 .5px rgba(0, 0, 0, .04);
        transition: transform .35s cubic-bezier(.22, 1, .36, 1),
                    width .22s cubic-bezier(.22, 1, .36, 1);
        pointer-events: none;
    }

    body.ta-ios .form-switch .form-check-input:checked {
        background-color: var(--ios-green) !important;
        border-color: transparent !important;
        background-image: none !important;
    }

    body.ta-ios .form-switch .form-check-input:checked::after {
        transform: translateX(calc(var(--ios-switch-w) - var(--ios-switch-thumb) - (var(--ios-switch-pad) * 2)));
    }

    body.ta-ios .form-switch .form-check-input:focus {
        box-shadow: 0 0 0 4px rgba(52, 199, 89, .18);
        border-color: transparent !important;
    }

    body.ta-ios .form-switch .form-check-input:active:not(:disabled)::after {
        width: 30px;
    }

    body.ta-ios .form-switch .form-check-input:checked:active:not(:disabled)::after {
        transform: translateX(calc(var(--ios-switch-w) - 30px - (var(--ios-switch-pad) * 2)));
    }

    body.ta-ios .form-switch .form-check-input:disabled {
        opacity: .45;
        cursor: default;
    }

    body.ta-ios .table {
        --bs-table-bg: transparent;
        --bs-table-striped-bg: rgba(120, 120, 128, .06);
        --bs-table-hover-bg: rgba(120, 120, 128, .1);
        color: var(--ios-label);
    }

    body.ta-ios .table > thead,
    body.ta-ios .table thead.table-light {
        --bs-table-bg: #f2f2f7;
        --bs-table-accent-bg: #f2f2f7;
        --bs-table-bg-type: initial;
        --bs-table-bg-state: initial;
        --bs-table-color: var(--ios-secondary);
    }

    body.ta-ios .table thead th,
    body.ta-ios .table thead.table-light th {
        background-color: #f2f2f7 !important;
        box-shadow: none !important;
        color: var(--ios-secondary);
        font-weight: 700;
        font-size: .72rem;
        letter-spacing: .02em;
        border-bottom: 1px solid var(--ios-separator);
        padding: .85rem 1rem;
    }

    body.ta-ios .table tbody td {
        padding: .9rem 1rem;
        border-bottom: 1px solid var(--ios-separator);
        vertical-align: middle;
    }

    body.ta-ios .table tbody tr:last-child > * {
        border-bottom: 0;
    }

    body.ta-ios .table-hover tbody tr:hover > * {
        background: rgba(120, 120, 128, .08);
    }

    body.ta-ios .table .table-secondary,
    body.ta-ios .admin-users-province-header > * {
        background: var(--ios-fill) !important;
        color: var(--ios-label);
        font-weight: 700;
    }

    body.ta-ios .table-responsive {
        border-radius: 0 0 20px 20px;
    }

    body.ta-ios .badge {
        border-radius: 999px;
        font-weight: 650;
        padding: .35em .7em;
    }

    body.ta-ios .badge.bg-primary {
        background: rgba(0, 122, 255, .14) !important;
        color: var(--ios-blue) !important;
    }

    body.ta-ios .badge.bg-success {
        background: rgba(52, 199, 89, .16) !important;
        color: #1f8a3a !important;
    }

    body.ta-ios .badge.bg-danger {
        background: rgba(255, 59, 48, .14) !important;
        color: var(--ios-red) !important;
    }

    body.ta-ios .badge.bg-warning {
        background: rgba(255, 159, 10, .18) !important;
        color: #b86e00 !important;
    }

    body.ta-ios .badge.bg-info {
        background: rgba(90, 200, 245, .2) !important;
        color: #0071a4 !important;
    }

    body.ta-ios .badge.bg-primary-subtle,
    body.ta-ios .bg-primary-subtle {
        background: rgba(0, 122, 255, .12) !important;
        color: var(--ios-blue) !important;
    }

    body.ta-ios .badge.bg-success-subtle,
    body.ta-ios .bg-success-subtle {
        background: rgba(52, 199, 89, .16) !important;
        color: #1f8a3a !important;
    }

    body.ta-ios .badge.bg-danger-subtle,
    body.ta-ios .bg-danger-subtle {
        background: rgba(255, 59, 48, .14) !important;
        color: var(--ios-red) !important;
    }

    body.ta-ios .badge.bg-warning-subtle,
    body.ta-ios .bg-warning-subtle,
    body.ta-ios .badge.bg-secondary-subtle,
    body.ta-ios .bg-secondary-subtle {
        background: var(--ios-fill) !important;
        color: var(--ios-secondary) !important;
        border: 0 !important;
    }

    body.ta-ios .badge.bg-white {
        background: #fff !important;
    }

    body.ta-ios .input-group {
        background: var(--ios-fill);
        border-radius: 12px;
        padding: 3px;
        gap: 3px;
    }

    body.ta-ios .input-group > .form-control,
    body.ta-ios .input-group > .form-select {
        background: transparent;
        border: 0;
        box-shadow: none;
        min-height: 34px;
    }

    body.ta-ios .input-group > .form-control:focus,
    body.ta-ios .input-group > .form-select:focus {
        background: #fff;
        box-shadow: none;
        border-color: transparent;
    }

    body.ta-ios .input-group > .btn {
        border-radius: 9px !important;
    }

    body.ta-ios .input-group > .btn-outline-secondary,
    body.ta-ios .input-group > .booking-clear-date {
        border: 0;
        background: transparent;
        color: var(--ios-secondary);
        min-width: 34px;
    }

    body.ta-ios .form-control.is-invalid,
    body.ta-ios .form-select.is-invalid {
        background: rgba(255, 59, 48, .08);
        border-color: rgba(255, 59, 48, .35);
    }

    body.ta-ios .form-control.is-valid,
    body.ta-ios .form-select.is-valid {
        background: rgba(52, 199, 89, .08);
        border-color: rgba(52, 199, 89, .4);
    }

    body.ta-ios .invalid-feedback {
        color: var(--ios-red);
        font-weight: 550;
    }

    body.ta-ios .form-text {
        color: var(--ios-tertiary);
    }

    body.ta-ios .text-muted {
        color: var(--ios-secondary) !important;
    }

    body.ta-ios .text-dark,
    body.ta-ios a.text-dark {
        color: var(--ios-label) !important;
    }

    body.ta-ios .table a:not(.btn):not(.badge) {
        color: var(--ios-label);
    }

    body.ta-ios .table a:not(.btn):not(.badge):hover {
        color: var(--ios-blue);
    }

    body.ta-ios .btn-close {
        opacity: .45;
        filter: none;
        border-radius: 99px;
        width: 28px;
        height: 28px;
        background-size: .65em;
    }

    body.ta-ios .btn-close:hover {
        opacity: .8;
        background-color: var(--ios-fill);
    }

    body.ta-ios .dropdown-divider {
        border-color: var(--ios-separator);
        opacity: 1;
    }

    body.ta-ios .dropdown-header {
        color: var(--ios-tertiary);
        font-weight: 700;
        font-size: .72rem;
    }

    body.ta-ios .spinner-border {
        color: var(--ios-blue);
        border-width: .15em;
    }

    body.ta-ios .table-bordered,
    body.ta-ios .table-bordered > :not(caption) > * > * {
        border-color: var(--ios-separator);
    }

    body.ta-ios .table-secondary,
    body.ta-ios tr.table-secondary > * {
        --bs-table-bg: var(--ios-fill);
        --bs-table-color: var(--ios-label);
        background: var(--ios-fill) !important;
        color: var(--ios-label);
    }

    body.ta-ios .p-3.border,
    body.ta-ios .border.rounded.p-3,
    body.ta-ios .border.rounded.p-2 {
        border-color: var(--ios-separator) !important;
        border-radius: 16px !important;
        background: var(--ios-fill);
    }

    body.ta-ios .alert-light {
        background: var(--ios-fill);
        color: var(--ios-secondary);
        border: 0;
    }

    body.ta-ios .ta-card__body {
        padding: 16px 20px 20px;
    }

    body.ta-ios .ta-legend {
        color: var(--ios-secondary);
        font-size: .78rem;
        font-weight: 620;
    }

    body.ta-ios .city-row {
        border-radius: 14px !important;
    }

    body.ta-ios .city-row .fw-semibold {
        color: var(--ios-label) !important;
    }

    /* Filter / toolbar cards */
    body.ta-ios .card-header[data-bs-toggle] {
        cursor: pointer;
        user-select: none;
    }

    body.ta-ios .card-header[data-bs-toggle]:hover {
        background: var(--ios-fill);
    }

    /* Occupancy calendar */
    body.ta-ios .occ-cal-cell {
        border-radius: 12px !important;
    }

    body.ta-ios .occ-cal-cell.c-free {
        background: rgba(52, 199, 89, .16) !important;
        border-color: transparent !important;
        color: #1f8a3a !important;
    }

    body.ta-ios .occ-cal-cell.c-partial {
        background: rgba(255, 159, 10, .18) !important;
        border-color: transparent !important;
        color: #b86e00 !important;
    }

    body.ta-ios .occ-cal-cell.c-full {
        background: var(--ios-fill) !important;
        border-color: transparent !important;
        color: var(--ios-tertiary) !important;
        background-image: none !important;
    }

    body.ta-ios .occ-cal-cell.is-today {
        box-shadow: 0 0 0 2px var(--ios-blue) !important;
    }

    body.ta-ios .occ-cal__swatch {
        border-radius: 5px;
    }

    body.ta-ios .occ-cal__swatch--free { background: rgba(52, 199, 89, .16); border-color: transparent; }
    body.ta-ios .occ-cal__swatch--partial { background: rgba(255, 159, 10, .18); border-color: transparent; }
    body.ta-ios .occ-cal__swatch--full { background: var(--ios-fill); border-color: transparent; }
    body.ta-ios .occ-cal__swatch--today { box-shadow: 0 0 0 2px var(--ios-blue); }

    body.ta-ios .occ-day-modal .booking-row {
        border: 0;
        background: var(--ios-fill);
        border-radius: 14px;
    }

    /* Room status + medical KPIs */
    body.ta-ios .room-status-box {
        border-radius: 16px !important;
        border-width: 1px !important;
    }

    body.ta-ios .room-status-box--success {
        background: rgba(52, 199, 89, .14) !important;
        border-color: rgba(52, 199, 89, .28) !important;
    }

    body.ta-ios .room-status-box--primary {
        background: rgba(0, 122, 255, .12) !important;
        border-color: rgba(0, 122, 255, .28) !important;
    }

    body.ta-ios .room-status-box--warning {
        background: rgba(255, 159, 10, .16) !important;
        border-color: rgba(255, 159, 10, .32) !important;
    }

    body.ta-ios .room-status-box--danger {
        background: rgba(255, 59, 48, .12) !important;
        border-color: rgba(255, 59, 48, .28) !important;
    }

    body.ta-ios .room-status-box__hover-tip {
        border-radius: 14px !important;
        background: rgba(28, 28, 30, .92) !important;
        color: #fff !important;
        box-shadow: 0 12px 32px rgba(0, 0, 0, .22) !important;
    }

    body.ta-ios .rsb-kpi__value,
    body.ta-ios .med-kpi__value {
        color: var(--ios-label) !important;
        letter-spacing: -.03em;
    }

    body.ta-ios .rsb-kpi__label,
    body.ta-ios .med-kpi__label,
    body.ta-ios .rsb-kpi__unit,
    body.ta-ios .med-kpi__unit,
    body.ta-ios .rsb-kpi__meta,
    body.ta-ios .med-kpi__meta {
        color: var(--ios-secondary) !important;
    }

    body.ta-ios .rsb-kpi__grid > .rsb-kpi__cell,
    body.ta-ios .med-kpi__grid > .med-kpi__cell {
        border-color: var(--ios-separator) !important;
    }

    body.ta-ios .rsb-kpi__icon,
    body.ta-ios .med-kpi__icon {
        border-radius: 14px !important;
    }

    body.ta-ios .rsb-kpi__icon--primary,
    body.ta-ios .med-kpi__icon--primary {
        color: var(--ios-blue) !important;
        background: rgba(0, 122, 255, .12) !important;
    }

    body.ta-ios .rsb-kpi__icon--success,
    body.ta-ios .med-kpi__icon--success {
        color: #1f8a3a !important;
        background: rgba(52, 199, 89, .16) !important;
    }

    body.ta-ios .rsb-kpi__icon--info,
    body.ta-ios .med-kpi__icon--info {
        color: #0071a4 !important;
        background: rgba(90, 200, 245, .18) !important;
    }

    body.ta-ios .rsb-kpi__icon--warning,
    body.ta-ios .med-kpi__icon--warning,
    body.ta-ios .med-kpi__icon--amber {
        color: #b86e00 !important;
        background: rgba(255, 159, 10, .16) !important;
    }

    body.ta-ios .rsb-kpi__icon--rose,
    body.ta-ios .med-kpi__icon--rose {
        color: var(--ios-red) !important;
        background: rgba(255, 59, 48, .12) !important;
    }

    body.ta-ios .rsb-kpi__icon--violet,
    body.ta-ios .med-kpi__icon--violet {
        color: #5856d6 !important;
        background: rgba(88, 86, 214, .14) !important;
    }

    body.ta-ios .rsb-kpi__chip,
    body.ta-ios .med-kpi__chip {
        border-radius: 999px !important;
        font-weight: 650;
    }

    /* Facility marketplace cards */
    body.ta-ios .facility-divar-card {
        background: rgba(255, 255, 255, .86);
        border: 1px solid var(--ios-stroke) !important;
        border-radius: 18px !important;
        box-shadow: var(--ios-shadow-float);
        height: 160px;
    }

    body.ta-ios .facility-divar-card:hover {
        border-color: var(--ios-hairline) !important;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .1);
        transform: translateY(-1px);
    }

    body.ta-ios .facility-divar-card__title {
        font-weight: 720;
        letter-spacing: -.02em;
        color: var(--ios-label);
    }

    body.ta-ios .facility-divar-card__desc,
    body.ta-ios .facility-divar-card__foot-item {
        color: var(--ios-secondary) !important;
    }

    body.ta-ios .facility-divar-card__badge {
        display: inline-flex;
        padding: .2em .55em;
        border-radius: 999px;
        background: rgba(255, 59, 48, .1);
        color: var(--ios-red) !important;
        font-weight: 650;
    }

    body.ta-ios .facility-divar-card__thumb {
        border-radius: 0 14px 14px 0;
        overflow: hidden;
    }

    body.ta-ios .facility-listing-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    @media (max-width: 991.98px) {
        body.ta-ios .facility-listing-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575.98px) {
        body.ta-ios .facility-listing-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    /* Booking detail + finance */
    body.ta-ios .booking-show-details .card.border-primary {
        border-color: transparent !important;
        background:
            linear-gradient(180deg, rgba(0, 122, 255, .08) 0%, rgba(255, 255, 255, .9) 42%);
    }

    body.ta-ios .booking-show-details .fs-4.fw-bold.text-primary {
        color: var(--ios-label) !important;
        letter-spacing: -.03em;
    }

    body.ta-ios .booking-detail-summary-card {
        border-radius: 18px !important;
        text-align: right !important;
    }

    body.ta-ios .booking-detail-summary-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 28px rgba(15, 23, 42, .1) !important;
    }

    body.ta-ios .booking-detail-summary-card .rounded-circle {
        border-radius: 14px !important;
    }

    body.ta-ios .bnb-fin {
        color: var(--ios-label);
    }

    body.ta-ios .bnb-fin-hero {
        background: var(--ios-fill);
        border: 0;
        border-radius: 20px;
    }

    body.ta-ios .bnb-fin-hero__amount,
    body.ta-ios .bnb-fin-row--hero .bnb-fin-row__amount,
    body.ta-ios .bnb-fin-service__foot strong {
        color: var(--ios-label) !important;
        letter-spacing: -.03em;
    }

    body.ta-ios .bnb-fin-hero__label,
    body.ta-ios .bnb-fin-currency,
    body.ta-ios .bnb-fin-hero__sub,
    body.ta-ios .bnb-fin-section__meta,
    body.ta-ios .bnb-fin-row__hint {
        color: var(--ios-secondary) !important;
    }

    body.ta-ios .bnb-fin-section,
    body.ta-ios .bnb-fin-service {
        border: 0;
        background: rgba(255, 255, 255, .55);
        border-radius: 16px;
        overflow: hidden;
    }

    body.ta-ios .bnb-fin-section__head,
    body.ta-ios .bnb-fin-service__head,
    body.ta-ios .bnb-fin-service__foot {
        background: transparent;
        border-color: var(--ios-separator);
    }

    body.ta-ios .bnb-fin-row {
        border-color: var(--ios-separator);
    }

    body.ta-ios .bnb-fin-pill {
        background: rgba(0, 122, 255, .12);
        color: var(--ios-blue);
    }

    body.ta-ios .bnb-fin-section__icon--stay {
        background: rgba(0, 122, 255, .12);
        color: var(--ios-blue);
        border-radius: 12px;
    }

    body.ta-ios .bnb-fin-section__icon--svc {
        background: rgba(52, 199, 89, .16);
        color: #1f8a3a;
        border-radius: 12px;
    }

    body.ta-ios .bnb-fin-section__icon--sum {
        background: rgba(88, 86, 214, .14);
        color: #5856d6;
        border-radius: 12px;
    }

    body.ta-ios .dashboard-acc-filter__menu {
        min-width: 280px;
        overflow: hidden;
        background: #fff !important;
        -webkit-backdrop-filter: none !important;
        backdrop-filter: none !important;
    }

    @media (max-width: 991.98px) {
        body.ta-ios .dashboard-acc-filter__menu {
            min-width: 0;
        }
    }

    @media (max-width: 575.98px) {
        body.ta-ios .ta-list-chrome > .dashboard-acc-filter {
            flex: 1 1 100%;
            width: 100%;
        }

        body.ta-ios .dashboard-acc-filter__menu {
            border-radius: 16px 16px 0 0;
            box-shadow: 0 -8px 32px rgba(15, 23, 42, .18);
        }
    }

    body.ta-ios .dashboard-acc-filter__menu .bg-light,
    body.ta-ios .dashboard-acc-filter__menu .border-bottom {
        background: #f2f2f7 !important;
        border-color: var(--ios-separator) !important;
    }

    body.ta-ios .host-permissions-matrix .form-check {
        display: flex;
        justify-content: center;
        margin: 0;
        min-height: 0;
    }

    body.ta-ios .datepicker-plot-area .datepicker-day-view td span {
        border-radius: 10px !important;
    }

    body.ta-ios .datepicker-plot-area .datepicker-day-view td.selected span,
    body.ta-ios .datepicker-plot-area td.selected span {
        background: var(--ios-blue) !important;
        color: #fff !important;
        box-shadow: none !important;
    }

    body.ta-ios .datepicker-plot-area .datepicker-day-view td.today span {
        box-shadow: inset 0 0 0 1.5px var(--ios-blue);
    }

    body.ta-ios .swal2-title {
        font-weight: 750 !important;
        letter-spacing: -.02em;
        color: var(--ios-label) !important;
    }

    /* ── iOS 27 overlay: topbar morphs into toast / confirm ── */
    body.ta-ios header.ta-topbar.ta-topbar--overlay-hidden {
        visibility: hidden !important;
        pointer-events: none !important;
    }

    .ta-ios-morph-ghost {
        position: fixed;
        top: 0;
        left: 0;
        z-index: 10045;
        margin: 0;
        padding: 0;
        pointer-events: none;
        box-sizing: border-box;
        border-radius: 22px;
        background: var(--ios-glass, rgba(255, 255, 255, 0));
        border: 1px solid var(--ios-stroke, rgba(255, 255, 255, .82));
        box-shadow:
            inset 0 1px 0 var(--ios-hairline, rgba(255, 255, 255, .94)),
            var(--ios-shadow-float, 0 8px 28px rgba(15, 23, 42, .08));
        -webkit-backdrop-filter: blur(28px) saturate(1.8);
        backdrop-filter: blur(28px) saturate(1.8);
        transform-origin: 0 0;
        overflow: hidden;
        will-change: transform, width, height, background, border-color;
        transition:
            transform .56s cubic-bezier(.22, 1, .36, 1),
            width .56s cubic-bezier(.22, 1, .36, 1),
            height .56s cubic-bezier(.22, 1, .36, 1),
            background .4s ease,
            border-color .4s ease,
            border-radius .56s cubic-bezier(.22, 1, .36, 1),
            opacity .2s ease;
    }

    .ta-ios-morph-ghost::before {
        content: "";
        position: absolute;
        inset: 0;
        border-radius: inherit;
        pointer-events: none;
        background: linear-gradient(180deg, rgba(255, 255, 255, .42), transparent 42%);
    }

    .ta-ios-morph-ghost--success {
        background: rgba(52, 199, 89, .34);
        border-color: rgba(52, 199, 89, .42);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .5),
            0 10px 32px rgba(52, 199, 89, .18);
    }

    .ta-ios-morph-ghost--error,
    .ta-ios-morph-ghost--danger {
        background: rgba(255, 59, 48, .32);
        border-color: rgba(255, 59, 48, .44);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .45),
            0 10px 32px rgba(255, 59, 48, .18);
    }

    .ta-ios-morph-ghost--warning {
        background: rgba(255, 159, 10, .3);
        border-color: rgba(255, 159, 10, .42);
    }

    .ta-ios-morph-ghost--info {
        background: rgba(0, 122, 255, .22);
        border-color: rgba(0, 122, 255, .34);
    }

    .ta-ios-morph-ghost--settled {
        opacity: 0;
    }

    body.ta-ios .swal2-container.bnb-ios-overlay-container {
        inset: 0 !important;
        top: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        left: 0 !important;
        transform: none !important;
        width: auto !important;
        max-width: none !important;
        padding: var(--bnb-overlay-top, 14px) var(--bnb-overlay-right, 14px) 28px var(--bnb-overlay-left, 14px) !important;
        display: flex !important;
        align-items: flex-start !important;
        justify-content: center !important;
        box-sizing: border-box !important;
        overflow: visible !important;
        pointer-events: none !important;
        background: transparent !important;
        -webkit-backdrop-filter: none !important;
        backdrop-filter: none !important;
    }

    body.ta-ios .swal2-container.bnb-ios-overlay-container > .swal2-popup {
        pointer-events: auto !important;
        margin: 0 !important;
        opacity: 0;
        transition: opacity .22s ease;
    }

    body.ta-ios .swal2-container.bnb-ios-overlay-container > .swal2-popup.bnb-ios-overlay-ready {
        opacity: 1 !important;
    }

    body.ta-ios .swal2-container.bnb-ios-overlay-container[data-bnb-overlay-mode="confirm"] {
        pointer-events: auto !important;
        background: transparent !important;
        -webkit-backdrop-filter: none !important;
        backdrop-filter: none !important;
        justify-content: flex-start !important;
    }

    body.ta-ios .swal2-container.bnb-ios-overlay-container[data-bnb-overlay-mode="confirm"]::before {
        content: none;
    }

    body.ta-ios.swal2-shown:not(.swal2-toast-shown) .swal2-container.bnb-ios-overlay-container[data-bnb-overlay-mode="confirm"] {
        background: transparent !important;
        -webkit-backdrop-filter: none !important;
        backdrop-filter: none !important;
    }

    body.ta-ios .swal2-container.bnb-ios-overlay-container[data-bnb-overlay-mode="confirm"] > .swal2-popup {
        box-shadow:
            inset 0 1px 0 var(--ios-hairline),
            var(--ios-shadow-float),
            0 0 0 100vmax rgba(15, 23, 42, .40) !important;
    }

    body.ta-ios .bnb-swal-toast.swal2-popup.swal2-toast {
        width: auto !important;
        min-width: 260px;
        max-width: min(380px, 100%) !important;
        border-radius: 18px !important;
        background: rgba(255, 255, 255, .42) !important;
        color: var(--ios-label) !important;
        border: 1px solid var(--ios-stroke) !important;
        box-shadow:
            inset 0 1px 0 var(--ios-hairline),
            0 10px 28px rgba(15, 23, 42, .1) !important;
        -webkit-backdrop-filter: blur(28px) saturate(1.85);
        backdrop-filter: blur(28px) saturate(1.85);
        padding: 11px 12px 12px 10px !important;
    }

    body.ta-ios .bnb-swal-toast::before {
        display: none;
    }

    body.ta-ios .bnb-swal-toast .swal2-title {
        color: inherit !important;
        font-weight: 620 !important;
        font-size: 13.5px !important;
    }

    body.ta-ios .bnb-swal-toast.bnb-toast--success {
        background: rgba(52, 199, 89, .34) !important;
        border-color: rgba(52, 199, 89, .46) !important;
        color: #0c3d1c !important;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .46),
            0 10px 28px rgba(52, 199, 89, .2) !important;
    }

    body.ta-ios .bnb-swal-toast.bnb-toast--error,
    body.ta-ios .bnb-swal-toast.bnb-toast--danger {
        background: rgba(255, 59, 48, .32) !important;
        border-color: rgba(255, 59, 48, .46) !important;
        color: #5c0b08 !important;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .4),
            0 10px 28px rgba(255, 59, 48, .2) !important;
    }

    body.ta-ios .bnb-swal-toast.bnb-toast--warning {
        background: rgba(255, 159, 10, .32) !important;
        border-color: rgba(255, 159, 10, .44) !important;
        color: #6b3d00 !important;
    }

    body.ta-ios .bnb-swal-toast.bnb-toast--info,
    body.ta-ios .bnb-swal-toast.bnb-toast--question {
        background: rgba(0, 122, 255, .24) !important;
        border-color: rgba(0, 122, 255, .38) !important;
        color: #003a75 !important;
    }

    body.ta-ios .bnb-swal-toast .swal2-icon {
        background: rgba(255, 255, 255, .55) !important;
    }

    body.ta-ios .bnb-swal-toast.bnb-toast--success .swal2-icon {
        color: var(--ios-green) !important;
        background: rgba(255, 255, 255, .62) !important;
    }

    body.ta-ios .bnb-swal-toast.bnb-toast--error .swal2-icon,
    body.ta-ios .bnb-swal-toast.bnb-toast--danger .swal2-icon {
        color: var(--ios-red) !important;
        background: rgba(255, 255, 255, .62) !important;
    }

    body.ta-ios .bnb-swal-toast .swal2-close {
        color: var(--ios-secondary) !important;
        border-radius: 999px !important;
    }

    body.ta-ios .bnb-swal-toast .swal2-close:hover {
        background: rgba(255, 255, 255, .45) !important;
        color: var(--ios-label) !important;
    }

    body.ta-ios .bnb-swal-toast.swal2-show,
    body.ta-ios .bnb-swal-toast.swal2-hide,
    body.ta-ios .bnb-swal-popup.swal2-show,
    body.ta-ios .bnb-swal-popup.swal2-hide {
        animation: none !important;
    }

    .bnb-ios-morph-show {
        animation: bnb-ios-morph-show .56s cubic-bezier(.22, 1, .36, 1) both !important;
    }

    @keyframes bnb-ios-morph-show {
        0%, 100% { transform: translateZ(0); }
    }

    .bnb-ios-morph-hide {
        animation: bnb-ios-morph-hide .56s cubic-bezier(.22, 1, .36, 1) both !important;
    }

    @keyframes bnb-ios-morph-hide {
        0%, 100% { transform: translateZ(0); }
    }

    body.ta-ios .bnb-swal-popup.swal2-popup {
        width: 100% !important;
        max-width: none !important;
        padding: 10px 12px 12px !important;
        border-radius: 22px !important;
        border: 1px solid var(--ios-stroke) !important;
        background: var(--ios-glass) !important;
        -webkit-backdrop-filter: blur(28px) saturate(1.85);
        backdrop-filter: blur(28px) saturate(1.85);
        box-shadow:
            inset 0 1px 0 var(--ios-hairline),
            var(--ios-shadow-float) !important;
    }

    body.ta-ios .bnb-swal-popup--bar {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        flex-wrap: nowrap !important;
        gap: 12px;
        min-height: var(--ta-topbar-h);
    }

    body.ta-ios .bnb-swal-popup--bar .swal2-html-container.bnb-swal-html {
        flex: 1 1 auto;
        margin: 0 !important;
        padding: 0 !important;
    }

    body.ta-ios .bnb-swal-popup--bar .bnb-confirm-body {
        align-items: center;
        gap: 12px;
    }

    body.ta-ios .bnb-swal-popup--bar .bnb-confirm-icon {
        width: 38px;
        height: 38px;
        min-width: 38px;
        font-size: 18px;
        background: rgba(255, 255, 255, .55);
    }

    body.ta-ios .bnb-swal-popup--bar .bnb-confirm-title {
        font-size: 15px;
        margin-bottom: 2px;
        color: var(--ios-label);
    }

    body.ta-ios .bnb-swal-popup--bar .bnb-confirm-msg {
        font-size: 13px;
        color: var(--ios-secondary);
    }

    body.ta-ios .bnb-swal-popup--bar .swal2-actions.bnb-swal-actions {
        margin: 0 !important;
        width: auto !important;
        flex: 0 0 auto !important;
        gap: 8px !important;
    }

    body.ta-ios .bnb-swal-popup--bar .swal2-styled.bnb-swal-confirm,
    body.ta-ios .bnb-swal-popup--bar .swal2-styled.bnb-swal-cancel {
        flex: 0 0 auto !important;
        min-width: 92px !important;
        padding: 8px 16px !important;
        border-radius: 12px !important;
    }

    body.ta-ios .bnb-swal-popup--sheet {
        flex-direction: column !important;
        align-items: stretch !important;
        padding: 16px 16px 14px !important;
        max-height: calc(100dvh - var(--bnb-overlay-top, 14px) - 28px);
        overflow: auto;
    }

    body.ta-ios .bnb-swal-popup--sheet .swal2-actions.bnb-swal-actions {
        margin: 16px 0 0 !important;
        width: 100% !important;
    }

    body.ta-ios .bnb-confirm-icon--delete {
        background: rgba(255, 59, 48, .16);
        color: var(--ios-red);
    }

    body.ta-ios .bnb-confirm-icon--warn {
        background: rgba(255, 159, 10, .18);
        color: #c77c00;
    }

    body.ta-ios .bnb-confirm-icon--info {
        background: rgba(0, 122, 255, .14);
        color: var(--ios-blue);
    }

    @media (max-width: 760px) {
        body.ta-ios .bnb-swal-popup--bar {
            flex-direction: column !important;
            align-items: stretch !important;
        }

        body.ta-ios .bnb-swal-popup--bar .swal2-actions.bnb-swal-actions {
            width: 100% !important;
            margin-top: 10px !important;
        }

        body.ta-ios .bnb-swal-popup--bar .swal2-styled.bnb-swal-confirm,
        body.ta-ios .bnb-swal-popup--bar .swal2-styled.bnb-swal-cancel {
            flex: 1 1 0 !important;
        }

        body.ta-ios .bnb-swal-toast.swal2-popup.swal2-toast {
            min-width: 0;
            max-width: 100% !important;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .ta-ios-morph-ghost {
            transition-duration: .01ms !important;
        }

        body.ta-ios .swal2-container.bnb-ios-overlay-container > .swal2-popup {
            transition: none;
            opacity: 1;
        }

        body.ta-ios .form-switch .form-check-input,
        body.ta-ios .form-switch .form-check-input::after {
            transition: none !important;
        }
    }

    .livewire-progress-bar {
        background: var(--ios-blue, #007aff) !important;
        height: 2px !important;
    }

    body.ta-ios .empty-state,
    body.ta-ios .card-body.text-center.py-5 {
        color: var(--ios-tertiary);
    }

    body.ta-ios .card-body.text-center.py-5 .fs-1 {
        opacity: .35;
    }

    body.ta-ios .pagination {
        margin-bottom: 0;
    }

    body.ta-ios code {
        background: var(--ios-fill);
        color: var(--ios-label);
        border-radius: 8px;
        font-weight: 650;
        padding: .12rem .42rem;
    }

    body.ta-ios .list-group-item {
        border-color: var(--ios-separator);
        background: transparent;
        color: var(--ios-label);
        padding: .85rem 1.1rem;
    }

    body.ta-ios .list-group-item-action:hover {
        background: var(--ios-fill);
        color: var(--ios-label);
    }

    body.ta-ios .list-group-item.active {
        background: #fff;
        color: var(--ios-label);
        border-color: transparent;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .08), 0 8px 18px rgba(15, 23, 42, .06);
        border-radius: 14px;
        margin: 4px 8px;
        width: auto;
    }

    body.ta-ios .list-group-flush > .list-group-item.active {
        border-width: 0;
    }

    body.ta-ios .pagination {
        gap: 6px;
    }

    body.ta-ios .page-link {
        border: 0 !important;
        border-radius: 10px !important;
        background: var(--ios-fill);
        color: var(--ios-label);
        min-width: 36px;
        height: 36px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 650;
        padding: 0 .7rem;
    }

    body.ta-ios .page-item.active .page-link {
        background: var(--ios-label);
        color: #fff;
    }

    body.ta-ios .page-item.disabled .page-link {
        background: transparent;
        color: var(--ios-tertiary);
    }

    body.ta-ios .alert {
        border: 0;
        border-radius: 16px;
        background: var(--ios-fill);
        color: var(--ios-label);
    }

    body.ta-ios .alert-success {
        background: rgba(52, 199, 89, .14);
        color: #1f8a3a;
    }

    body.ta-ios .alert-danger {
        background: rgba(255, 59, 48, .12);
        color: var(--ios-red);
    }

    body.ta-ios .alert-warning {
        background: rgba(255, 159, 10, .16);
        color: #9a6700;
    }

    body.ta-ios .alert-info {
        background: rgba(90, 200, 245, .16);
        color: #0071a4;
    }

    body.ta-ios .progress {
        height: 8px;
        border-radius: 99px;
        background: var(--ios-fill);
        overflow: hidden;
    }

    body.ta-ios .progress-bar {
        background: var(--ios-blue) !important;
        border-radius: 99px;
    }

    body.ta-ios .modal-content {
        border-radius: 24px;
        border: 1px solid var(--ios-stroke);
        background: rgba(255, 255, 255, .94);
        box-shadow: 0 24px 64px rgba(15, 23, 42, .2);
        overflow: hidden;
    }

    body.ta-ios .modal-header,
    body.ta-ios .modal-footer {
        border-color: var(--ios-separator);
        background: transparent;
        padding: 16px 20px;
    }

    body.ta-ios .modal-title {
        font-weight: 750;
        letter-spacing: -.02em;
    }

    body.ta-ios .modal-backdrop {
        background: rgba(15, 23, 42, .32);
    }

    body.ta-ios .offcanvas {
        border: 0;
        background: rgba(255, 255, 255, .92);
    }

    body.ta-ios .offcanvas-start,
    body.ta-ios .offcanvas-end {
        border-radius: 22px 0 0 22px;
    }

    [dir="rtl"] body.ta-ios .offcanvas-end {
        border-radius: 22px 0 0 22px;
    }

    [dir="rtl"] body.ta-ios .offcanvas-start {
        border-radius: 0 22px 22px 0;
    }

    body.ta-ios .accordion-item {
        border: 0;
        background: transparent;
    }

    body.ta-ios .accordion-button {
        border-radius: 14px !important;
        background: var(--ios-fill);
        color: var(--ios-label);
        font-weight: 650;
        box-shadow: none !important;
    }

    body.ta-ios .accordion-button:not(.collapsed) {
        background: #fff;
        color: var(--ios-label);
    }

    body.ta-ios .tooltip .tooltip-inner {
        border-radius: 10px;
        background: rgba(28, 28, 30, .92);
        padding: .4rem .7rem;
        font-weight: 520;
    }

    body.ta-ios .admin-overview-stats .ta-stat-card {
        background: rgba(255, 255, 255, .88) !important;
        border-radius: 22px !important;
        min-height: 10.25rem;
        padding: 1.05rem 1.1rem .4rem;
    }

    body.ta-ios .admin-overview-stats .ta-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 14px 32px rgba(15, 23, 42, .1) !important;
    }

    body.ta-ios .admin-overview-stats .ta-stat-card__value {
        letter-spacing: -.03em;
        color: var(--ios-label);
    }

    body.ta-ios .ta-metric {
        padding: 20px;
    }

    body.ta-ios .ta-metric__icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: var(--ios-fill);
        color: var(--ios-label);
    }

    body.ta-ios .ta-metric__label {
        color: var(--ios-tertiary);
        font-weight: 620;
    }

    body.ta-ios .ta-metric__value {
        letter-spacing: -.03em;
        color: var(--ios-label);
    }

    body.ta-ios .city-row:hover {
        background: var(--ios-fill) !important;
    }

    body.ta-ios #iranMap,
    body.ta-ios #medicalIranMap,
    body.ta-ios .med-iran-map,
    body.ta-ios .leaflet-container {
        border-radius: 18px !important;
        border: 1px solid var(--ios-separator) !important;
        overflow: hidden;
        background: #f4f6f9 !important;
    }

    body.ta-ios .datepicker-plot-area {
        border-radius: 18px !important;
        border: 1px solid var(--ios-stroke) !important;
        box-shadow: 0 18px 48px rgba(15, 23, 42, .16) !important;
        overflow: hidden;
    }

    body.ta-ios .swal2-popup {
        font-family: inherit !important;
    }

    body.ta-ios .bnb-swal-popup .swal2-styled.bnb-swal-confirm {
        background: var(--ios-red) !important;
        border: 0 !important;
        border-radius: 12px !important;
        font-weight: 700 !important;
        color: #fff !important;
    }

    body.ta-ios .bnb-swal-popup--generic .swal2-styled.bnb-swal-confirm {
        background: var(--ios-blue) !important;
    }

    body.ta-ios .bnb-swal-popup .swal2-styled.bnb-swal-cancel,
    body.ta-ios .swal2-styled.swal2-cancel {
        background: var(--ios-fill) !important;
        color: var(--ios-label) !important;
        border: 0 !important;
        border-radius: 12px !important;
        font-weight: 650 !important;
    }

    body.ta-ios hr,
    body.ta-ios .border-top {
        border-color: var(--ios-separator) !important;
    }

    body.ta-ios .ta-footer {
        margin: var(--ta-shell-gap);
        margin-top: auto;
        padding: 14px 18px;
        border: 1px solid var(--ios-stroke);
        border-radius: 18px;
        background: rgba(255, 255, 255, .42);
        -webkit-backdrop-filter: blur(18px) saturate(1.5);
        backdrop-filter: blur(18px) saturate(1.5);
        box-shadow: inset 0 1px 0 var(--ios-hairline);
    }

    body.ta-ios .ta-backdrop {
        background: rgba(15, 23, 42, .28);
    }

    @supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
        body.ta-ios .ta-sidebar::before,
        body.ta-ios .ta-topbar,
        body.ta-ios .dropdown-menu {
            background: rgba(255, 255, 255, .92);
        }
    }

    /* ── Collapsed rail ──────────────────────────────────────── */
    @media (min-width: 992px) {
        body.ta-ios.ta-collapsed .ta-sidebar {
            width: var(--ta-sidebar-collapsed-w);
        }

        body.ta-ios.ta-collapsed .ta-main {
            margin-right: calc(var(--ta-sidebar-collapsed-w) + var(--ta-shell-gap) * 2);
        }

        body.ta-ios.ta-collapsed .ta-sidebar__brand {
            justify-content: center;
            padding: 16px 8px 8px;
            min-height: 72px;
        }

        body.ta-ios.ta-collapsed .ta-sidebar__brand-text {
            display: none;
        }

        body.ta-ios.ta-collapsed .ta-sidebar__toggle {
            top: 30px;
            transform: translate(-50%, 0);
        }

        body.ta-ios.ta-collapsed .ta-sidebar__toggle:active {
            transform: translate(-50%, 0) scale(.94);
        }

        body.ta-ios.ta-collapsed .ta-sidebar__toggle-icon {
            transform: scaleX(1);
        }

        [dir="rtl"] body.ta-ios.ta-collapsed .ta-sidebar__toggle-icon {
            transform: scaleX(1);
        }

        body.ta-ios.ta-collapsed .ta-sidebar__section {
            font-size: 0;
            padding: 0;
            margin: 14px 0 8px;
        }

        body.ta-ios.ta-collapsed .ta-submenu-panel,
        body.ta-ios.ta-collapsed .ta-nav-link__arrow {
            display: none !important;
        }

        body.ta-ios.ta-collapsed .ta-nav-group.open {
            background: transparent;
            box-shadow: none;
            padding: 2px;
            margin-bottom: 3px;
        }

        body.ta-ios.ta-collapsed .ta-nav-group.open > .ta-nav-link {
            background: linear-gradient(
                180deg,
                rgba(255, 255, 255, .84) 0%,
                rgba(255, 255, 255, .56) 100%
            );
            box-shadow:
                inset 0 1px 0 var(--ios-hairline),
                inset 0 0 0 0.5px rgba(255, 255, 255, .72);
        }

        body.ta-ios.ta-collapsed .ta-nav-group.open > .ta-nav-link i:first-child {
            color: var(--ios-blue);
        }

        body.ta-ios.ta-collapsed .ta-sidebar__nav {
            padding: 8px 10px;
        }

        body.ta-ios.ta-collapsed .ta-nav-link {
            justify-content: center;
            min-height: 44px;
            padding: 10px 0;
            border-radius: 14px;
        }

        body.ta-ios.ta-collapsed .ta-nav-link--logout {
            border-radius: 50%;
            width: 44px;
            min-height: 44px;
            padding: 0;
            margin: 0 auto;
        }

        body.ta-ios.ta-collapsed .ta-nav-link--logout .ta-nav-link__label {
            display: none;
        }

        body.ta-ios.ta-collapsed .ta-sidebar__foot {
            display: flex;
            justify-content: center;
            padding: 8px 10px 16px;
        }

        body.ta-ios.ta-collapsed .ta-nav-link .badge {
            position: absolute;
            top: 6px;
            left: 8px;
            min-width: 8px;
            height: 8px;
            padding: 0;
            font-size: 0;
            overflow: hidden;
        }

        body.ta-ios.ta-collapsed .ta-sidebar__nav > a.ta-nav-link::after,
        body.ta-ios.ta-collapsed .ta-sidebar__nav .ta-nav-group > .ta-nav-link::after,
        body.ta-ios.ta-collapsed .ta-sidebar__foot .ta-nav-link::after {
            right: calc(100% + 12px);
            background: rgba(28, 28, 30, .92);
            border-radius: 10px;
            padding: 7px 11px;
            font-size: .76rem;
            box-shadow: 0 10px 24px rgba(0, 0, 0, .18);
        }

        body.ta-ios.ta-collapsed .ta-sidebar__foot .ta-nav-link {
            position: relative;
        }

        body.ta-ios.ta-collapsed .ta-sidebar__foot .ta-nav-link::after {
            content: attr(data-label);
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            white-space: nowrap;
            color: #fff;
            font-weight: 500;
            opacity: 0;
            pointer-events: none;
            z-index: 1060;
        }

        body.ta-ios.ta-collapsed .ta-sidebar__foot .ta-nav-link:hover::after {
            opacity: 1;
        }
    }

    /* ── Mobile ──────────────────────────────────────────────── */
    @media (max-width: 991.98px) {
        body.ta-ios {
            --ta-shell-gap: 10px;
        }

        body.ta-ios .ta-main {
            margin-right: 0;
        }

        body.ta-ios .ta-sidebar {
            right: 10px;
            top: 10px;
            bottom: 10px;
            width: min(var(--ta-sidebar-w), calc(100vw - 20px));
            transform: translateX(calc(100% + 24px));
        }

        body.ta-ios .ta-sidebar.show {
            transform: none;
        }

        body.ta-ios .ta-sidebar__toggle {
            display: none !important;
        }

        body.ta-ios .ta-topbar {
            border-radius: 18px;
            padding: 7px 8px;
        }

        body.ta-ios .ta-page {
            padding: 12px var(--ta-shell-gap) var(--ta-shell-gap);
        }
    }

    @media (max-width: 767.98px) {
        body.ta-ios .ta-user {
            padding: 3px;
            gap: 0;
        }

        body.ta-ios .ta-hamburger,
        body.ta-ios .ta-icon-btn,
        body.ta-ios .ta-user__avatar,
        body.ta-ios .ta-breadcrumb-back,
        body.ta-ios .ta-breadcrumb-more {
            width: 40px;
            height: 40px;
        }
    }

    /* ── Dark ────────────────────────────────────────────────── */
    [data-bs-theme="dark"] body.ta-ios {
        --ios-label: #f5f5f7;
        --ios-secondary: rgba(235, 235, 245, .7);
        --ios-tertiary: rgba(235, 235, 245, .46);
        --ios-fill: rgba(120, 120, 128, .24);
        --ios-separator: rgba(235, 235, 245, .16);
        --ios-glass: rgba(22, 22, 24, .62);
        --ios-glass-strong: rgba(36, 36, 38, .78);
        --ios-stroke: rgba(255, 255, 255, .12);
        --ios-hairline: rgba(255, 255, 255, .16);
    }

    html.ta-ios[data-bs-theme="dark"] {
        background: #0c0c10;
    }

    html.ta-ios[data-bs-theme="dark"]::before {
        background:
            radial-gradient(ellipse 78% 52% at 100% -8%, rgba(40, 90, 160, .45), transparent 58%),
            radial-gradient(ellipse 58% 46% at -6% 18%, rgba(30, 90, 70, .35), transparent 54%),
            radial-gradient(ellipse 52% 42% at 78% 108%, rgba(70, 50, 120, .32), transparent 56%),
            linear-gradient(180deg, #121216 0%, #0c0c10 100%);
    }

    [data-bs-theme="dark"] body.ta-ios .ta-sidebar::before,
    [data-bs-theme="dark"] body.ta-ios .ta-topbar {
        background: var(--ios-glass);
    }

    [data-bs-theme="dark"] body.ta-ios .dropdown-menu,
    [data-bs-theme="dark"] body.ta-ios .dashboard-acc-filter__menu {
        background: #2c2c2e !important;
        -webkit-backdrop-filter: none !important;
        backdrop-filter: none !important;
        border-color: var(--ios-stroke);
    }

    [data-bs-theme="dark"] body.ta-ios .dashboard-acc-filter__menu .bg-light,
    [data-bs-theme="dark"] body.ta-ios .dashboard-acc-filter__menu .border-bottom {
        background: #3a3a3c !important;
    }

    [data-bs-theme="dark"] body.ta-ios .ta-nav-group.open {
        background: rgba(255, 255, 255, .08);
        box-shadow: inset 0 0.5px 0 rgba(255, 255, 255, .12);
    }

    [data-bs-theme="dark"] body.ta-ios .ta-nav-group.open > .ta-nav-link {
        background: transparent;
        box-shadow: none;
    }

    [data-bs-theme="dark"] body.ta-ios .ta-nav-link.active,
    [data-bs-theme="dark"] body.ta-ios .ta-submenu a.active {
        background: linear-gradient(
            180deg,
            rgba(255, 255, 255, .16) 0%,
            rgba(255, 255, 255, .08) 100%
        );
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .22),
            inset 0 0 0 0.5px rgba(255, 255, 255, .12);
    }

    [data-bs-theme="dark"] body.ta-ios.ta-collapsed .ta-nav-group.open > .ta-nav-link {
        background: linear-gradient(
            180deg,
            rgba(255, 255, 255, .16) 0%,
            rgba(255, 255, 255, .08) 100%
        );
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .22),
            inset 0 0 0 0.5px rgba(255, 255, 255, .12);
    }

    [data-bs-theme="dark"] body.ta-ios .ta-nav-link--logout,
    [data-bs-theme="dark"] body.ta-ios .ta-page .nav-tabs .nav-link.active,
    [data-bs-theme="dark"] body.ta-ios .ta-sidebar__toggle,
    [data-bs-theme="dark"] body.ta-ios .ta-user,
    [data-bs-theme="dark"] body.ta-ios .ta-icon-btn,
    [data-bs-theme="dark"] body.ta-ios .ta-hamburger {
        background: rgba(58, 58, 60, .92);
        color: var(--ios-label);
        border-color: var(--ios-stroke);
    }

    [data-bs-theme="dark"] body.ta-ios .ta-page .card,
    [data-bs-theme="dark"] body.ta-ios .ta-card,
    [data-bs-theme="dark"] body.ta-ios .ta-metric,
    [data-bs-theme="dark"] body.ta-ios .admin-overview-stats .ta-stat-card,
    [data-bs-theme="dark"] body.ta-ios .modal-content,
    [data-bs-theme="dark"] body.ta-ios .offcanvas {
        background: rgba(28, 28, 30, .86);
        border-color: var(--ios-stroke) !important;
    }

    [data-bs-theme="dark"] body.ta-ios .form-control,
    [data-bs-theme="dark"] body.ta-ios .form-select,
    [data-bs-theme="dark"] body.ta-ios .page-link,
    [data-bs-theme="dark"] body.ta-ios .btn-outline-secondary {
        background: var(--ios-fill);
        color: var(--ios-label);
        border-color: transparent;
    }

    [data-bs-theme="dark"] body.ta-ios .btn-light,
    [data-bs-theme="dark"] body.ta-ios .dashboard-acc-filter > .btn-light,
    [data-bs-theme="dark"] body.ta-ios .btn-group.occ-cal__month-nav > .btn-light {
        background: rgba(58, 58, 60, .92);
        color: var(--ios-label);
        border-color: var(--ios-stroke);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .12),
            0 1px 2px rgba(0, 0, 0, .2);
    }

    [data-bs-theme="dark"] body.ta-ios .btn-group.occ-cal__month-nav > .occ-cal__month-label,
    [data-bs-theme="dark"] body.ta-ios .btn-group.occ-cal__month-nav > .btn.disabled {
        background: transparent !important;
        border: 0 !important;
        box-shadow: none !important;
        color: var(--ios-label) !important;
    }

    [data-bs-theme="dark"] body.ta-ios .btn-check:checked + .btn-outline-secondary,
    [data-bs-theme="dark"] body.ta-ios .btn-outline-secondary.active {
        background: var(--ios-blue) !important;
        color: #fff !important;
    }

    [data-bs-theme="dark"] body.ta-ios .table > thead,
    [data-bs-theme="dark"] body.ta-ios .table thead.table-light {
        --bs-table-bg: #2c2c2e;
        --bs-table-accent-bg: #2c2c2e;
        --bs-table-color: var(--ios-secondary);
    }

    [data-bs-theme="dark"] body.ta-ios .table thead th,
    [data-bs-theme="dark"] body.ta-ios .table thead.table-light th {
        background-color: #2c2c2e !important;
        box-shadow: none !important;
        color: var(--ios-secondary);
    }

    [data-bs-theme="dark"] body.ta-ios .list-group-item.active,
    [data-bs-theme="dark"] body.ta-ios .ta-page .nav-tabs .nav-link.active,
    [data-bs-theme="dark"] body.ta-ios .page-item.active .page-link,
    [data-bs-theme="dark"] body.ta-ios .btn-group > .btn-primary {
        background: rgba(58, 58, 60, .95) !important;
        color: var(--ios-label) !important;
    }

    [data-bs-theme="dark"] body.ta-ios .facility-divar-card,
    [data-bs-theme="dark"] body.ta-ios .bnb-fin-section,
    [data-bs-theme="dark"] body.ta-ios .bnb-fin-service,
    [data-bs-theme="dark"] body.ta-ios .booking-show-details .card.border-primary {
        background: rgba(28, 28, 30, .86);
    }

    [data-bs-theme="dark"] body.ta-ios .bnb-fin-hero {
        background: var(--ios-fill);
    }

    [data-bs-theme="dark"] body.ta-ios .ta-breadcrumb-btn--current {
        background: #f5f5f7;
        color: #1c1c1e;
        border-color: #f5f5f7;
    }

    [data-bs-theme="dark"] body.ta-ios .form-switch .form-check-input:not(:checked) {
        background-color: #39393d !important;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .06);
    }

    @media (prefers-reduced-motion: reduce) {
        body.ta-ios .ta-sidebar,
        body.ta-ios .ta-main,
        body.ta-ios .ta-topbar,
        body.ta-ios .ta-nav-link,
        body.ta-ios .ta-submenu,
        body.ta-ios .ta-submenu-panel {
            transition: none !important;
        }
    }
</style>
@endonce

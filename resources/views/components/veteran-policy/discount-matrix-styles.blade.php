@once
<style>
    .vp-matrix-scroll {
        overflow: auto;
        max-height: min(72vh, 720px);
        width: 100%;
        max-width: 100%;
        -webkit-overflow-scrolling: touch;
    }

    .vp-matrix-scroll .vp-matrix-table {
        width: max-content;
        min-width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 0;
    }

    .vp-matrix-scroll .vp-matrix-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background-color: var(--bs-table-bg, var(--bs-light, #f8f9fa));
        box-shadow: 0 1px 0 var(--bs-table-border-color, var(--bs-border-color, #dee2e6));
    }

    .vp-matrix-scroll .vp-matrix-table .vp-matrix-sticky-col {
        position: sticky;
        inset-inline-end: 0;
        right: 0;
        z-index: 1;
        width: 7.5rem;
        min-width: 6.5rem;
        max-width: 8.5rem;
        white-space: normal;
        word-break: break-word;
        line-height: 1.35;
        padding-inline: .4rem;
        background-color: var(--bs-body-bg, #fff);
        box-shadow: -2px 0 4px -2px rgba(0, 0, 0, 0.1);
    }

    .vp-matrix-scroll .vp-matrix-table thead th.vp-matrix-sticky-col {
        z-index: 4;
        width: 7.5rem;
        min-width: 6.5rem;
        max-width: 8.5rem;
        font-size: .8rem;
        background-color: var(--bs-table-bg, var(--bs-light, #f8f9fa));
    }

    .vp-matrix-scroll .vp-matrix-table tbody td.vp-matrix-sticky-col {
        font-weight: 600;
        vertical-align: middle;
    }

    .vp-matrix-scroll .vp-matrix-table th:not(.vp-matrix-sticky-col),
    .vp-matrix-scroll .vp-matrix-table td:not(.vp-matrix-sticky-col) {
        min-width: 100px;
    }

    [data-bs-theme="dark"] .vp-matrix-scroll .vp-matrix-table .vp-matrix-sticky-col {
        box-shadow: -2px 0 4px -2px rgba(0, 0, 0, 0.4);
    }
</style>
@endonce

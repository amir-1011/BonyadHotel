@once
<style>
    /*
     * Scrollable data tables with Excel-like sticky column headers.
     * Applied globally to Bootstrap .table-responsive wrappers.
     */
    .table-responsive {
        overflow: auto;
        max-height: min(72vh, 720px);
        width: 100%;
        -webkit-overflow-scrolling: touch;
    }

    .modal .table-responsive {
        max-height: min(55vh, 480px);
    }

    .table-responsive > table {
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .table-responsive > table > thead > tr > th {
        position: sticky;
        top: 0;
        z-index: 2;
        background-color: var(--bs-light, #f8f9fa);
        box-shadow: 0 1px 0 var(--bs-border-color, #dee2e6);
    }

    .table-responsive > table > thead:not(.table-light) > tr > th {
        background-color: var(--bs-body-bg, #fff);
    }

    [data-bs-theme="dark"] .table-responsive > table > thead > tr > th {
        background-color: var(--bs-tertiary-bg, #2b3035);
        box-shadow: 0 1px 0 var(--bs-border-color-translucent, rgba(255, 255, 255, 0.15));
    }

    .table-responsive > table > tfoot > tr > th,
    .table-responsive > table > tfoot > tr > td {
        position: sticky;
        bottom: 0;
        z-index: 2;
        background-color: var(--bs-light, #f8f9fa);
        box-shadow: 0 -1px 0 var(--bs-border-color, #dee2e6);
    }

    [data-bs-theme="dark"] .table-responsive > table > tfoot > tr > th,
    [data-bs-theme="dark"] .table-responsive > table > tfoot > tr > td {
        background-color: var(--bs-tertiary-bg, #2b3035);
        box-shadow: 0 -1px 0 var(--bs-border-color-translucent, rgba(255, 255, 255, 0.15));
    }
</style>
@endonce

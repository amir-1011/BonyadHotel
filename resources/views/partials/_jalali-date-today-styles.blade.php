<style>
/* Jalali date input — blue when selected date is today */
input.jalali-date-is-today:not(.is-invalid) {
    border-color: #0d6efd !important;
    color: #0d6efd;
    font-weight: 600;
    background-color: rgba(13, 110, 253, 0.06);
}
input.jalali-date-is-today:focus:not(.is-invalid) {
    border-color: #0d6efd !important;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.2);
}

/* Persian datepicker — today + selected = blue (not dark gray) */
.datepicker-plot-area .datepicker-day-view .table-days td.today.selected span {
    background-color: #0d6efd !important;
    color: #fff !important;
    border: none !important;
    text-shadow: none !important;
}
</style>

@include('partials.booking-payment-records-table', [
    'records' => $records,
    'provinces' => $provinces,
    'terminals' => $terminals,
    'hasActiveFilters' => $hasActiveFilters,
    'panel' => $panel ?? 'host',
])

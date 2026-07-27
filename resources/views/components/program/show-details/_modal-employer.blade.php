@php
    $employer = $program->employer;
@endphp

@include('components.program.show-details._entity-detail-body', [
    'entity' => $employer,
    'type' => 'employer',
    'panel' => $panel,
])

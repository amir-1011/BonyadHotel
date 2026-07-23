@push('scripts')
<script src="{{ Vite::asset('resources/js/program-datepicker.js') }}" data-navigate-once></script>
@endpush

<div>
    <livewire:program-booking-form panel="admin" />
</div>

@props([
    'accommodations' => [],
    'maxVisible' => 3,
])

@if(count($accommodations) > 0)
<div class="veteran-policy-acc-badges d-flex flex-wrap gap-1 mt-1">
    @foreach(array_slice($accommodations, 0, $maxVisible) as $acc)
    <span
        class="badge bg-light text-dark border fw-normal"
        style="font-size:.65rem"
        title="{{ $acc['name'] ?? '' }}">
        <i class="bi bi-building me-1 opacity-75"></i>{{ \Illuminate\Support\Str::limit($acc['name'] ?? '', 24) }}
    </span>
    @endforeach
    @if(count($accommodations) > $maxVisible)
    <span
        class="badge bg-secondary-subtle text-secondary border fw-normal"
        style="font-size:.65rem"
        title="{{ collect(array_slice($accommodations, $maxVisible))->pluck('name')->implode('، ') }}">
        +{{ count($accommodations) - $maxVisible }} اقامتگاه دیگر
    </span>
    @endif
</div>
@endif

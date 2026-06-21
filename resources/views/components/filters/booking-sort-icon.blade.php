@props(['column', 'sort', 'dir'])

@if($sort !== $column)
    <i class="bi bi-arrow-down-up text-muted opacity-50 ms-1" style="font-size:.7rem"></i>
@elseif($dir === 'asc')
    <i class="bi bi-sort-up-alt text-primary ms-1" style="font-size:.8rem"></i>
@else
    <i class="bi bi-sort-down text-primary ms-1" style="font-size:.8rem"></i>
@endif

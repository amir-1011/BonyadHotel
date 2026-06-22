<button type="button"
        class="card shadow-sm border-0 h-100 w-100 text-start booking-detail-summary-card"
        data-bs-toggle="modal"
        data-bs-target="#{{ $modalId }}"
        style="cursor:pointer; transition: box-shadow .15s, transform .15s;">
    <div class="card-body d-flex align-items-start gap-3 py-3">
        <div class="rounded-circle bg-{{ $accent }} bg-opacity-10 text-{{ $accent }} d-flex align-items-center justify-content-center flex-shrink-0"
             style="width:42px;height:42px;font-size:1.1rem">
            <i class="bi bi-{{ $icon }}"></i>
        </div>
        <div class="flex-grow-1 min-w-0">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                <h6 class="fw-semibold mb-0 small">{{ $title }}</h6>
                <i class="bi bi-chevron-left text-muted flex-shrink-0" style="font-size:.75rem"></i>
            </div>
            <div class="text-muted" style="font-size:.8rem;line-height:1.5">
                {!! $summary !!}
            </div>
        </div>
    </div>
</button>

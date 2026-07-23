{{-- Modal shell with Livewire-compatible body (include view, not pre-rendered HTML). --}}
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}-label" aria-hidden="true" wire:ignore.self>
    <div class="modal-dialog modal-dialog-scrollable {{ !empty($size) ? 'modal-' . $size : 'modal-lg' }} modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2 px-3">
                <h5 class="modal-title fs-6 fw-semibold" id="{{ $id }}-label">
                    <i class="bi bi-{{ $icon }} me-2 text-primary"></i>{{ $title }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="بستن"></button>
            </div>
            <div class="modal-body py-3 px-3 small">
                @include($bodyView, $bodyVars)
            </div>
            <div class="modal-footer py-2 px-3">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>

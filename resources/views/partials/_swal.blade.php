{{--
    ██████████████████████████████████████████████████████████
    GLOBAL SWEETALERT2 — TOASTS + CONFIRMATIONS
    Included once in all 3 layouts (admin, host, app).

    Exports:
      window.bnbToast(icon, message)            — floating toast
      window.bnbConfirm(message[, opts])        — returns Promise<SweetAlertResult>
      attribute: data-swal-confirm="message"    — auto-intercepts wire:click / form buttons & submits
    ██████████████████████████████████████████████████████████
--}}

{{-- SweetAlert2 (vendor copy; ~100KB one-time download, cached thereafter) --}}
<link rel="stylesheet" href="{{ asset('vendor/sweetalert2/sweetalert2.min.css') }}">
<script src="{{ asset('vendor/sweetalert2/sweetalert2.min.js') }}"></script>

<script>
(function () {
    /* ── 1. TOAST MIXIN ─────────────────────────────────────── */
    var _Toast = null;
    function getToast() {
        if (!_Toast) {
            _Toast = Swal.mixin({
                toast: true,
                position: 'top-end',          /* top-right; visually top-left in RTL layouts */
                showConfirmButton: false,
                timer: 3500,
                timerProgressBar: true,
                customClass: { popup: 'bnb-swal-toast' },
                didOpen: function (toast) {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
        }
        return _Toast;
    }

    window.bnbToast = function (icon, message) {
        if (!window.Swal) return;
        getToast().fire({ icon: icon || 'success', title: message || '' });
    };

    /* ── 2. CONFIRM HELPER ──────────────────────────────────── */
    window.bnbConfirm = function (message, opts) {
        return Swal.fire(Object.assign({
            title: message || 'آیا مطمئن هستید؟',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-check-lg me-1"></i>بله',
            cancelButtonText: 'انصراف',
            confirmButtonColor: '#dc3545',
            reverseButtons: true,
            focusCancel: true,
            customClass: { popup: 'bnb-swal-popup' },
            didOpen: function (popup) {
                popup.style.fontFamily = 'var(--bnb-font, Vazirmatn, sans-serif)';
                popup.style.direction  = 'rtl';
                /* push z-index above admin/host sidebars */
                var container = document.querySelector('.swal2-container');
                if (container) container.style.zIndex = '9999';
            }
        }, opts || {}));
    };

    /* ── 3. data-swal-confirm INTERCEPTOR ───────────────────── */
    /*
     *  Usage: add  data-swal-confirm="پیام تأیید"  to any button that
     *  also has wire:click or type="submit".  The interceptor shows a
     *  SweetAlert2 confirmation; only fires the real action on "بله".
     *
     *  Uses capture phase so it fires BEFORE Livewire / Alpine handlers.
     */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-swal-confirm]');
        if (!btn || btn._swalBypassed) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        var message = btn.dataset.swalConfirm || 'آیا از این عملیات مطمئن هستید؟';

        bnbConfirm(message).then(function (result) {
            if (!result.isConfirmed) return;
            btn._swalBypassed = true;
            var form = btn.form || btn.closest('form');
            if (form) form._swalBypassed = true;
            btn.click();
            /* restore flag after micro-task so next click is guarded again */
            Promise.resolve().then(function () {
                delete btn._swalBypassed;
                if (form) delete form._swalBypassed;
            });
        });
    }, true /* capture phase */);

    /* ── 3b. FORM SUBMIT INTERCEPTOR (Enter key / programmatic submit) ── */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || form._swalBypassed) return;

        var submitter = e.submitter;
        var el = null;
        if (submitter && submitter.matches('[data-swal-confirm]')) {
            el = submitter;
        } else if (form.matches('[data-swal-confirm]')) {
            el = form;
        }
        if (!el) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        var message = el.dataset.swalConfirm || 'آیا از این عملیات مطمئن هستید؟';

        bnbConfirm(message).then(function (result) {
            if (!result.isConfirmed) return;
            form._swalBypassed = true;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(submitter || undefined);
            } else {
                form.submit();
            }
            Promise.resolve().then(function () { delete form._swalBypassed; });
        });
    }, true);

    /* ── 4. LIVEWIRE TOAST EVENTS ───────────────────────────── */
    /*
     *  Livewire components call:
     *    $this->dispatch('toast', type: 'success', message: 'متن پیام');
     *  This listener shows the toast in the browser.
     */
    window.addEventListener('toast', function (e) {
        var d = e.detail || {};
        /* Livewire 4 wraps named params in an array */
        if (Array.isArray(d) && d.length) d = d[0];
        bnbToast(d.type || d.icon || 'success', d.message || d.title || '');
    });

    /* ── 5. SESSION FLASH AUTO-TOAST (controller/redirect flows) */
    /*
     *  For actions that use session()->flash() and redirect, the layout
     *  outputs the message as an inline <script> call below.
     *  We wrap it in DOMContentLoaded so Swal is ready.
     */
})();
</script>

{{-- Session-based flash toasts (shown on page load after redirect / full render) --}}
@php
    $__swalStatus  = session('status')  ?? session('success');
    $__swalError   = session('error');
    $__swalWarning = session('warning');
@endphp

@if($__swalStatus || $__swalError || $__swalWarning)
<script>
(function () {
    function showSessionToasts() {
        @if($__swalStatus)  bnbToast('success', @json($__swalStatus)); @endif
        @if($__swalError)   bnbToast('error',   @json($__swalError));  @endif
        @if($__swalWarning) bnbToast('warning', @json($__swalWarning));@endif
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showSessionToasts);
    } else {
        showSessionToasts();
    }
})();
</script>
@endif

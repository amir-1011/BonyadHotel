@props([
    'title' => 'راهنما',
])

<span {{ $attributes->merge(['class' => 'admin-column-help d-inline-flex align-items-center flex-shrink-0']) }}
      role="button"
      tabindex="0"
      data-admin-column-help
      data-bs-title="{{ $title }}"
      aria-label="{{ $title }}">
    <i class="bi bi-question-circle text-primary opacity-75" style="font-size:.85rem;cursor:help;line-height:1"></i>
    <template class="admin-column-help-content">{!! trim($slot) !!}</template>
</span>

@once
    @push('styles')
    <style>
        .admin-column-help:focus { outline: none; }
        .admin-column-help:focus-visible i { opacity: 1; box-shadow: 0 0 0 2px rgba(13, 110, 253, .35); border-radius: 50%; }
        .admin-column-help-popover { max-width: min(22rem, 90vw); font-size: .82rem; line-height: 1.65; }
        .admin-column-help-popover .popover-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .5rem;
            font-size: .85rem;
            font-weight: 600;
            padding: .6rem .75rem;
        }
        .admin-column-help-popover .popover-header .admin-column-help-popover-title { flex: 1; min-width: 0; }
        .admin-column-help-popover .admin-column-help-popover-close {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.35rem;
            height: 1.35rem;
            padding: 0;
            border: 0;
            border-radius: 50%;
            background: transparent;
            color: #6c757d;
            line-height: 1;
            cursor: pointer;
        }
        .admin-column-help-popover .admin-column-help-popover-close:hover {
            color: #212529;
            background: rgba(0, 0, 0, .06);
        }
        .admin-column-help-popover .popover-body ul { margin-bottom: 0; padding-right: 1.1rem; }
        .admin-column-help-popover .popover-body li + li { margin-top: .35rem; }
    </style>
    @endpush

    @push('scripts')
    <script>
        window.buildAdminColumnHelpTitle = function (title) {
            return (
                '<span class="admin-column-help-popover-title">' + title + '</span>' +
                '<button type="button" class="admin-column-help-popover-close" aria-label="بستن" title="بستن">' +
                '<i class="bi bi-x-lg" style="font-size:.8rem;line-height:1"></i>' +
                '</button>'
            );
        };

        window.initAdminColumnHelp = function (root) {
            var scope = root && root.querySelectorAll ? root : document;
            scope.querySelectorAll('[data-admin-column-help]').forEach(function (el) {
                var existing = bootstrap.Popover.getInstance(el);
                if (el.dataset.helpReady === '1' && existing) {
                    return;
                }

                if (existing) {
                    existing.dispose();
                }

                delete el.dataset.helpReady;

                var template = el.querySelector('template.admin-column-help-content');
                var content = template ? template.innerHTML.trim() : '';
                if (!content) {
                    return;
                }

                var title = el.dataset.bsTitle || 'راهنما';

                new bootstrap.Popover(el, {
                    title: window.buildAdminColumnHelpTitle(title),
                    content: content,
                    html: true,
                    trigger: 'hover focus click',
                    placement: 'auto',
                    sanitize: false,
                    customClass: 'admin-column-help-popover',
                    container: 'body',
                });

                el.addEventListener('show.bs.popover', function (e) {
                    if (el.classList.contains('admin-column-help--paused')) {
                        e.preventDefault();
                    }
                });

                el.dataset.helpReady = '1';
            });
        };

        window.closeAdminColumnHelpPopover = function (popoverEl) {
            if (!popoverEl) {
                return;
            }

            var popoverId = popoverEl.id;
            var trigger = popoverId
                ? document.querySelector('[aria-describedby="' + popoverId + '"]')
                : null;

            if (trigger) {
                var instance = bootstrap.Popover.getInstance(trigger);
                if (instance && typeof instance.hide === 'function') {
                    trigger.classList.add('admin-column-help--paused');
                    instance.hide();
                    trigger.blur();
                    window.setTimeout(function () {
                        trigger.classList.remove('admin-column-help--paused');
                    }, 500);
                    return;
                }
            }

            popoverEl.classList.remove('show');
        };

        document.addEventListener('click', function (e) {
            var closeBtn = e.target.closest('.admin-column-help-popover-close');
            if (!closeBtn) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();
            window.closeAdminColumnHelpPopover(closeBtn.closest('.popover'));
        }, true);

        document.addEventListener('DOMContentLoaded', function () {
            window.initAdminColumnHelp();
        });

        document.addEventListener('livewire:navigated', function () {
            document.querySelectorAll('[data-admin-column-help]').forEach(function (el) {
                delete el.dataset.helpReady;
                var instance = bootstrap.Popover.getInstance(el);
                if (instance) {
                    instance.dispose();
                }
            });
            window.initAdminColumnHelp();
        });

        document.addEventListener('livewire:init', function () {
            Livewire.hook('commit', function ({ succeed }) {
                succeed(function () {
                    queueMicrotask(function () {
                        window.initAdminColumnHelp();
                    });
                });
            });
        });
    </script>
    @endpush
@endonce

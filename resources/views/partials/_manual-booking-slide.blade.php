@once
<script data-navigate-once>
(function () {
    if (window.__mbfStepSlideBound) return;
    window.__mbfStepSlideBound = true;

    var EASE = 'cubic-bezier(.22, 1, .36, 1)';
    var DURATION = 560;

    function prefersReducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function formFromComponent(component) {
        var el = component && component.el;
        return el && el.id === 'manual-booking-form' ? el : null;
    }

    function livePane(viewport) {
        if (!viewport) return null;
        return viewport.querySelector('.mbf-step-pane:not(.mbf-step-pane--ghost)');
    }

    function sanitizeGhost(ghost) {
        ghost.classList.add('mbf-step-pane--ghost');
        ghost.setAttribute('aria-hidden', 'true');
        ghost.querySelectorAll('script').forEach(function (node) { node.remove(); });
        [ghost].concat(Array.prototype.slice.call(ghost.querySelectorAll('*'))).forEach(function (node) {
            Array.prototype.slice.call(node.attributes).forEach(function (attr) {
                var name = attr.name;
                if (
                    name === 'id' ||
                    name.indexOf('wire:') === 0 ||
                    name.indexOf('x-') === 0 ||
                    name.indexOf('@') === 0 ||
                    name === 'data-mbf-step'
                ) {
                    node.removeAttribute(name);
                }
            });
        });
    }

    function cleanup(viewport, incoming) {
        if (viewport) {
            viewport.querySelectorAll('.mbf-step-pane--ghost').forEach(function (node) { node.remove(); });
            viewport.classList.remove('is-sliding');
            viewport.style.height = '';
            viewport.style.transition = '';
            var form = viewport.closest('#manual-booking-form');
            if (form) form.classList.remove('mbf-is-sliding');
        }
        if (incoming) {
            incoming.style.transition = '';
            incoming.style.transform = '';
            incoming.style.opacity = '';
            incoming.style.willChange = '';
        }
    }

    function animate(form, viewport, incoming, ghost, dir, fromH) {
        var reduced = prefersReducedMotion();
        var ms = reduced ? 180 : DURATION;
        var incomingFrom = dir > 0 ? '-108%' : '108%';
        var outgoingTo = dir > 0 ? '108%' : '-108%';

        form.classList.add('mbf-is-sliding');
        viewport.classList.add('is-sliding');
        viewport.style.transition = 'none';
        viewport.style.height = fromH + 'px';
        ghost.style.transition = 'none';
        ghost.style.transform = 'translate3d(0,0,0)';
        incoming.style.transition = 'none';
        incoming.style.willChange = 'transform';
        ghost.style.willChange = 'transform, opacity';

        if (reduced) {
            incoming.style.opacity = '0';
            incoming.style.transform = 'none';
        } else {
            incoming.style.opacity = '1';
            incoming.style.transform = 'translate3d(' + incomingFrom + ',0,0)';
        }

        viewport.appendChild(ghost);
        var toH = incoming.offsetHeight || fromH;
        void viewport.offsetHeight;

        var done = false;
        var finish = function () {
            if (done) return;
            done = true;
            incoming.removeEventListener('transitionend', onEnd);
            cleanup(viewport, incoming);
        };
        var onEnd = function (event) {
            if (event.target !== incoming) return;
            if (event.propertyName !== 'transform' && event.propertyName !== 'opacity') return;
            finish();
        };
        incoming.addEventListener('transitionend', onEnd);
        setTimeout(finish, ms + 90);

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                viewport.style.transition = 'height ' + ms + 'ms ' + EASE;
                viewport.style.height = toH + 'px';
                ghost.style.transition = 'transform ' + ms + 'ms ' + EASE + ', opacity ' + ms + 'ms ' + EASE;
                incoming.style.transition = 'transform ' + ms + 'ms ' + EASE + ', opacity ' + ms + 'ms ' + EASE;
                if (reduced) {
                    ghost.style.opacity = '0';
                    incoming.style.opacity = '1';
                } else {
                    ghost.style.transform = 'translate3d(' + outgoingTo + ',0,0)';
                    ghost.style.opacity = '0';
                    incoming.style.transform = 'translate3d(0,0,0)';
                }
            });
        });
    }

    function playIfNeeded(form) {
        var state = form && form._mbfSlide;
        if (!state || state.playing) return;
        var viewport = form.querySelector('.mbf-step-viewport');
        var incoming = livePane(viewport);
        if (!viewport || !incoming) return;
        if (incoming.getAttribute('data-mbf-step') === state.fromStep) return;

        state.playing = true;
        form._mbfSlide = null;
        animate(form, viewport, incoming, state.ghost, state.dir, state.fromH);
    }

    function clearIfUnchanged(form) {
        var state = form && form._mbfSlide;
        if (!state || state.playing) return;
        var incoming = livePane(form.querySelector('.mbf-step-viewport'));
        if (!incoming || incoming.getAttribute('data-mbf-step') !== state.fromStep) return;
        form._mbfSlide = null;
        delete form.dataset.mbfPendingDir;
    }

    document.addEventListener('click', function (event) {
        var btn = event.target.closest && event.target.closest('[data-mbf-slide]');
        if (!btn || btn.disabled || btn.getAttribute('disabled') !== null) return;
        var form = btn.closest('#manual-booking-form');
        if (!form) return;
        form.dataset.mbfPendingDir = btn.getAttribute('data-mbf-slide') || '';
    }, true);

    function attach() {
        if (typeof Livewire === 'undefined' || typeof Livewire.hook !== 'function') return;
        if (window.__mbfStepSlideHooked) return;
        window.__mbfStepSlideHooked = true;

        Livewire.hook('commit', function (info) {
            var form = formFromComponent(info.component);
            if (!form) return;

            var dir = parseInt(form.dataset.mbfPendingDir || '0', 10) || 0;
            delete form.dataset.mbfPendingDir;
            if (!dir) return;

            var viewport = form.querySelector('.mbf-step-viewport');
            var pane = livePane(viewport);
            if (!viewport || !pane) return;

            var ghost = pane.cloneNode(true);
            sanitizeGhost(ghost);
            ghost.style.width = pane.offsetWidth + 'px';

            form._mbfSlide = {
                dir: dir,
                fromStep: pane.getAttribute('data-mbf-step'),
                fromH: pane.offsetHeight,
                ghost: ghost,
                playing: false
            };

            var schedulePlay = function () {
                playIfNeeded(form);
            };

            if (typeof info.succeed === 'function') {
                info.succeed(function () {
                    queueMicrotask(schedulePlay);
                    requestAnimationFrame(function () {
                        requestAnimationFrame(schedulePlay);
                    });
                    setTimeout(schedulePlay, 80);
                    setTimeout(function () { clearIfUnchanged(form); }, 220);
                });
            }
            if (typeof info.fail === 'function') {
                info.fail(function () {
                    form._mbfSlide = null;
                });
            }
        });

        Livewire.hook('morph.updated', function (payload) {
            var el = payload && payload.el;
            var form = el && (el.id === 'manual-booking-form' ? el : el.closest && el.closest('#manual-booking-form'));
            if (form) playIfNeeded(form);
        });

        Livewire.hook('morph.added', function (payload) {
            var el = payload && payload.el;
            if (!el || !el.classList || !el.classList.contains('mbf-step-pane')) return;
            var form = el.closest && el.closest('#manual-booking-form');
            if (form) playIfNeeded(form);
        });
    }

    document.addEventListener('livewire:initialized', attach);
    attach();
})();
</script>
@endonce

const EASE = 'cubic-bezier(0.32, 0.72, 0, 1)';
const OPEN_MS = 480;
const CLOSE_MS = 480;

function getOverlay() {
    return document.getElementById('facility-detail-overlay');
}

function getPanel() {
    const overlay = getOverlay();
    return overlay?.querySelector('.facility-detail-panel') ?? null;
}

export function facilityDetailSetOrigin(cardEl) {
    if (!cardEl) {
        return;
    }

    const rect = cardEl.getBoundingClientRect();
    window.__facilityDetailOrigin = {
        top: rect.top,
        left: rect.left,
        width: rect.width,
        height: rect.height,
    };
}

function targetPanelGeometry() {
    const width = Math.min(480, window.innerWidth - 24);
    const maxHeight = Math.min(680, window.innerHeight - 24);

    return {
        left: (window.innerWidth - width) / 2,
        width,
        maxHeight,
    };
}

function applyPanelFrame(panel, frame, radius) {
    panel.style.top = `${frame.top}px`;
    panel.style.left = `${frame.left}px`;
    panel.style.width = `${frame.width}px`;
    panel.style.height = `${frame.height}px`;
    panel.style.borderRadius = `${radius}px`;
}

function lockPanelToCurrentFrame(panel) {
    const rect = panel.getBoundingClientRect();
    applyPanelFrame(panel, {
        top: rect.top,
        left: rect.left,
        width: rect.width,
        height: rect.height,
    }, parseFloat(getComputedStyle(panel).borderRadius) || 20);
}

function resetPanelMeasureStyles(panel) {
    const inner = panel.querySelector('.facility-detail-panel__inner');
    const scroll = panel.querySelector('.facility-detail-scroll');

    panel.style.height = '';
    panel.style.maxHeight = '';

    if (inner) {
        inner.style.height = '';
        inner.style.display = '';
    }

    if (scroll) {
        scroll.style.flex = '';
        scroll.style.overflow = '';
        scroll.style.height = '';
        scroll.style.maxHeight = '';
    }
}

function measureContentHeight(panel, maxHeight, targetWidth) {
    const inner = panel.querySelector('.facility-detail-panel__inner');
    const scroll = panel.querySelector('.facility-detail-scroll');

    if (!scroll || !inner) {
        return maxHeight;
    }

    const saved = {
        fitted: panel.classList.contains('is-fitted'),
        scrollable: panel.classList.contains('is-scrollable'),
        panelWidth: panel.style.width,
        panelHeight: panel.style.height,
        panelMaxHeight: panel.style.maxHeight,
        panelTop: panel.style.top,
        panelLeft: panel.style.left,
        panelVisibility: panel.style.visibility,
        innerDisplay: inner.style.display,
        innerHeight: inner.style.height,
    };

    panel.classList.remove('is-fitted', 'is-scrollable');
    resetPanelMeasureStyles(panel);

    panel.style.visibility = 'hidden';
    panel.style.top = '0';
    panel.style.left = '0';
    panel.style.width = `${targetWidth}px`;
    panel.style.height = 'auto';
    panel.style.maxHeight = 'none';

    inner.style.display = 'block';
    inner.style.height = 'auto';

    scroll.style.flex = 'none';
    scroll.style.height = 'auto';
    scroll.style.maxHeight = 'none';
    scroll.style.overflow = 'visible';

    const measured = Math.ceil(scroll.scrollHeight);

    panel.style.width = saved.panelWidth;
    panel.style.height = saved.panelHeight;
    panel.style.maxHeight = saved.panelMaxHeight;
    panel.style.top = saved.panelTop;
    panel.style.left = saved.panelLeft;
    panel.style.visibility = saved.panelVisibility;
    inner.style.display = saved.innerDisplay;
    inner.style.height = saved.innerHeight;
    resetPanelMeasureStyles(panel);

    if (saved.fitted) {
        panel.classList.add('is-fitted');
    }

    if (saved.scrollable) {
        panel.classList.add('is-scrollable');
    }

    return Math.min(measured, maxHeight);
}

function applyScrollableState(panel, target, frame) {
    const scroll = panel.querySelector('.facility-detail-scroll');

    if (frame.height >= target.maxHeight) {
        panel.classList.add('is-scrollable');
    } else {
        panel.classList.remove('is-scrollable');
    }

    if (scroll) {
        scroll.style.maxHeight = '';
        scroll.style.height = '';
    }
}

function computeFittedFrame(panel, target) {
    const height = measureContentHeight(panel, target.maxHeight, target.width);
    const frame = {
        top: Math.max(12, (window.innerHeight - height) / 2),
        left: target.left,
        width: target.width,
        height,
    };

    panel.classList.add('is-fitted');
    applyScrollableState(panel, target, frame);

    return frame;
}

function applyFittedPanelSize(panel, target) {
    const frame = computeFittedFrame(panel, target);

    panel.style.maxHeight = `${target.maxHeight}px`;
    panel.style.height = `${frame.height}px`;
    panel.style.top = `${frame.top}px`;

    return frame;
}

function destroyFacilityCarousel(panel) {
    const carousel = panel.querySelector('[data-facility-carousel]');
    if (!carousel || !carousel.__facilityCarouselCleanup) {
        return;
    }

    carousel.__facilityCarouselCleanup();
    delete carousel.__facilityCarouselCleanup;
}

function initFacilityCarousel(panel) {
    destroyFacilityCarousel(panel);

    const carousel = panel.querySelector('[data-facility-carousel]');
    if (!carousel) {
        return;
    }

    const track = carousel.querySelector('[data-facility-carousel-track]');
    const viewport = carousel.querySelector('.facility-detail-carousel__viewport');
    const slides = carousel.querySelectorAll('[data-facility-carousel-slide]');
    const dots = carousel.querySelectorAll('[data-facility-carousel-dot]');

    if (!track || slides.length <= 1) {
        return;
    }

    const abort = new AbortController();
    const { signal } = abort;
    carousel.__facilityCarouselCleanup = () => abort.abort();

    let index = 0;
    let startX = 0;
    let deltaX = 0;
    let dragging = false;
    let activePointerId = null;

    const goTo = (nextIndex) => {
        index = Math.max(0, Math.min(slides.length - 1, nextIndex));
        track.style.transform = `translate3d(${index * -100}%, 0, 0)`;
        dots.forEach((dot, dotIndex) => {
            dot.classList.toggle('is-active', dotIndex === index);
        });
    };

    dots.forEach((dot) => {
        dot.addEventListener('click', () => {
            goTo(Number.parseInt(dot.dataset.facilityCarouselDot ?? '0', 10));
        }, { signal });
    });

    carousel.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'mouse' && event.button !== 0) {
            return;
        }

        event.preventDefault();
        dragging = true;
        activePointerId = event.pointerId;
        startX = event.clientX;
        deltaX = 0;
        track.style.transition = 'none';
        carousel.classList.add('is-dragging');
        carousel.setPointerCapture(event.pointerId);
    }, { signal });

    carousel.addEventListener('pointermove', (event) => {
        if (!dragging || event.pointerId !== activePointerId) {
            return;
        }

        event.preventDefault();
        deltaX = event.clientX - startX;
        const width = (viewport || carousel).clientWidth || 1;
        const offsetPercent = (-index * 100) + ((deltaX / width) * 100);
        track.style.transform = `translate3d(${offsetPercent}%, 0, 0)`;
    }, { signal });

    const endDrag = (event) => {
        if (!dragging || (event && event.pointerId !== activePointerId)) {
            return;
        }

        dragging = false;
        activePointerId = null;
        carousel.classList.remove('is-dragging');
        track.style.transition = `transform 0.25s ${EASE}`;

        if (deltaX < -40) {
            goTo(index + 1);
        } else if (deltaX > 40) {
            goTo(index - 1);
        } else {
            goTo(index);
        }

        deltaX = 0;
    };

    carousel.addEventListener('pointerup', endDrag, { signal });
    carousel.addEventListener('pointercancel', endDrag, { signal });
    carousel.addEventListener('lostpointercapture', endDrag, { signal });

    carousel.addEventListener('dragstart', (event) => {
        event.preventDefault();
    }, { signal });

    track.style.transition = `transform 0.25s ${EASE}`;
    goTo(0);
}

function refitAfterImageLoad(panel, target) {
    const images = panel.querySelectorAll('.facility-detail-hero__image');
    if (images.length === 0) {
        return;
    }

    const refit = () => {
        if (!panel.classList.contains('is-expanded')) {
            return;
        }

        const frame = applyFittedPanelSize(panel, target);
        panel.style.transition = `top 0.22s ${EASE}, height 0.22s ${EASE}`;
        panel.style.top = `${frame.top}px`;
        panel.style.height = `${frame.height}px`;
    };

    let pending = 0;

    images.forEach((img) => {
        if (img.complete) {
            return;
        }

        pending += 1;
        img.addEventListener('load', () => {
            pending -= 1;
            if (pending <= 0) {
                refit();
            }
        }, { once: true });
        img.addEventListener('error', () => {
            pending -= 1;
            if (pending <= 0) {
                refit();
            }
        }, { once: true });
    });
}

function animateOpen() {
    const overlay = getOverlay();
    const panel = getPanel();

    if (!overlay || !panel) {
        return;
    }

    const origin = window.__facilityDetailOrigin;
    const target = targetPanelGeometry();

    overlay.classList.remove('is-closing');
    overlay.classList.add('is-visible');
    document.body.classList.add('facility-detail-open');

    panel.classList.remove('is-closing', 'is-fitted', 'is-scrollable');
    panel.style.transition = 'none';
    panel.style.visibility = 'hidden';
    panel.classList.remove('is-expanded');

    const scroll = panel.querySelector('.facility-detail-scroll');
    if (scroll) {
        scroll.style.height = 'auto';
        scroll.style.maxHeight = 'none';
    }

    panel.classList.add('is-expanded');

    const initCarousel = () => initFacilityCarousel(panel);

    const finalFrame = computeFittedFrame(panel, target);
    panel.style.maxHeight = `${target.maxHeight}px`;

    if (origin) {
        applyPanelFrame(panel, origin, 4);
        panel.style.visibility = 'visible';
        initCarousel();

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                panel.style.transition = `top ${OPEN_MS}ms ${EASE}, left ${OPEN_MS}ms ${EASE}, width ${OPEN_MS}ms ${EASE}, height ${OPEN_MS}ms ${EASE}, border-radius ${OPEN_MS}ms ${EASE}`;
                applyPanelFrame(panel, finalFrame, 20);

                window.setTimeout(() => {
                    refitAfterImageLoad(panel, target);
                }, OPEN_MS);
            });
        });
    } else {
        panel.style.visibility = 'visible';
        applyPanelFrame(panel, finalFrame, 20);
        initCarousel();
        refitAfterImageLoad(panel, target);
    }
}

function animateClose(onDone) {
    const overlay = getOverlay();
    const panel = getPanel();
    const origin = window.__facilityDetailOrigin;

    if (!overlay || !panel) {
        onDone?.();
        return;
    }

    if (!origin) {
        overlay.classList.remove('is-visible', 'is-closing');
        document.body.classList.remove('facility-detail-open');
        panel.classList.remove('is-expanded', 'is-closing', 'is-fitted', 'is-scrollable');
        resetPanelMeasureStyles(panel);
        onDone?.();
        return;
    }

    overlay.classList.add('is-closing');
    panel.classList.add('is-closing');
    panel.classList.remove('is-expanded', 'is-fitted', 'is-scrollable');
    destroyFacilityCarousel(panel);

    lockPanelToCurrentFrame(panel);

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            panel.style.transition = `top ${CLOSE_MS}ms ${EASE}, left ${CLOSE_MS}ms ${EASE}, width ${CLOSE_MS}ms ${EASE}, height ${CLOSE_MS}ms ${EASE}, border-radius ${CLOSE_MS}ms ${EASE}`;
            applyPanelFrame(panel, origin, 4);
        });
    });

    window.setTimeout(() => {
        overlay.classList.remove('is-visible', 'is-closing');
        document.body.classList.remove('facility-detail-open');
        panel.classList.remove('is-closing');
        panel.style.transition = 'none';
        resetPanelMeasureStyles(panel);
        onDone?.();
    }, CLOSE_MS);
}

function scheduleOpen() {
    let tries = 0;

    const attempt = () => {
        if (getOverlay()) {
            animateOpen();
            return;
        }

        if (tries++ < 20) {
            window.requestAnimationFrame(attempt);
        }
    };

    attempt();
}

function closeLivewireDetail() {
    const overlay = getOverlay();
    const componentId = overlay?.dataset.livewireId;

    if (!componentId || !window.Livewire) {
        return;
    }

    window.Livewire.find(componentId)?.call('closeDetail');
}

function bindCloseRequest() {
    document.addEventListener('facility-detail-close-requested', () => {
        animateClose(closeLivewireDetail);
    });
}

function bindOpenRequest() {
    document.addEventListener('facility-detail-opened', () => scheduleOpen());
}

function bindLivewireHooks() {
    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('morph.added', ({ el }) => {
            if (el?.id === 'facility-detail-overlay') {
                scheduleOpen();
            }
        });

        window.Livewire.on('facility-detail-opened', () => scheduleOpen());

        window.Livewire.on('facility-detail-close-requested', () => {
            animateClose(closeLivewireDetail);
        });
    });
}

function initFacilityDetailModal() {
    bindOpenRequest();
    bindCloseRequest();
    bindLivewireHooks();
}

initFacilityDetailModal();

window.facilityDetailSetOrigin = facilityDetailSetOrigin;
window.facilityDetailScheduleOpen = scheduleOpen;
window.facilityDetailRequestClose = function () {
    document.dispatchEvent(new CustomEvent('facility-detail-close-requested'));
};

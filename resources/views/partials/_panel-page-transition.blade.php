<style>
@media (prefers-reduced-motion: no-preference) {
    .ta-rise-item {
        opacity: 0;
        transform: translateY(22px);
        animation: ta-rise-in 0.42s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        animation-delay: calc(var(--ta-rise-i, 0) * 55ms);
    }

    @keyframes ta-rise-in {
        to {
            opacity: 1;
            transform: none;
        }
    }
}
</style>
<script data-navigate-once>
(function () {
  if (window.__taPanelPageTransitionBound) return;
  window.__taPanelPageTransitionBound = true;

  var MAX_ITEMS = 24;
  var BLOCK_SELECTOR = 'script, style, template, noscript, [wire\\:ignore], [wire\\:snapshot], .dropdown-menu, .modal, .offcanvas, .ta-sidebar, .ta-topbar';

  function isVisible(el) {
    if (!el || !el.getBoundingClientRect) return false;
    if (el.matches(BLOCK_SELECTOR)) return false;
    var style = window.getComputedStyle(el);
    if (style.display === 'none' || style.visibility === 'hidden') return false;
    var rect = el.getBoundingClientRect();
    return rect.width > 0 || rect.height > 0;
  }

  function dedupeNested(items) {
    return items.filter(function (el) {
      return !items.some(function (other) {
        return other !== el && other.contains(el);
      });
    });
  }

  function collectFromChildren(parent) {
    return dedupeNested(Array.from(parent.children).filter(isVisible));
  }

  function collectItems(page) {
    var root = page.firstElementChild;
    if (!root) return [];

    var items = collectFromChildren(root);

    if (items.length === 1) {
      var inner = collectFromChildren(items[0]);
      if (inner.length >= 2) items = inner;
    }

    if (items.length < 2) {
      items = dedupeNested(Array.from(root.querySelectorAll(
        '.ta-page-head, .card.shadow-sm, .ta-card, .ta-stat-card, .admin-overview-stats__col, .facility-listing-grid > *, .row.g-3 > [class*="col-"], .row.g-4 > [class*="col-"]'
      )).filter(isVisible));
    }

    if (!items.length && isVisible(root)) items = [root];

    return items.slice(0, MAX_ITEMS);
  }

  function clearRise(page) {
    page.querySelectorAll('.ta-rise-item').forEach(function (el) {
      el.classList.remove('ta-rise-item');
      el.style.removeProperty('--ta-rise-i');
    });
  }

  function runTransition() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var page = document.querySelector('.ta-page');
    if (!page) return;

    clearRise(page);

    var items = collectItems(page);
    if (!items.length) return;

    items.forEach(function (el, index) {
      el.style.setProperty('--ta-rise-i', String(index));
      el.classList.add('ta-rise-item');
      el.addEventListener('animationend', function onEnd(event) {
        if (event.target !== el) return;
        el.classList.remove('ta-rise-item');
        el.style.removeProperty('--ta-rise-i');
      }, { once: true });
    });
  }

  function scheduleTransition() {
    requestAnimationFrame(function () {
      requestAnimationFrame(runTransition);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scheduleTransition);
  } else {
    scheduleTransition();
  }

  document.addEventListener('livewire:navigated', scheduleTransition);
})();
</script>

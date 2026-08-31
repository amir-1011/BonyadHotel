<script>
(function () {
  function normalizeJalali(value) {
    if (window.BonyadJalaliDate && typeof window.BonyadJalaliDate.normalizeJalaliDigits === 'function') {
      return window.BonyadJalaliDate.normalizeJalaliDigits(value);
    }

    return String(value || '')
      .replace(/[۰-۹]/g, function (d) {
        return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
      })
      .trim();
  }

  function addNightsToJalali(value, nights) {
    if (typeof persianDate === 'undefined') {
      return null;
    }

    const normalized = normalizeJalali(value);
    const parts = normalized.split('/');

    if (parts.length !== 3) {
      return null;
    }

    const year = parseInt(parts[0], 10);
    const month = parseInt(parts[1], 10);
    const day = parseInt(parts[2], 10);

    if (!year || !month || !day || nights === 0) {
      return null;
    }

    try {
      return new persianDate([year, month, day]).add('day', nights).format('YYYY/MM/DD');
    } catch (e) {
      return null;
    }
  }

  function findLivewireComponent(el) {
    const root = el.closest('[wire\\:id]');
    const componentId = root ? root.getAttribute('wire:id') : null;

    if (!componentId || typeof Livewire === 'undefined' || typeof Livewire.find !== 'function') {
      return null;
    }

    return Livewire.find(componentId);
  }

  window.bnbStayExtensionAddNights = function (button, nights) {
    const form = button.closest('[data-stay-extension-form]');
    const input = form ? form.querySelector('[data-stay-extension-input]') : null;

    if (!input) {
      return;
    }

    const baseValue = input.value || input.dataset.defaultCheckout || '';
    const nextValue = addNightsToJalali(baseValue, nights);

    if (!nextValue) {
      return;
    }

    const modal = button.closest('.modal');
    if (modal && modal.id) {
      window.__bnbKeepModalOpenId = modal.id;
    }

    const component = findLivewireComponent(input);

    if (component) {
      component.set('extendCheckOutJalali', nextValue);
      return;
    }

    input.value = nextValue;
    input.dispatchEvent(new Event('input', { bubbles: true }));
  };
})();
</script>

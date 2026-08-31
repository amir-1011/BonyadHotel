<script>
(function () {
  let openModalIdBeforeMorph = null;

  function cleanupOrphanBootstrapModalLocks() {
    if (document.querySelectorAll('.modal.show').length > 0) {
      return;
    }

    document.querySelectorAll('.modal-backdrop').forEach(function (el) {
      el.remove();
    });
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
  }

  function restoreOpenBootstrapModal() {
    const modalId = openModalIdBeforeMorph || window.__bnbKeepModalOpenId;
    if (!modalId || !window.bootstrap?.Modal) {
      return;
    }

    const modalEl = document.getElementById(modalId);
    if (!modalEl || modalEl.classList.contains('show')) {
      return;
    }

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }

  function rememberOpenModal() {
    const openModal = document.querySelector('.modal.show');
    openModalIdBeforeMorph = openModal ? openModal.id : null;
    if (openModalIdBeforeMorph) {
      window.__bnbKeepModalOpenId = openModalIdBeforeMorph;
    }
  }

  function afterLivewireMorph() {
    requestAnimationFrame(function () {
      restoreOpenBootstrapModal();
      cleanupOrphanBootstrapModalLocks();
    });
    setTimeout(function () {
      restoreOpenBootstrapModal();
      cleanupOrphanBootstrapModalLocks();
    }, 50);
    setTimeout(function () {
      restoreOpenBootstrapModal();
      cleanupOrphanBootstrapModalLocks();
    }, 250);
  }

  function registerLivewireModalGuard() {
    if (typeof Livewire === 'undefined' || typeof Livewire.hook !== 'function') {
      return;
    }

    if (window.__bnbModalLivewireGuardRegistered) {
      return;
    }

    window.__bnbModalLivewireGuardRegistered = true;

    Livewire.hook('morph.updating', rememberOpenModal);
    Livewire.hook('morph.updated', afterLivewireMorph);
  }

  window.bnbPreserveBootstrapModalOpen = function (modalId) {
    if (modalId) {
      window.__bnbKeepModalOpenId = modalId;
    }
    rememberOpenModal();
    afterLivewireMorph();
  };

  document.addEventListener('livewire:init', registerLivewireModalGuard);
  document.addEventListener('livewire:initialized', registerLivewireModalGuard);
  document.addEventListener('hidden.bs.modal', function (event) {
    if (event.target && event.target.classList && event.target.classList.contains('modal')) {
      if (window.__bnbKeepModalOpenId === event.target.id) {
        window.__bnbKeepModalOpenId = null;
      }
      setTimeout(cleanupOrphanBootstrapModalLocks, 50);
    }
  });

  registerLivewireModalGuard();
})();
</script>

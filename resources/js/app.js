import './bootstrap';

// ── Swiper ────────────────────────────────────────────────────────────────
// Alpine.js is provided by Livewire 4 automatically — do NOT import here
import Swiper from 'swiper';
import { Navigation, Pagination, A11y } from 'swiper/modules';

window.Swiper = Swiper;
window.SwiperModules = { Navigation, Pagination, A11y };

// ── Card Swipers ──────────────────────────────────────────────────────────
function initCardSwipers() {
    document.querySelectorAll('.bnb-card-swiper').forEach((el) => {
        if (el._swiperInstance) return; // already initialized
        const swiper = new Swiper(el, {
            modules: [Pagination],
            pagination: { el: el.querySelector('.swiper-pagination'), clickable: true },
            loop: true,
        });
        el._swiperInstance = swiper;
    });
}

// ── Init ──────────────────────────────────────────────────────────────────
function initPage() {
    initCardSwipers();
}

// Initial load and after every Livewire wire:navigate page transition
document.addEventListener('DOMContentLoaded', initPage);
document.addEventListener('livewire:navigated', initPage);

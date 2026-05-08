import './bootstrap';

// ── Swiper ────────────────────────────────────────────────────────────────
import Swiper from 'swiper';
import { Navigation, Pagination, A11y } from 'swiper/modules';

window.Swiper = Swiper;
window.SwiperModules = { Navigation, Pagination, A11y };

// ── AOS ───────────────────────────────────────────────────────────────────
import AOS from 'aos';
window.AOS = AOS;

// ── Alpine.js ─────────────────────────────────────────────────────────────
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

// ── Init on DOM ready ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Init AOS
    AOS.init({
        duration: 600,
        once: true,
        offset: 40,
        easing: 'ease-out-cubic',
    });

    // Navbar scroll shadow
    const navbar = document.querySelector('.bnb-navbar');
    if (navbar) {
        const handleScroll = () => {
            navbar.classList.toggle('scrolled', window.scrollY > 10);
        };
        window.addEventListener('scroll', handleScroll, { passive: true });
        handleScroll();
    }

    // Init all card swipers (photo galleries)
    document.querySelectorAll('.bnb-card-swiper').forEach((el) => {
        new Swiper(el, {
            modules: [Pagination],
            pagination: { el: el.querySelector('.swiper-pagination'), clickable: true },
            loop: true,
        });
    });
});

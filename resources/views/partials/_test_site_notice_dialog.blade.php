@if(config('test_site.enabled'))
<style>
.bnb-test-site-notice.swal2-popup {
    width: min(460px, calc(100vw - 32px)) !important;
    max-width: 460px !important;
    padding: 28px 28px 24px !important;
    border-radius: 20px !important;
    border: none !important;
    background: #ffffff !important;
    color: #1e293b !important;
    box-shadow:
        0 4px 6px -2px rgba(0, 0, 0, 0.05),
        0 20px 40px -8px rgba(0, 0, 0, 0.18) !important;
    font-family: 'Vazirmatn', Tahoma, sans-serif !important;
    direction: rtl !important;
}
body.ta-ios .bnb-test-site-notice.swal2-popup {
    width: min(460px, calc(100vw - 32px)) !important;
    max-width: 460px !important;
    background: #ffffff !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
    border: none !important;
}
.bnb-test-site-notice .swal2-icon {
    margin: 0 auto 16px !important;
    border-color: #bfdbfe !important;
    color: #1d4ed8 !important;
}
.bnb-test-site-notice .swal2-icon.swal2-info {
    border-color: #bfdbfe !important;
    color: #1d4ed8 !important;
}
.bnb-test-site-notice .swal2-title {
    display: block !important;
    padding: 0 0 12px !important;
    margin: 0 !important;
    font-size: 20px !important;
    font-weight: 700 !important;
    color: #1e40af !important;
    line-height: 1.4 !important;
}
.bnb-test-site-notice .bnb-test-site-notice-html {
    margin: 0 !important;
    padding: 0 !important;
    text-align: right !important;
    color: #334155 !important;
    font-size: 14px !important;
    line-height: 1.9 !important;
}
.bnb-test-site-notice .bnb-test-site-notice-html p {
    margin: 0 0 12px;
    color: #334155;
}
.bnb-test-site-notice .bnb-test-site-notice-html ul {
    margin: 0;
    padding-right: 1.25rem;
    color: #475569;
}
.bnb-test-site-notice .bnb-test-site-notice-html li {
    margin-bottom: 6px;
}
.bnb-test-site-notice .bnb-test-site-notice-html .bnb-test-site-notice-note {
    margin: 14px 0 0;
    font-size: 13px;
    color: #64748b;
}
.bnb-test-site-notice .swal2-actions {
    margin: 22px 0 0 !important;
    width: 100% !important;
}
.bnb-test-site-notice .swal2-styled.bnb-test-site-notice-btn {
    margin: 0 !important;
    width: 100% !important;
    border-radius: 12px !important;
    font-family: inherit !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    padding: 12px 16px !important;
    background: #1e40af !important;
    border: 1px solid #1e40af !important;
    color: #fff !important;
    box-shadow: none !important;
}
.bnb-test-site-notice .swal2-styled.bnb-test-site-notice-btn:hover {
    background: #1e3a8a !important;
    border-color: #1e3a8a !important;
}
</style>
<script>
(function () {
    if (window.showBnbTestSiteNotice) return;

    window.showBnbTestSiteNotice = function () {
        if (window.showBnbTestSiteNotice._shown) return;
        if (!window.Swal) return;
        window.showBnbTestSiteNotice._shown = true;

        Swal.fire({
            title: 'محیط آزمایشی',
            icon: 'info',
            html: `
                <p>شما در حال استفاده از <strong>نسخه آزمایشی (تست)</strong> سامانه رزرو هستید.</p>
                <ul>
                    <li>داده‌ها و تغییرات این محیط کاملاً <strong>جدا از نسخه اصلی</strong> نگهداری می‌شوند.</li>
                    <li>هر تغییری که اینجا انجام دهید بر روی سایت اصلی، رزروهای واقعی یا اطلاعات کاربران تأثیری ندارد.</li>
                    <li>این نسخه صرفاً برای آزمایش قابلیت‌های جدید، رفع باگ و آموزش تیم طراحی شده است.</li>
                </ul>
                <p class="bnb-test-site-notice-note">لطفاً پیش از استفاده در محیط واقعی، همه‌چیز را در این محیط بررسی و تأیید کنید.</p>
            `,
            confirmButtonText: '<i class="bi bi-check-lg me-1"></i>متوجه شدم، ادامه می‌دهم',
            allowOutsideClick: false,
            allowEscapeKey: true,
            customClass: {
                popup: 'bnb-test-site-notice',
                htmlContainer: 'bnb-test-site-notice-html',
                confirmButton: 'bnb-test-site-notice-btn',
            },
            didOpen: function () {
                var container = document.querySelector('.swal2-container');
                if (container) container.style.zIndex = '20050';
            }
        });
    };

    window.addEventListener('message', function (e) {
        if (e.origin !== window.location.origin) return;
        var data = e.data;
        if (!data || typeof data !== 'object' || data.type !== 'bnb-show-test-site-notice') return;
        window.showBnbTestSiteNotice();
    });
})();
</script>
@endif

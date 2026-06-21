{{-- Shown once after staff login when TEST_SITE_MODE=true --}}
@if(config('test_site.enabled') && session('show_test_site_notice'))
<script>
(function () {
    function showTestSiteNotice() {
        if (!window.Swal) return;

        Swal.fire({
            title: 'محیط آزمایشی',
            icon: 'info',
            html: `
                <div style="text-align:right;line-height:1.9;font-size:14px;color:#334155;">
                    <p style="margin:0 0 12px;">
                        شما در حال استفاده از <strong>نسخه آزمایشی (تست)</strong> سامانه رزرو هستید.
                    </p>
                    <ul style="margin:0;padding-right:1.25rem;text-align:right;">
                        <li>داده‌ها و تغییرات این محیط کاملاً <strong>جدا از نسخه اصلی</strong> نگهداری می‌شوند.</li>
                        <li>هر تغییری که اینجا انجام دهید بر روی سایت اصلی، رزروهای واقعی یا اطلاعات کاربران تأثیری ندارد.</li>
                        <li>این نسخه صرفاً برای آزمایش قابلیت‌های جدید، رفع باگ و آموزش تیم طراحی شده است.</li>
                    </ul>
                    <p style="margin:14px 0 0;font-size:13px;color:#64748b;">
                        لطفاً پیش از استفاده در محیط واقعی، همه‌چیز را در این محیط بررسی و تأیید کنید.
                    </p>
                </div>
            `,
            confirmButtonText: '<i class="bi bi-check-lg me-1"></i>متوجه شدم، ادامه می‌دهم',
            confirmButtonColor: '#1e40af',
            allowOutsideClick: false,
            allowEscapeKey: true,
            customClass: { popup: 'bnb-swal-popup' },
            didOpen: function (popup) {
                popup.style.fontFamily = 'Vazirmatn, sans-serif';
                popup.style.direction = 'rtl';
                var container = document.querySelector('.swal2-container');
                if (container) container.style.zIndex = '10000';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showTestSiteNotice);
    } else {
        showTestSiteNotice();
    }
})();
</script>
@endif

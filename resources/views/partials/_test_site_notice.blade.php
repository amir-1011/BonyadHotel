{{-- Shown once after staff login when TEST_SITE_MODE=true --}}
@include('partials._test_site_notice_dialog')
@if(config('test_site.enabled') && session('show_test_site_notice'))
<script>
(function () {
    if (window.parent !== window) return;

    function run() {
        if (typeof window.showBnbTestSiteNotice === 'function') {
            window.showBnbTestSiteNotice();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
</script>
@endif

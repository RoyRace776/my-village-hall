window.MyvhPortalReports = (function() {
    function initPage() {
        if (window.MyVHReportBuilder && typeof window.MyVHReportBuilder.init === 'function') {
            window.MyVHReportBuilder.init();
        }

        if (window.MyVHReportRunner && typeof window.MyVHReportRunner.init === 'function') {
            window.MyVHReportRunner.init();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPage);
    } else {
        initPage();
    }

    return {
        initPage: initPage
    };
})();

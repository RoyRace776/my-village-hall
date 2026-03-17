document.addEventListener("DOMContentLoaded", () => {

    function loadPage(page) {

        fetch(myvhPortal.ajaxUrl + "?action=myvh_portal_page&page=" + page)
            .then(r => r.text())
            .then(html => {
                document.getElementById("portal-content").innerHTML = html;
            });
    }

    function router() {

        let page = location.hash.replace("#", "") || "dashboard";

        loadPage(page);
    }

    window.addEventListener("hashchange", router);

    router();
});

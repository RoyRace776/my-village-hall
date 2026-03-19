document.addEventListener("DOMContentLoaded", () => {

    function loadPage(page) {

        fetch(myvhPortal.ajax_url + "?action=myvh_portal_page&page=" + page)
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

    // --- Group toggle ---
    document.getElementById('portal-content').addEventListener('click', function (e) {
        const header = e.target.closest('.myvh-group-header');
        if (!header) return;

        const group   = header.dataset.group;
        const children = document.querySelector(`.myvh-group-children[data-group="${group}"]`);
        const toggle   = header.querySelector('.myvh-group-toggle');

        if (!children) return;

        const isOpen = children.classList.toggle('is-open');
        if (toggle) toggle.textContent = isOpen ? '▼' : '▶';
    });

    router();
});

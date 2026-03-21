document.addEventListener("DOMContentLoaded", () => {

    const routeAliases = {
        'my-bookings': 'bookings',
        'book-room': 'bookings',
        'home': 'dashboard'
    };

    function initPortalPage() {
        if (document.getElementById('myvh-calendar') && typeof MYVH_Calendar !== 'undefined') {
            MYVH_Calendar.init();
        }

        if (document.querySelector('.myvh-bookings-list') && typeof MYVH_Bookings !== 'undefined') {
            MYVH_Bookings.init();
        }
    }

    function postPortalForm(action, form) {
        const formData = new FormData(form);
        formData.append('action', action);
        formData.append('nonce', myvhPortal.nonce);

        return fetch(myvhPortal.ajax_url, {
            method: 'POST',
            body: formData,
        }).then(r => r.json());
    }

    function showMessage(target, text, isError) {
        if (!target) return;
        target.textContent = text || '';
        target.style.color = isError ? '#b32d2e' : '#2d5a27';
    }

    function loadPage(page) {

        fetch(
            myvhPortal.ajax_url
            + "?action=myvh_portal_page"
            + "&page=" + encodeURIComponent(page)
            + "&nonce=" + encodeURIComponent(myvhPortal.nonce)
        )
            .then(r => r.text())
            .then(html => {
                document.getElementById("portal-content").innerHTML = html;
                initPortalPage();
            });
    }

    function router() {

        let page = location.hash.replace("#", "") || "dashboard";
        page = routeAliases[page] || page;

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

    document.getElementById('portal-content').addEventListener('submit', function (e) {
        const form = e.target;

        if (form.id === 'myvh-account-details-form') {
            e.preventDefault();

            const message = document.getElementById('myvh-account-details-message');
            showMessage(message, 'Saving...', false);

            postPortalForm('myvh_portal_update_account', form)
                .then(res => {
                    if (!res.success) {
                        showMessage(message, res.data || 'Failed to update details', true);
                        return;
                    }
                    showMessage(message, res.data?.message || 'Account details updated', false);
                })
                .catch(() => {
                    showMessage(message, 'Unexpected error updating details', true);
                });
            return;
        }

        if (form.id === 'myvh-account-password-form') {
            e.preventDefault();

            const message = document.getElementById('myvh-account-password-message');
            showMessage(message, 'Updating password...', false);

            postPortalForm('myvh_portal_change_password', form)
                .then(res => {
                    if (!res.success) {
                        showMessage(message, res.data || 'Failed to update password', true);
                        return;
                    }

                    form.reset();
                    showMessage(message, res.data?.message || 'Password changed successfully', false);
                })
                .catch(() => {
                    showMessage(message, 'Unexpected error updating password', true);
                });
        }
    });

    router();
});

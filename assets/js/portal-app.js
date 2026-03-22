document.addEventListener("DOMContentLoaded", () => {

    const routeAliases = {
        'my-bookings': 'bookings',
        'book-room': 'bookings',
        'new-booking': 'bookings-new',
        'home': 'dashboard'
    };

    function initPortalPage() {
        if (document.getElementById('myvh-calendar') && typeof MYVH_Calendar !== 'undefined') {
            MYVH_Calendar.init();
        }

        if (document.querySelector('.myvh-bookings-list') && typeof MYVH_Bookings !== 'undefined') {
            MYVH_Bookings.init();
        }

        const bookingEditForm = document.getElementById('myvh-booking-edit-form');
        if (bookingEditForm) {
            const submitButton = bookingEditForm.querySelector('button[type="submit"]');
            if (submitButton) {
                const trackedFields = Array.from(
                    bookingEditForm.querySelectorAll('input:not([type="hidden"]), textarea, select')
                );

                const initialState = JSON.stringify(
                    trackedFields.map((field) => ({
                        name: field.name,
                        value: field.value,
                    }))
                );

                const syncSubmitState = () => {
                    const currentState = JSON.stringify(
                        trackedFields.map((field) => ({
                            name: field.name,
                            value: field.value,
                        }))
                    );

                    submitButton.disabled = currentState === initialState;
                };

                trackedFields.forEach((field) => {
                    field.addEventListener('input', syncSubmitState);
                    field.addEventListener('change', syncSubmitState);
                });

                syncSubmitState();
            }
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

    function loadPage(page, params = {}) {

        const query = new URLSearchParams({
            action: 'myvh_portal_page',
            page: page,
            nonce: myvhPortal.nonce,
        });

        Object.keys(params || {}).forEach((key) => {
            if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
                query.set(key, params[key]);
            }
        });

        fetch(
            myvhPortal.ajax_url
            + "?" + query.toString()
        )
            .then(r => r.text())
            .then(html => {
                document.getElementById("portal-content").innerHTML = html;
                initPortalPage();
            });
    }

    function router() {

        let hash = location.hash.replace("#", "") || "dashboard";
        let page = hash;
        let params = {};

        if (hash.includes('?')) {
            const parts = hash.split('?');
            page = parts[0] || 'dashboard';
            const searchParams = new URLSearchParams(parts[1] || '');
            searchParams.forEach((value, key) => {
                params[key] = value;
            });
        }

        page = routeAliases[page] || page;

        loadPage(page, params);
    }

    window.addEventListener("hashchange", router);

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

            return;
        }

        const portalAction = form.dataset.portalAction;
        if (portalAction) {
            e.preventDefault();

            const confirmMessage = form.dataset.confirm || '';
            if (confirmMessage && !window.confirm(confirmMessage)) {
                return;
            }

            const messageTargetId = form.dataset.messageTarget || '';
            const reloadPage = form.dataset.reloadPage || '';
            const message = messageTargetId ? document.getElementById(messageTargetId) : null;

            showMessage(message, 'Saving...', false);

            postPortalForm(portalAction, form)
                .then(res => {
                    if (!res.success) {
                        showMessage(message, res.data || 'Request failed', true);
                        return;
                    }

                    if (form.tagName === 'FORM') {
                        form.reset();
                    }

                    showMessage(message, res.data?.message || 'Saved', false);

                    if (reloadPage) {
                        setTimeout(() => loadPage(reloadPage), 200);
                    }
                })
                .catch(() => {
                    showMessage(message, 'Unexpected error while saving', true);
                });
        }
    });

    router();
});

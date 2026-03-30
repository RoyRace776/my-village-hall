document.addEventListener("DOMContentLoaded", () => {
    // Add a class to the body to indicate the portal is loaded
    document.body.classList.add('myvh-has-portal');

    // Aliases for hash routes to canonical page names
    const routeAliases = {
        'my-bookings': 'bookings',
        'book-room': 'bookings',
        'new-booking': 'bookings-new',
        'home': 'dashboard'
    };

    /**
     * Initialize all portal page widgets and logic.
     * Should be called after each page load or dynamic content update.
     */
    function initPortalPage() {
        // Initialize calendar if present
        if (document.getElementById('myvh-calendar') && typeof Calendar !== 'undefined') {
            Calendar.init();
        }

        // Initialize bookings list if present
        if (document.querySelector('.myvh-bookings-list')) {
            // Portal-specific client-side status filtering
            function filterPortalBookingsByStatus() {
                var checked = Array.from(document.querySelectorAll('.myvh-status-filter:checked')).map(function(cb) {
                    return cb.value;
                });
                var cards = document.querySelectorAll('.myvh-booking-card');
                cards.forEach(function(card) {
                    var status = card.getAttribute('data-status');
                    if (!status || checked.indexOf(status) !== -1) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }
            var boxes = document.querySelectorAll('.myvh-status-filter');
            boxes.forEach(function(cb) {
                cb.addEventListener('change', filterPortalBookingsByStatus);
            });
            // Initial filter
            filterPortalBookingsByStatus();
        }

        // Booking edit form: enable/disable submit button based on changes
        const bookingEditForm = document.getElementById('myvh-booking-edit-form');
        if (bookingEditForm) {
            const submitButton = bookingEditForm.querySelector('button[type="submit"]');
            if (submitButton) {
                // Track all non-hidden fields
                const trackedFields = Array.from(
                    bookingEditForm.querySelectorAll('input:not([type="hidden"]), textarea, select')
                );

                // Capture initial state for dirty check
                const initialState = JSON.stringify(
                    trackedFields.map((field) => ({
                        name: field.name,
                        value: field.value,
                    }))
                );

                // Disable submit if no changes
                const syncSubmitState = () => {
                    const currentState = JSON.stringify(
                        trackedFields.map((field) => ({
                            name: field.name,
                            value: field.value,
                        }))
                    );
                    submitButton.disabled = currentState === initialState;
                };

                // Listen for changes
                trackedFields.forEach((field) => {
                    field.addEventListener('input', syncSubmitState);
                    field.addEventListener('change', syncSubmitState);
                });

                syncSubmitState();
            }
        }

        // Initialize settings page tabs and dirty state if present
        const settingsPage = document.querySelector('.myvh-client-settings-page');
        if (settingsPage) {
            initSettingsTabs(settingsPage);
            initSettingsDirtyState(settingsPage);
        }

        // Organization billing toggles and invoices page logic
        initOrganisationBillingToggles();
        initInvoicesPage();
    }

    /**
     * Show/hide organisation billing fields based on toggle input.
     */
    function initOrganisationBillingToggles() {
        const toggleInputs = Array.from(document.querySelectorAll('.myvh-org-invoice-toggle'));
        if (!toggleInputs.length) {
            return;
        }

        toggleInputs.forEach((toggleInput) => {
            const form = toggleInput.closest('form');
            const fields = form ? form.querySelector('.myvh-org-billing-fields') : null;
            if (!fields) {
                return;
            }

            // Show/hide fields based on toggle
            const syncVisibility = () => {
                fields.hidden = !toggleInput.checked;
            };

            toggleInput.addEventListener('change', syncVisibility);
            syncVisibility();
        });
    }

    /**
     * Initialize the invoices page: tab switching, filtering, and drilldown for uninvoiced bookings.
     */
    function initInvoicesPage() {

        const invoicesPage = document.querySelector('.myvh-invoices-page');
        if (!invoicesPage) return;

        // Drilldown for uninvoiced bookings by customer/organisation
        invoicesPage.addEventListener('click', function (event) {
            const drilldownBtn = event.target.closest('.myvh-drilldown-btn');
            if (!drilldownBtn) return;

            let customerId = drilldownBtn.getAttribute('data-customer-id');
            let organisationId = drilldownBtn.getAttribute('data-organisation-id');
            const targetDiv = customerId ? document.getElementById('myvh-customer-drilldown') : document.getElementById('myvh-organisation-drilldown');
            if (!targetDiv) return;

            targetDiv.innerHTML = '<p>Loading bookings...</p>';

            // Build AJAX params
            const params = new URLSearchParams({
                action: 'myvh_portal_get_uninvoiced_bookings',
                nonce: myvhPortal.nonce
            });
            if (customerId) params.append('customer_id', customerId);
            if (organisationId) params.append('organisation_id', organisationId);

            // Fetch uninvoiced bookings and render table
            fetch(myvhPortal.ajax_url + '?' + params.toString())
                .then(r => r.json())
                .then(res => {
                    if (!res.success || !Array.isArray(res.data.bookings)) {
                        targetDiv.innerHTML = '<p>No bookings found or error.</p>';
                        return;
                    }
                    if (res.data.bookings.length === 0) {
                        targetDiv.innerHTML = '<p>No uninvoiced bookings found.</p>';
                        return;
                    }
                    let html = '<table class="myvh-invoices-table"><thead><tr>' +
                        '<th>Booking</th><th>Description</th><th>Date</th><th>Room</th></tr></thead><tbody>';
                    res.data.bookings.forEach(b => {
                        html += '<tr>' +
                            '<td>#' + (b.Id || '') + '</td>' +
                            '<td>' + (b.Description ? escapeHtml(b.Description) : '-') + '</td>' +
                            '<td>' + (b.StartDate ? escapeHtml(formatDate(b.StartDate)) : '-') + '</td>' +
                            '<td>' + (b.RoomName ? escapeHtml(b.RoomName) : '-') + '</td>' +
                            '</tr>';
                    });
                    html += '</tbody></table>';
                    targetDiv.innerHTML = html;
                })
                .catch(() => {
                    targetDiv.innerHTML = '<p>Error loading bookings.</p>';
                });
        });

        // Helper: escape HTML for safe rendering
        function escapeHtml(str) {
            return String(str).replace(/[&<>"']/g, function (s) {
                return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'})[s];
            });
        }
        // Helper: format date (YYYY-MM-DD or ISO)
        function formatDate(dateStr) {
            const d = new Date(dateStr);
            if (!isNaN(d)) {
                return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
            }
            return dateStr;
        }

        // Tab switching logic for invoices page
        const invoiceTabs = Array.from(invoicesPage.querySelectorAll('.myvh-invoices-tab'));
        const invoicePanels = Array.from(invoicesPage.querySelectorAll('[data-invoices-panel]'));

        if (invoiceTabs.length && invoicePanels.length) {
            const storageKey = 'myvhPortalInvoicesTab';

            const activateInvoicesTab = (tabKey) => {
                let hasMatch = false;

                invoiceTabs.forEach((tab) => {
                    const isActive = tab.dataset.invoicesTab === tabKey;
                    tab.classList.toggle('is-active', isActive);
                    tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    if (isActive) hasMatch = true;
                });

                invoicePanels.forEach((panel) => {
                    const isActive = panel.dataset.invoicesPanel === tabKey;
                    panel.classList.toggle('is-active', isActive);
                    panel.hidden = !isActive;
                });

                if (hasMatch) {
                    try {
                        window.localStorage.setItem(storageKey, tabKey);
                    } catch (e) {
                        // Ignore storage failures.
                    }
                }
            };

            // Use delegated click handling to keep tabs working across dynamic re-renders.
            invoicesPage.addEventListener('click', (event) => {
                const clickedTab = event.target.closest('[data-invoices-tab]');
                if (!clickedTab || !invoicesPage.contains(clickedTab)) {
                    return;
                }

                event.preventDefault();
                activateInvoicesTab(clickedTab.dataset.invoicesTab || 'list');
            });

            const currentHash = window.location.hash || '';
            const hasStatusFiltersInHash = currentHash.indexOf('#invoices?') === 0 && currentHash.indexOf('statuses=') > -1;

            let initialTab = hasStatusFiltersInHash ? 'list' : '';

            if (!initialTab) {
                try {
                    initialTab = window.localStorage.getItem(storageKey) || '';
                } catch (e) {
                    initialTab = '';
                }
            }

            if (!initialTab || !invoiceTabs.some((tab) => tab.dataset.invoicesTab === initialTab)) {
                initialTab = invoiceTabs[0]?.dataset.invoicesTab || 'list';
            }

            activateInvoicesTab(initialTab);
        }

        // Invoice status filter form: update hash for filtering
        const filterForm = invoicesPage.querySelector('#myvh-invoice-filter-form');
        if (filterForm) {
            filterForm.addEventListener('submit', function (event) {
                event.preventDefault();

                const selected = Array.from(filterForm.querySelectorAll('input[name="statuses[]"]:checked'))
                    .map((checkbox) => checkbox.value)
                    .filter(Boolean);

                const statusCsv = selected.join(',');
                location.hash = statusCsv ? '#invoices?statuses=' + encodeURIComponent(statusCsv) : '#invoices';
            });
        }

        // Select all/clear all checkboxes for uninvoiced bookings
        const checkboxes = Array.from(invoicesPage.querySelectorAll('.myvh-uninvoiced-checkbox'));
        const selectAllButton = invoicesPage.querySelector('#myvh-select-all-uninvoiced');
        const clearAllButton = invoicesPage.querySelector('#myvh-clear-all-uninvoiced');

        if (selectAllButton) {
            selectAllButton.addEventListener('click', function () {
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = true;
                });
            });
        }

        if (clearAllButton) {
            clearAllButton.addEventListener('click', function () {
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = false;
                });
            });
        }
    }

    /**
     * Initialize settings page tab switching logic.
     * Remembers last active tab in localStorage.
     */
    function initSettingsTabs(settingsPage) {
        const tabs = Array.from(settingsPage.querySelectorAll('.myvh-settings-tab'));
        const panels = Array.from(settingsPage.querySelectorAll('[data-settings-panel]'));
        if (!tabs.length || !panels.length) return;

        const storageKey = 'myvhPortalSettingsTab';

        function activateTab(tabKey) {
            let hasMatch = false;

            tabs.forEach((tab) => {
                const isActive = tab.dataset.settingsTab === tabKey;
                tab.classList.toggle('is-active', isActive);
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                if (isActive) hasMatch = true;
            });

            panels.forEach((panel) => {
                const isActive = panel.dataset.settingsPanel === tabKey;
                panel.classList.toggle('is-active', isActive);
                panel.hidden = !isActive;
            });

            if (hasMatch) {
                try {
                    window.localStorage.setItem(storageKey, tabKey);
                } catch (e) {
                    // Ignore storage failures in private browsing modes.
                }
            }
        }

        // Tab click event
        tabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                activateTab(tab.dataset.settingsTab || '');
            });
        });

        // Restore last active tab
        let storedTab = '';
        try {
            storedTab = window.localStorage.getItem(storageKey) || '';
        } catch (e) {
            storedTab = '';
        }

        if (storedTab && tabs.some((tab) => tab.dataset.settingsTab === storedTab)) {
            activateTab(storedTab);
        }
    }

    /**
     * Track dirty state for settings forms and enable/disable submit button accordingly.
     */
    function initSettingsDirtyState(settingsPage) {
        const forms = Array.from(settingsPage.querySelectorAll('.myvh-settings-form'));
        if (!forms.length) return;

        forms.forEach((form) => {
            const submitButton = form.querySelector('button[type="submit"]');
            if (!submitButton) return;

            // Only track enabled, non-hidden fields
            const trackedFields = Array.from(form.querySelectorAll('input, select, textarea'))
                .filter((field) => field.name && field.type !== 'hidden' && !field.disabled);

            if (!trackedFields.length) return;

            // Capture current state for dirty check
            const captureState = () => JSON.stringify(
                trackedFields.map((field) => {
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        return { name: field.name, checked: !!field.checked };
                    }
                    return { name: field.name, value: field.value };
                })
            );

            let baselineState = captureState();

            // Update button state based on changes
            const syncDirtyState = () => {
                const isDirty = captureState() !== baselineState;
                submitButton.classList.toggle('myvh-settings-save-dirty', isDirty);
                submitButton.classList.toggle('myvh-settings-save-clean', !isDirty);
                submitButton.disabled = !isDirty;
            };

            // Listen for changes
            trackedFields.forEach((field) => {
                field.addEventListener('input', syncDirtyState);
                field.addEventListener('change', syncDirtyState);
            });

            // Reset baseline on form reset
            form.addEventListener('reset', () => {
                setTimeout(() => {
                    baselineState = captureState();
                    syncDirtyState();
                }, 0);
            });

            // Disable button on submit
            form.addEventListener('submit', () => {
                submitButton.disabled = true;
            });

            syncDirtyState();
        });
    }

    /**
     * Submit a portal form via AJAX POST.
     * @param {string} action - The WP AJAX action name
     * @param {HTMLFormElement} form - The form to submit
     * @returns {Promise<object>} - The JSON response
     */
    function postPortalForm(action, form) {
        const formData = new FormData(form);
        formData.append('action', action);
        formData.append('nonce', myvhPortal.nonce);

        return fetch(myvhPortal.ajax_url, {
            method: 'POST',
            body: formData,
        }).then(r => r.json());
    }

    /**
     * Show a message in a target element, with color for error/success.
     */
    function showMessage(target, text, isError) {
        if (!target) return;
        target.textContent = text || '';
        target.style.color = isError ? '#b32d2e' : '#2d5a27';
    }

    /**
     * Load a portal page via AJAX and re-initialize widgets.
     * @param {string} page - The page slug
     * @param {object} params - Extra query params
     */
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

    /**
     * Simple hash-based router for portal pages.
     * Parses hash, resolves aliases, and loads the correct page.
     */
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

        // Use route alias if present
        page = routeAliases[page] || page;

        loadPage(page, params);
    }

    // Listen for hash changes to trigger router
    window.addEventListener("hashchange", router);

    // Delegate form submission handling for all portal forms
    document.getElementById('portal-content').addEventListener('submit', function (e) {
        const form = e.target;

        // Account details form
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

        // Password change form
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

        // Generic portal action forms (with data-portal-action)
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

    // Initial page load
    router();
});

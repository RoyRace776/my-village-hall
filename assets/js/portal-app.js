document.addEventListener("DOMContentLoaded", () => {
    // Add a class to the body to indicate the portal is loaded
    document.body.classList.add('myvh-has-portal');

    // Aliases for hash routes to canonical page names
    const routeAliases = {
        'my-bookings': 'bookings',
        'book-room': 'bookings',
        'home': 'dashboard'
    };

    const legacyBookingRoutes = new Set(['new-booking', 'bookings-new', 'booking-view', 'booking-edit', 'booking-delete']);

    function getHashRoute(targetHash) {
        const normalized = String(targetHash || '').replace(/^#/, '');
        const [page, queryString] = normalized.split('?');
        const params = {};

        new URLSearchParams(queryString || '').forEach((value, key) => {
            params[key] = value;
        });

        return {
            page: page || 'dashboard',
            params: params
        };
    }

    function getLegacyBookingAction(page, params) {
        if (!legacyBookingRoutes.has(page)) {
            return null;
        }

        const bookingId = parseInt(params.booking_id || '0', 10) || 0;

        if (page === 'new-booking' || page === 'bookings-new') {
            return {
                page: 'bookings',
                run: function() {
                    window.MyvhPortalCalendarFlow?.openCreate({});
                }
            };
        }

        if (!bookingId) {
            return {
                page: 'bookings',
                run: function() {}
            };
        }

        if (page === 'booking-edit') {
            return {
                page: 'bookings',
                run: function() {
                    window.MyvhPortalCalendarFlow?.openEdit(bookingId);
                }
            };
        }

        if (page === 'booking-delete') {
            return {
                page: 'bookings',
                run: function() {
                    window.MyvhPortalCalendarFlow?.deleteBooking(bookingId);
                }
            };
        }

        return {
            page: 'bookings',
            run: function() {
                window.MyvhPortalCalendarFlow?.openView(bookingId);
            }
        };
    }

    function runWhenPortalBookingFlowReady(action) {
        if (typeof action !== 'function') {
            return;
        }

        if (window.MyvhPortalCalendarFlow) {
            action();
            return;
        }

        const handleReady = function() {
            document.removeEventListener('myvh:portal-booking-flow-ready', handleReady);
            action();
        };

        document.addEventListener('myvh:portal-booking-flow-ready', handleReady, { once: true });
    }

    function portalAlert(message) {
        if (window.MyvhPortalDialog && typeof window.MyvhPortalDialog.alert === 'function') {
            return window.MyvhPortalDialog.alert(message);
        }

        return Promise.resolve(true);
    }

    function portalConfirm(message) {
        if (window.MyvhPortalDialog && typeof window.MyvhPortalDialog.confirm === 'function') {
            return window.MyvhPortalDialog.confirm(message);
        }

        return Promise.resolve(false);
    }

    function initPortalNavigation() {
        const portalNav = document.querySelector('[data-portal-nav]');
        if (!portalNav || portalNav.dataset.bound === '1') {
            return;
        }

        portalNav.dataset.bound = '1';

        const navGroups = Array.from(portalNav.querySelectorAll('[data-portal-nav-group]'));

        const closeNavGroup = (group) => {
            if (!group) {
                return;
            }

            const toggle = group.querySelector('.myvh-portal-nav-toggle');
            group.classList.remove('is-open');

            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        };

        const closeAllNavGroups = (exceptGroup) => {
            navGroups.forEach((group) => {
                if (group !== exceptGroup) {
                    closeNavGroup(group);
                }
            });
        };

        navGroups.forEach((group) => {
            const toggle = group.querySelector('.myvh-portal-nav-toggle');
            if (!toggle) {
                return;
            }

            toggle.addEventListener('click', () => {
                const willOpen = !group.classList.contains('is-open');
                closeAllNavGroups(group);
                group.classList.toggle('is-open', willOpen);
                toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });
        });

        document.addEventListener('click', (event) => {
            navGroups.forEach((group) => {
                if (!group.contains(event.target)) {
                    closeNavGroup(group);
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            closeAllNavGroups();
        });

        portalNav.addEventListener('click', (event) => {
            const link = event.target.closest('a[href^="#"]');
            if (!link || !portalNav.contains(link)) {
                return;
            }

            const href = link.getAttribute('href') || '';
            if (!href || href === '#') {
                return;
            }

            event.preventDefault();
            closeAllNavGroups();
            navigateToHash(href);
        });
    }

    function syncPortalAccountLabel(nextLabel) {
        const normalizedLabel = String(nextLabel || '').trim();
        if (!normalizedLabel) {
            return;
        }

        const accountToggleLabel = document.querySelector('.myvh-portal-nav-group--account .myvh-portal-account-label');
        if (accountToggleLabel) {
            accountToggleLabel.textContent = normalizedLabel;
        }
    }

    function initTableSort(table) {
        if (!table || table.dataset.sortBound === '1') {
            return;
        }

        const headerCells = Array.from(table.querySelectorAll('thead th'));
        const tbody = table.querySelector('tbody');
        if (!headerCells.length || !tbody) {
            return;
        }

        const nonSortableLabels = new Set(['actions', 'details']);

        const getCellText = (row, columnIndex) => {
            const cell = row.cells[columnIndex];
            if (!cell) {
                return '';
            }

            return cell.textContent.replace(/\s+/g, ' ').trim();
        };

        const toNumericValue = (value) => {
            const cleaned = value.replace(/[£$,]/g, '').trim();
            if (cleaned === '') {
                return null;
            }

            const parsed = Number.parseFloat(cleaned);
            return Number.isNaN(parsed) ? null : parsed;
        };

        headerCells.forEach((headerCell, columnIndex) => {
            const label = headerCell.textContent.replace(/\s+/g, ' ').trim().toLowerCase();
            if (!label || nonSortableLabels.has(label)) {
                return;
            }

            headerCell.classList.add('myvh-th-sortable');
            headerCell.setAttribute('role', 'button');
            headerCell.setAttribute('tabindex', '0');

            const sortByColumn = () => {
                const currentDirection = headerCell.getAttribute('data-sort-dir');
                const nextDirection = currentDirection === 'asc' ? 'desc' : 'asc';
                const directionMultiplier = nextDirection === 'asc' ? 1 : -1;

                headerCells.forEach((cell) => {
                    cell.removeAttribute('data-sort-dir');
                });
                headerCell.setAttribute('data-sort-dir', nextDirection);

                const rows = Array.from(tbody.querySelectorAll('tr'));

                const numericSort = rows.every((row) => {
                    const value = getCellText(row, columnIndex);
                    return value === '' || toNumericValue(value) !== null;
                });

                rows.sort((firstRow, secondRow) => {
                    const firstText = getCellText(firstRow, columnIndex);
                    const secondText = getCellText(secondRow, columnIndex);

                    if (numericSort) {
                        const firstNumber = toNumericValue(firstText);
                        const secondNumber = toNumericValue(secondText);

                        if (firstNumber === null && secondNumber === null) {
                            return 0;
                        }

                        if (firstNumber === null) {
                            return 1;
                        }

                        if (secondNumber === null) {
                            return -1;
                        }

                        return (firstNumber - secondNumber) * directionMultiplier;
                    }

                    return firstText.localeCompare(secondText, undefined, { sensitivity: 'base', numeric: true }) * directionMultiplier;
                });

                const fragment = document.createDocumentFragment();
                rows.forEach((row) => {
                    fragment.appendChild(row);
                });
                tbody.appendChild(fragment);
            };

            headerCell.addEventListener('click', sortByColumn);
            headerCell.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }

                event.preventDefault();
                sortByColumn();
            });
        });

        table.dataset.sortBound = '1';
    }

    /**
     * Initialize all portal page widgets and logic.
     * Should be called after each page load or dynamic content update.
     */
    function initPortalPage() {
        document.querySelectorAll('.myvh-customer-list-table').forEach(initTableSort);

        window.MyvhFlatpickr?.initWithin(document);

        (function initQuarterHourInputs() {
            const fields = document.querySelectorAll(
                '.myvh-room-rate-add-page input[name="start_time"], ' +
                '.myvh-room-rate-add-page input[name="end_time"], ' +
                '.myvh-room-rate-edit-page input[name="start_time"], ' +
                '.myvh-room-rate-edit-page input[name="end_time"], ' +
                '.myvh-room-rate-tester-page input[name="start_time"], ' +
                '.myvh-room-rate-tester-page input[name="end_time"]'
            );

            const isQuarterHour = function(value) {
                const match = String(value || '').match(/(?:T|^)(\d{2}):(\d{2})(?::(\d{2}))?$/);
                if (!match) {
                    return false;
                }

                const minutes = parseInt(match[2], 10);
                const seconds = parseInt(match[3] || '0', 10);
                return minutes % 15 === 0 && seconds === 0;
            };

            fields.forEach(function(field) {
                if (!field) {
                    return;
                }

                field.setAttribute('step', '900');

                const validate = function() {
                    const value = String(field.value || '').trim();
                    if (value === '') {
                        field.setCustomValidity('');
                        return;
                    }

                    if (!isQuarterHour(value)) {
                        field.setCustomValidity('Please use 15 minute intervals (00, 15, 30, 45).');
                        return;
                    }

                    field.setCustomValidity('');
                };

                field.addEventListener('input', validate);
                field.addEventListener('change', validate);
                validate();
            });
        })();

        // Single booking invoice rules table (portal admin page)
        (function initSingleBookingInvoiceRulesPage() {
            const table = document.getElementById('myvh-single-booking-rules-table');
            const addButton = document.getElementById('myvh-add-single-booking-rule');

            if (!table || !addButton) {
                return;
            }

            if (table.dataset.rulesInit === '1') {
                return;
            }

            table.dataset.rulesInit = '1';

            const body = table.querySelector('tbody');
            if (!body) {
                return;
            }

            const nextIndex = function () {
                return body.querySelectorAll('tr.myvh-rule-row').length;
            };

            const buildRow = function (index) {
                const row = document.createElement('tr');
                row.className = 'myvh-rule-row';
                row.innerHTML = '' +
                    '<td><input type="hidden" name="rules[' + index + '][id]" value="0"><input type="text" name="rules[' + index + '][name]" required></td>' +
                    '<td><select name="rules[' + index + '][trigger_timing]">' +
                        '<option value="confirmation">On booking confirmation</option>' +
                        '<option value="booking_date">On booking date</option>' +
                        '<option value="days_before_booking_date">N days before booking date</option>' +
                        '<option value="days_after_booking_date">N days after booking date</option>' +
                        '<option value="manual_invoicing">Manual invoicing</option>' +
                    '</select></td>' +
                    '<td><input type="number" name="rules[' + index + '][trigger_offset_days]" min="0" value="0"></td>' +
                    '<td><select name="rules[' + index + '][group_by]">' +
                        '<option value="per_booking">Per booking</option>' +
                        '<option value="by_customer">By customer</option>' +
                        '<option value="by_organisation">By organisation</option>' +
                    '</select></td>' +
                    '<td><input type="number" name="rules[' + index + '][due_date_offset_days]" min="0" value="30"></td>' +
                    '<td><input type="checkbox" name="rules[' + index + '][is_active]" value="1" checked></td>' +
                    '<td><button type="button" class="button myvh-remove-rule-row">Remove</button></td>';

                return row;
            };

            addButton.addEventListener('click', function () {
                body.appendChild(buildRow(nextIndex()));
            });

            body.addEventListener('click', function (event) {
                const target = event.target;

                if (!target || !target.classList.contains('myvh-remove-rule-row')) {
                    return;
                }

                const row = target.closest('tr.myvh-rule-row');
                if (row) {
                    row.remove();
                }
            });
        })();

        // Recurring booking invoice rules table (portal admin page)
        (function initRecurringBookingInvoiceRulesPage() {
            const table = document.getElementById('myvh-recurring-booking-rules-table');
            const addButton = document.getElementById('myvh-add-recurring-booking-rule');

            if (!table || !addButton) {
                return;
            }

            if (table.dataset.rulesInit === '1') {
                return;
            }

            table.dataset.rulesInit = '1';

            const body = table.querySelector('tbody');
            if (!body) {
                return;
            }

            const nextIndex = function () {
                return body.querySelectorAll('tr.myvh-rule-row').length;
            };

            const buildRow = function (index) {
                const row = document.createElement('tr');
                row.className = 'myvh-rule-row';
                row.innerHTML = '' +
                    '<td><input type="hidden" name="rules[' + index + '][id]" value="0"><input type="text" name="rules[' + index + '][name]" required></td>' +
                    '<td><select name="rules[' + index + '][trigger_timing]" style="min-width: 190px;">' +
                        '<option value="start_of_month">Start of month</option>' +
                        '<option value="start_of_quarter">Start of quarter</option>' +
                        '<option value="start_of_week">Start of week</option>' +
                        '<option value="manual_invoicing">Manual invoicing</option>' +
                        '<option value="treat_as_single_bookings">Treat as single bookings</option>' +
                    '</select></td>' +
                    '<td><select name="rules[' + index + '][trigger_direction]" style="min-width: 150px;">' +
                        '<option value="in_advance">In advance</option>' +
                        '<option value="in_arrears">In arrears</option>' +
                    '</select></td>' +
                    '<td><input type="number" name="rules[' + index + '][trigger_period_count]" min="0" max="99" value="0" style="width: 46px;"></td>' +
                    '<td><input type="number" name="rules[' + index + '][due_date_offset_days]" min="0" value="30"></td>' +
                    '<td><input type="checkbox" name="rules[' + index + '][is_active]" value="1" checked></td>' +
                    '<td><button type="button" class="button myvh-remove-rule-row">Remove</button></td>';

                return row;
            };

            addButton.addEventListener('click', function () {
                body.appendChild(buildRow(nextIndex()));
            });

            body.addEventListener('click', function (event) {
                const target = event.target;

                if (!target || !target.classList.contains('myvh-remove-rule-row')) {
                    return;
                }

                const row = target.closest('tr.myvh-rule-row');
                if (row) {
                    row.remove();
                }
            });
        })();

        // Collapsible cards (e.g. Hall Notices)
        document.querySelectorAll('[data-myvh-collapsible] .myvh-collapse-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const card = btn.closest('[data-myvh-collapsible]');
                const collapsed = card.classList.toggle('is-collapsed');
                btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            });
        });

        // Notices repeater (portal settings page)
        document.querySelectorAll('[data-notices-repeater]').forEach(function (wrapper) {
            if (wrapper._myvhNoticesInit) return;
            wrapper._myvhNoticesInit = true;

            let tbody    = wrapper.querySelector('.myvh-notices-body');
            let addBtn   = wrapper.querySelector('.myvh-notice-add-row');
            let fieldName = wrapper.getAttribute('data-field') || 'notices';
            let phFrom   = wrapper.getAttribute('data-placeholder-from') || '';
            let phTo     = wrapper.getAttribute('data-placeholder-to') || '';

            if (!tbody || !addBtn) return;

            function rowCount() {
                return tbody.querySelectorAll('.myvh-notice-row').length;
            }

            function buildRow(idx) {
                let tr = document.createElement('tr');
                tr.className = 'myvh-notice-row';
                tr.innerHTML =
                    '<td style="padding:4px 8px;"><textarea name="' + fieldName + '[' + idx + '][message]" rows="2" style="width:100%;" required></textarea></td>' +
                    '<td style="padding:4px 8px;"><input type="text" name="' + fieldName + '[' + idx + '][start_date]" placeholder="' + phFrom + '" data-myvh-picker="date" autocomplete="off" style="width:100%;"></td>' +
                    '<td style="padding:4px 8px;"><input type="text" name="' + fieldName + '[' + idx + '][end_date]"   placeholder="' + phTo   + '" data-myvh-picker="date" autocomplete="off" style="width:100%;"></td>' +
                    '<td style="padding:4px 8px;"><button type="button" class="myvh-notice-remove" style="cursor:pointer;">&#x2715; Remove</button></td>';
                return tr;
            }

            function reindex() {
                tbody.querySelectorAll('.myvh-notice-row').forEach(function (row, i) {
                    row.querySelectorAll('[name]').forEach(function (el) {
                        el.name = el.name.replace(/\[\d+\]/, '[' + i + ']');
                    });
                });
            }

            addBtn.addEventListener('click', function () {
                let row = buildRow(rowCount());
                tbody.appendChild(row);
                window.MyvhFlatpickr?.initWithin(row);
            });

            tbody.addEventListener('click', function (e) {
                if (e.target.classList.contains('myvh-notice-remove')) {
                    e.target.closest('tr').remove();
                    reindex();
                }
            });
        });

        if (typeof Bookings !== 'undefined' && typeof Bookings.init === 'function') {
            Bookings.init();
        }

        if (typeof Calendar !== 'undefined' && typeof Calendar.ensureBookingFlow === 'function') {
            Calendar.ensureBookingFlow();
        }

        // Initialize calendar if present
        if (document.getElementById('myvh-calendar') && typeof Calendar !== 'undefined') {
            Calendar.init();
        }

        // Booking edit form: enable/disable submit button based on changes
        const bookingEditForm = document.getElementById('myvh-booking-edit-form');
        if (bookingEditForm) {
            const submitButton = bookingEditForm.querySelector('button[type="submit"]');
            const scopeRadios = Array.from(bookingEditForm.querySelectorAll('input[name="edit_scope"]'));
            const scopeHint = bookingEditForm.querySelector('[data-recurring-scope-hint]');
            const scheduleFields = Array.from(bookingEditForm.querySelectorAll('input[name="start_date"], input[name="start_time"], input[name="end_time"]'));

            const syncRecurringScopeState = () => {
                if (!scopeRadios.length || !scheduleFields.length) {
                    return;
                }

                const scheduleChanged = scheduleFields.some((field) => field.defaultValue !== field.value);

                scopeRadios.forEach((radio) => {
                    const isSingle = radio.value === 'this_only';
                    radio.disabled = scheduleChanged && !isSingle;
                    if (radio.disabled && radio.checked) {
                        const fallback = scopeRadios.find(option => option.value === 'this_only');
                        if (fallback) {
                            fallback.checked = true;
                        }
                    }
                });

                if (scopeHint) {
                    scopeHint.textContent = scheduleChanged
                        ? 'Date and time changes only apply to this booking in the portal edit form.'
                        : 'Series-wide updates apply description only in this portal form. If you change the date or time, broader options will be disabled.';
                }
            };

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

                scheduleFields.forEach((field) => {
                    field.addEventListener('input', syncRecurringScopeState);
                    field.addEventListener('change', syncRecurringScopeState);
                });

                syncRecurringScopeState();
                syncSubmitState();
            }
        }

        // Initialize settings page tabs and dirty state if present
        const settingsPage = document.querySelector('.myvh-client-settings-page');
        if (settingsPage) {
            initSettingsTabs(settingsPage);
            initSettingsMediaFields(settingsPage);
            initSettingsDirtyState(settingsPage);
        }

        // Organization billing toggles and invoices page logic
        initRoomColourPreviews();
        initOrganisationBillingToggles();
        initRoomRatesPage();
        initInvoicesPage();
        initPaymentsPage();

        if (window.MyvhPortalEmail) {
            window.MyvhPortalEmail.initEmailTemplatesPage();
            window.MyvhPortalEmail.initEmailTemplateEditPage();
        }
    }

    /**
     * Initialize room rates page filters.
     */
    function initRoomRatesPage() {
        const ratesPage = document.querySelector('.myvh-room-rates-page');
        if (!ratesPage || ratesPage.dataset.filtersInit === '1') {
            return;
        }

        const roomFilter = ratesPage.querySelector('#myvh-room-rates-filter-room');
        const orgTypeFilter = ratesPage.querySelector('#myvh-room-rates-filter-org-type');
        const clearButton = ratesPage.querySelector('[data-room-rates-clear-filters]');
        const summary = ratesPage.querySelector('#myvh-room-rates-filter-summary');
        const noResults = ratesPage.querySelector('#myvh-room-rates-no-results');
        const rows = Array.from(ratesPage.querySelectorAll('#myvh-room-rates-table tbody .myvh-room-rate-row'));

        if (!roomFilter || !orgTypeFilter || !rows.length) {
            ratesPage.dataset.filtersInit = '1';
            return;
        }

        const hasOptionValue = function(selectElement, targetValue) {
            return Array.from(selectElement.options).some(function(option) {
                return String(option.value) === String(targetValue);
            });
        };

        const currentRoute = getHashRoute(window.location.hash || '#room-rates');
        const currentRoutePage = routeAliases[currentRoute.page] || currentRoute.page;
        if (currentRoutePage === 'room-rates') {
            const hashRoomId = String(currentRoute.params.room_id || '');
            const hashOrgTypeId = String(currentRoute.params.org_type_id || '');

            if (hashRoomId !== '' && hasOptionValue(roomFilter, hashRoomId)) {
                roomFilter.value = hashRoomId;
            }

            if (hashOrgTypeId !== '' && hasOptionValue(orgTypeFilter, hashOrgTypeId)) {
                orgTypeFilter.value = hashOrgTypeId;
            }
        }

        const syncFiltersToHash = function() {
            const query = new URLSearchParams();
            const selectedRoom = String(roomFilter.value || '');
            const selectedOrgType = String(orgTypeFilter.value || '');

            if (selectedRoom !== '') {
                query.set('room_id', selectedRoom);
            }

            if (selectedOrgType !== '') {
                query.set('org_type_id', selectedOrgType);
            }

            const nextHash = query.toString() ? ('#room-rates?' + query.toString()) : '#room-rates';
            if (window.location.hash === nextHash) {
                return;
            }

            if (window.history && typeof window.history.replaceState === 'function') {
                window.history.replaceState(null, '', nextHash);
                return;
            }

            window.location.hash = nextHash;
        };

        const applyFilters = function() {
            const selectedRoom = String(roomFilter.value || '');
            const selectedOrgType = String(orgTypeFilter.value || '');
            const totalCount = rows.length;
            let visibleCount = 0;

            rows.forEach(function(row) {
                const rowRoomId = String(row.getAttribute('data-room-id') || '');
                const rowOrgTypeId = String(row.getAttribute('data-org-type-id') || '0');

                const roomMatch = selectedRoom === '' || rowRoomId === selectedRoom;
                const orgTypeMatch = selectedOrgType === ''
                    || (selectedOrgType === '0' && rowOrgTypeId === '0')
                    || (selectedOrgType !== '0' && rowOrgTypeId === selectedOrgType);

                const showRow = roomMatch && orgTypeMatch;
                row.style.display = showRow ? '' : 'none';

                if (showRow) {
                    visibleCount += 1;
                }
            });

            if (summary) {
                summary.textContent = visibleCount + ' of ' + totalCount + ' rates shown';
            }

            if (noResults) {
                noResults.style.display = visibleCount === 0 ? '' : 'none';
            }
        };

        roomFilter.addEventListener('change', function() {
            applyFilters();
            syncFiltersToHash();
        });

        orgTypeFilter.addEventListener('change', function() {
            applyFilters();
            syncFiltersToHash();
        });

        if (clearButton) {
            clearButton.addEventListener('click', function() {
                roomFilter.value = '';
                orgTypeFilter.value = '';
                applyFilters();
                syncFiltersToHash();
            });
        }

        applyFilters();
        ratesPage.dataset.filtersInit = '1';
    }

    /**
     * Initialize payments page filters and amount label behavior.
     */
    function initPaymentsPage() {
        const paymentsPage = document.querySelector('.myvh-payments-page');
        if (!paymentsPage) {
            return;
        }

        const filterForm = paymentsPage.querySelector('#myvh-payment-filter-form');
        if (filterForm && filterForm.dataset.bound !== '1') {
            const startDateInput = filterForm.querySelector('input[name="start_date"]');
            const endDateInput = filterForm.querySelector('input[name="end_date"]');
            const invoiceIdInput = filterForm.querySelector('input[name="invoice_id"]');
            const resetButton = filterForm.querySelector('[data-payment-filter-reset]');
            const lastMonthButton = filterForm.querySelector('[data-payment-date-range="last_month"]');

            const getDefaultEndDate = () => {
                const today = new Date();
                return today.toISOString().slice(0, 10);
            };

            const getDefaultStartDate = () => {
                const date = new Date();
                date.setMonth(date.getMonth() - 1);
                return date.toISOString().slice(0, 10);
            };

            const getLastMonthStartDate = () => {
                if (lastMonthButton) {
                    return lastMonthButton.getAttribute('data-start-date') || getDefaultStartDate();
                }
                return getDefaultStartDate();
            };

            const getLastMonthEndDate = () => {
                if (lastMonthButton) {
                    return lastMonthButton.getAttribute('data-end-date') || getDefaultEndDate();
                }
                return getDefaultEndDate();
            };

            const setDateRange = (startDate, endDate) => {
                if (startDateInput && startDate) {
                    startDateInput.value = startDate;
                }

                if (endDateInput && endDate) {
                    endDateInput.value = endDate;
                }
            };

            const applyPaymentFilterHash = () => {
                const query = new URLSearchParams();

                if (invoiceIdInput && invoiceIdInput.value) {
                    query.set('invoice_id', invoiceIdInput.value);
                }

                if (startDateInput && startDateInput.value) {
                    query.set('start_date', startDateInput.value);
                }

                if (endDateInput && endDateInput.value) {
                    query.set('end_date', endDateInput.value);
                }

                const queryString = query.toString();
                location.hash = queryString ? '#payments?' + queryString : '#payments';
            };

            filterForm.addEventListener('submit', (event) => {
                event.preventDefault();
                applyPaymentFilterHash();
            });

            paymentsPage.addEventListener('click', (event) => {
                const quickRangeButton = event.target.closest('[data-payment-date-range]');
                if (!quickRangeButton || !filterForm.contains(quickRangeButton)) {
                    return;
                }

                event.preventDefault();
                setDateRange(
                    quickRangeButton.getAttribute('data-start-date') || '',
                    quickRangeButton.getAttribute('data-end-date') || ''
                );
                applyPaymentFilterHash();
            });

            if (resetButton) {
                resetButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    setDateRange(getLastMonthStartDate(), getLastMonthEndDate());
                    applyPaymentFilterHash();
                });
            }

            filterForm.dataset.bound = '1';
        }

        const invoiceSelect = paymentsPage.querySelector('#myvh-portal-payment-invoice');
        const amountLabel = paymentsPage.querySelector('#myvh-portal-payment-amount-label');

        if (!invoiceSelect || !amountLabel || invoiceSelect.dataset.amountLabelBound === '1') {
            return;
        }

        const baseLabel = amountLabel.getAttribute('data-base-label') || 'Amount';
        const currencyFormatter = new Intl.NumberFormat('en-GB', {
            style: 'currency',
            currency: 'GBP',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });

        const updateAmountLabel = () => {
            const selectedOption = invoiceSelect.options[invoiceSelect.selectedIndex];

            if (!selectedOption || !selectedOption.value) {
                amountLabel.textContent = baseLabel;
                return;
            }

            const rawAmountDue = selectedOption.getAttribute('data-amount-due') || '';
            const amountDue = Number.parseFloat(rawAmountDue);

            if (!Number.isFinite(amountDue)) {
                amountLabel.textContent = baseLabel;
                return;
            }

            amountLabel.textContent = baseLabel + ' (' + currencyFormatter.format(amountDue) + ' outstanding)';
        };

        invoiceSelect.addEventListener('change', updateAmountLabel);
        invoiceSelect.dataset.amountLabelBound = '1';
        updateAmountLabel();
    }

    /**
     * Enhance room colour picker UX with live preview and swatch quick-select.
     */
    function initRoomColourPreviews() {
        const colourInputs = Array.from(document.querySelectorAll('input[data-room-colour-input]'));
        if (!colourInputs.length) {
            return;
        }

        const updatePreview = (input) => {
            const wrapper = input.closest('.myvh-account-field');
            if (!wrapper) {
                return;
            }

            const value = String(input.value || '').toLowerCase();
            const preview = wrapper.querySelector('[data-room-colour-preview]');
            const label = wrapper.querySelector('[data-room-colour-code]');

            if (preview) {
                preview.style.backgroundColor = value;
            }

            if (label) {
                label.textContent = value;
            }
        };

        colourInputs.forEach((input) => {
            updatePreview(input);
            input.addEventListener('input', () => updatePreview(input));
            input.addEventListener('change', () => updatePreview(input));

            const wrapper = input.closest('.myvh-account-field');
            if (!wrapper) {
                return;
            }

            const swatches = Array.from(wrapper.querySelectorAll('.myvh-room-colour-swatch[data-room-colour-choice]'));
            swatches.forEach((swatch) => {
                swatch.addEventListener('click', () => {
                    const swatchColour = String(swatch.getAttribute('data-room-colour-choice') || '').trim().toLowerCase();
                    if (!swatchColour) {
                        return;
                    }

                    input.value = swatchColour;
                    updatePreview(input);
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.focus();
                });
            });
        });
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

            targetDiv.innerHTML = renderDrilldownState('Loading bookings...', '');

            // Fetch uninvoiced bookings and render table
            window.MyvhPortalAjax.get({
                action: 'myvh_portal_get_uninvoiced_bookings',
                customer_id: customerId || '',
                organisation_id: organisationId || ''
            }, { scope: 'portal' })
                .then(res => {
                    if (!res.success || !Array.isArray(res.data.bookings)) {
                        targetDiv.innerHTML = renderDrilldownState('No bookings found or error.', 'Try again in a moment.');
                        return;
                    }
                    if (res.data.bookings.length === 0) {
                        targetDiv.innerHTML = renderDrilldownState('No uninvoiced bookings found.', 'This customer or organisation has nothing ready to invoice right now.');
                        return;
                    }
                    let html = '<div class="myvh-generate-drilldown-card">' +
                        '<div class="myvh-account-card-head"><div><h3>Uninvoiced Bookings</h3>' +
                        '<span>' + res.data.bookings.length + ' booking' + (res.data.bookings.length === 1 ? '' : 's') + '</span></div></div>' +
                        '<div class="myvh-invoices-table-wrap myvh-generate-table-wrap">' +
                        '<table class="myvh-customer-list-table myvh-invoices-table myvh-generate-drilldown-table"><thead><tr>' +
                        '<th>Booking</th><th>Description</th><th>Date</th><th>Room</th></tr></thead><tbody>';
                    res.data.bookings.forEach(b => {
                        html += '<tr>' +
                            '<td>#' + (b.Id || '') + '</td>' +
                            '<td>' + (b.Description ? escapeHtml(b.Description) : '-') + '</td>' +
                            '<td>' + (b.StartDate ? escapeHtml(formatDate(b.StartDate)) : '-') + '</td>' +
                            '<td>' + (b.RoomName ? escapeHtml(b.RoomName) : '-') + '</td>' +
                            '</tr>';
                    });
                    html += '</tbody></table></div></div>';
                    targetDiv.innerHTML = html;
                })
                .catch(() => {
                    targetDiv.innerHTML = renderDrilldownState('Error loading bookings.', 'Check the connection and try again.');
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

        function renderDrilldownState(title, detail) {
            const detailHtml = detail ? '<p>' + escapeHtml(detail) + '</p>' : '';

            return '<div class="myvh-empty-state myvh-invoices-empty-state myvh-generate-drilldown-empty">' +
                '<p class="myvh-invoices-empty-state__title">' + escapeHtml(title) + '</p>' +
                detailHtml +
                '</div>';
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
            const currentRoute = getHashRoute(currentHash);
            const currentRoutePage = routeAliases[currentRoute.page] || currentRoute.page;
            const hasInvoiceFiltersInHash = currentHash.indexOf('#invoices?') === 0 && (
                currentHash.indexOf('statuses=') > -1 ||
                currentHash.indexOf('start_date=') > -1 ||
                currentHash.indexOf('end_date=') > -1
            );

            let initialTab = hasInvoiceFiltersInHash ? 'list' : '';

            if (!initialTab) {
                try {
                    initialTab = window.localStorage.getItem(storageKey) || '';
                } catch (e) {
                    initialTab = '';
                }
            }

            if (!initialTab && currentRoutePage === 'invoice-generate') {
                initialTab = currentRoute.params.invoice_tab || 'create';
            }

            if (!initialTab || !invoiceTabs.some((tab) => tab.dataset.invoicesTab === initialTab)) {
                initialTab = invoiceTabs[0]?.dataset.invoicesTab || 'list';
            }

            activateInvoicesTab(initialTab);
        }

        // Invoice status filter form: update hash for filtering
        const filterForm = invoicesPage.querySelector('#myvh-invoice-filter-form');
        if (filterForm) {
            const statusCheckboxes = Array.from(filterForm.querySelectorAll('input[name="statuses[]"]'));
            const defaultStatusStates = statusCheckboxes.map((checkbox) => ({
                value: checkbox.value,
                checked: checkbox.checked,
            }));

            const restoreDefaultStatusCheckboxes = () => {
                statusCheckboxes.forEach((checkbox) => {
                    const defaultState = defaultStatusStates.find((state) => state.value === checkbox.value);
                    checkbox.checked = defaultState ? defaultState.checked : checkbox.checked;
                });
            };

            const applyInvoiceFilterHash = () => {
                const clientName = (filterForm.querySelector('input[name="client_name"]') || {}).value || '';
                const clientNameMatch = (filterForm.querySelector('select[name="client_name_match"]') || {}).value || '';
                const invoiceNumber = (filterForm.querySelector('input[name="invoice_number"]') || {}).value || '';
                const invoiceNumberMatch = (filterForm.querySelector('select[name="invoice_number_match"]') || {}).value || '';
                const selected = Array.from(filterForm.querySelectorAll('input[name="statuses[]"]:checked'))
                    .map((checkbox) => checkbox.value)
                    .filter(Boolean);

                const startDate = (filterForm.querySelector('input[name="start_date"]') || {}).value || '';
                const endDate = (filterForm.querySelector('input[name="end_date"]') || {}).value || '';

                const query = new URLSearchParams();

                if (selected.length) {
                    query.set('statuses', selected.join(','));
                }

                if (clientName) {
                    query.set('client_name', clientName);
                }

                if (clientNameMatch) {
                    query.set('client_name_match', clientNameMatch);
                }

                if (invoiceNumber) {
                    query.set('invoice_number', invoiceNumber);
                }

                if (invoiceNumberMatch) {
                    query.set('invoice_number_match', invoiceNumberMatch);
                }

                if (startDate) {
                    query.set('start_date', startDate);
                }

                if (endDate) {
                    query.set('end_date', endDate);
                }

                const queryString = query.toString();
                location.hash = queryString ? '#invoices?' + queryString : '#invoices';
            };

            const resetInvoiceFilters = () => {
                const clientNameInput = filterForm.querySelector('input[name="client_name"]');
                const clientNameMatchSelect = filterForm.querySelector('select[name="client_name_match"]');
                const invoiceNumberInput = filterForm.querySelector('input[name="invoice_number"]');
                const invoiceNumberMatchSelect = filterForm.querySelector('select[name="invoice_number_match"]');
                const startDateInput = filterForm.querySelector('input[name="start_date"]');
                const endDateInput = filterForm.querySelector('input[name="end_date"]');

                if (clientNameInput) {
                    clientNameInput.value = '';
                }

                if (clientNameMatchSelect) {
                    clientNameMatchSelect.value = 'contains';
                }

                if (invoiceNumberInput) {
                    invoiceNumberInput.value = '';
                }

                if (invoiceNumberMatchSelect) {
                    invoiceNumberMatchSelect.value = 'contains';
                }

                if (startDateInput) {
                    startDateInput.value = getDefaultInvoiceStartDate();
                }

                if (endDateInput) {
                    endDateInput.value = getDefaultInvoiceEndDate();
                }

                restoreDefaultStatusCheckboxes();
                applyInvoiceFilterHash();
            };

            const getDefaultInvoiceEndDate = () => {
                const today = new Date();
                return today.toISOString().slice(0, 10);
            };

            const getDefaultInvoiceStartDate = () => {
                const date = new Date();
                date.setMonth(date.getMonth() - 1);
                return date.toISOString().slice(0, 10);
            };

            const setDateRange = (startDate, endDate) => {
                const startInput = filterForm.querySelector('input[name="start_date"]');
                const endInput = filterForm.querySelector('input[name="end_date"]');

                if (startInput && startDate) {
                    startInput.value = startDate;
                }

                if (endInput && endDate) {
                    endInput.value = endDate;
                }
            };

            invoicesPage.addEventListener('click', (event) => {
                const quickRangeButton = event.target.closest('[data-invoice-date-range]');
                if (!quickRangeButton || !filterForm.contains(quickRangeButton)) {
                    return;
                }

                event.preventDefault();
                setDateRange(
                    quickRangeButton.getAttribute('data-start-date') || '',
                    quickRangeButton.getAttribute('data-end-date') || ''
                );
                applyInvoiceFilterHash();
            });

            const resetButton = filterForm.querySelector('[data-invoice-filter-reset]');
            if (resetButton) {
                resetButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    resetInvoiceFilters();
                });
            }

            filterForm.addEventListener('submit', function (event) {
                event.preventDefault();
                applyInvoiceFilterHash();
            });
        }

        // Single/recurring booking split tabs in Create Invoices panel
        const bookingTypeTabs = Array.from(invoicesPage.querySelectorAll('.myvh-booking-type-tab'));
        const bookingTypePanels = Array.from(invoicesPage.querySelectorAll('[data-booking-type-panel]'));

        if (bookingTypeTabs.length && bookingTypePanels.length) {
            const activateBookingTypeTab = (tabKey) => {
                bookingTypeTabs.forEach((tab) => {
                    const isActive = tab.dataset.bookingTypeTab === tabKey;
                    tab.classList.toggle('is-active', isActive);
                    tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                bookingTypePanels.forEach((panel) => {
                    const isActive = panel.dataset.bookingTypePanel === tabKey;
                    panel.classList.toggle('is-active', isActive);
                    panel.hidden = !isActive;

                    const panelCheckboxes = Array.from(panel.querySelectorAll('.myvh-uninvoiced-checkbox'));
                    panelCheckboxes.forEach((checkbox) => {
                        checkbox.disabled = !isActive;
                    });
                });
            };

            invoicesPage.addEventListener('click', (event) => {
                const clickedBookingTypeTab = event.target.closest('[data-booking-type-tab]');
                if (!clickedBookingTypeTab || !invoicesPage.contains(clickedBookingTypeTab)) {
                    return;
                }

                event.preventDefault();
                activateBookingTypeTab(clickedBookingTypeTab.dataset.bookingTypeTab || 'single');
            });

            const currentRoute = getHashRoute(window.location.hash || '');
            const currentRoutePage = routeAliases[currentRoute.page] || currentRoute.page;
            let initialBookingType = bookingTypeTabs[0]?.dataset.bookingTypeTab || 'single';

            if (currentRoutePage === 'invoice-generate') {
                const requestedBookingType = (currentRoute.params.booking_type || '').toLowerCase();
                if (bookingTypeTabs.some((tab) => (tab.dataset.bookingTypeTab || '') === requestedBookingType)) {
                    initialBookingType = requestedBookingType;
                }
            }

            activateBookingTypeTab(initialBookingType);
        }

        // Expand/collapse recurring booking rows by pattern group
        invoicesPage.addEventListener('click', function (event) {
            const recurringToggle = event.target.closest('.myvh-recurring-group-toggle');
            if (!recurringToggle) {
                return;
            }

            event.preventDefault();

            const groupId = recurringToggle.getAttribute('data-recurring-group') || '';
            if (!groupId) {
                return;
            }

            const isExpanded = recurringToggle.getAttribute('aria-expanded') === 'true';
            recurringToggle.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');

            const childRows = Array.from(invoicesPage.querySelectorAll('[data-recurring-group-child="' + groupId + '"]'));
            childRows.forEach((row) => {
                row.hidden = isExpanded;
            });
        });

        // Email invoice handler
        invoicesPage.addEventListener('click', async function (event) {
            const emailBtn = event.target.closest('[data-invoice-email]');
            if (!emailBtn) {
                return;
            }

            event.preventDefault();

            const invoiceId = parseInt(emailBtn.getAttribute('data-invoice-email'), 10);
            if (!invoiceId || invoiceId <= 0) {
                await portalAlert('Invalid invoice ID');
                return;
            }

            if (!(await portalConfirm('Send this invoice to the customer via email?'))) {
                return;
            }

            emailBtn.disabled = true;

            window.MyvhPortalAjax.post('myvh_portal_email_invoice', {
                invoice_id: invoiceId
            }, { scope: 'portal' })
                .then(res => {
                    emailBtn.disabled = false;
                    if (!res.success) {
                        portalAlert('Error: ' + (res.data?.message || 'Failed to email invoice'));
                        return;
                    }

                    // Update the status badge in the table row
                    const row = emailBtn.closest('tr');
                    if (row) {
                        const statusBadge = row.querySelector('.myvh-status-badge');
                        if (statusBadge) {
                            const newStatus = res.data?.status || 'sent';
                            const statusLabel = res.data?.status_label || 'Sent';
                            statusBadge.className = 'myvh-status-badge myvh-status-' + newStatus;
                            statusBadge.textContent = statusLabel;
                        }
                    }

                    portalAlert(res.data?.message || 'Invoice emailed successfully');
                })
                .catch(err => {
                    emailBtn.disabled = false;
                    portalAlert('Error: Unexpected error while emailing invoice');
                    console.error(err);
                });
        });

        // Select all/clear all for the currently selected booking type only
        invoicesPage.addEventListener('click', function (event) {
            const selectAllBtn = event.target.closest('.myvh-select-all-uninvoiced');
            const clearAllBtn = event.target.closest('.myvh-clear-all-uninvoiced');
            if (!selectAllBtn && !clearAllBtn) {
                return;
            }

            const bookingType = (selectAllBtn || clearAllBtn).getAttribute('data-booking-type') || '';
            if (!bookingType) {
                return;
            }

            const typedCheckboxes = Array.from(
                invoicesPage.querySelectorAll('.myvh-uninvoiced-checkbox[data-booking-type="' + bookingType + '"]:not(:disabled)')
            );

            if (selectAllBtn) {
                typedCheckboxes.forEach((checkbox) => {
                    checkbox.checked = true;
                });
            }

            if (clearAllBtn) {
                typedCheckboxes.forEach((checkbox) => {
                    checkbox.checked = false;
                });
            }
        });
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
            const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
            if (!submitButton) return;

            // Read tracked fields dynamically so add/remove notice rows are included.
            const getTrackedFields = () => Array.from(form.querySelectorAll('input, select, textarea'))
                .filter((field) => field.name && !field.disabled)
                .filter((field) => field.type !== 'hidden' || field.hasAttribute('data-myvh-media-input'));

            // Capture current state for dirty check
            const captureState = () => JSON.stringify(
                getTrackedFields().map((field) => {
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

            // Flatpickr updates can be async/programmatic; defer to ensure value is committed first.
            const syncDirtyStateDeferred = () => {
                window.setTimeout(syncDirtyState, 0);
            };

            // Use delegated listeners so dynamically added settings fields are tracked.
            form.addEventListener('input', syncDirtyState);
            form.addEventListener('change', syncDirtyState);
            form.addEventListener('myvh:flatpickr-change', syncDirtyStateDeferred);
            form.addEventListener('click', (event) => {
                const target = event.target;
                if (!target) {
                    return;
                }

                if (target.closest('.myvh-notice-add-row') || target.closest('.myvh-notice-remove')) {
                    syncDirtyState();
                }
            });

            if (window.MyvhFlatpickr && typeof window.MyvhFlatpickr.initWithin === 'function') {
                window.MyvhFlatpickr.initWithin(form, {
                    '[data-myvh-picker="date"]': {
                        onChange: [syncDirtyStateDeferred],
                        onValueUpdate: [syncDirtyStateDeferred]
                    }
                });
            }

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
     * Initialize portal settings media picker fields (logo upload/select/remove).
     */
    function initSettingsMediaFields(settingsPage) {
        if (!window.wp || !window.wp.media) {
            return;
        }

        const mediaFields = Array.from(settingsPage.querySelectorAll('[data-myvh-media-field]'));
        if (!mediaFields.length) {
            return;
        }

        const updatePreview = (field, url) => {
            const preview = field.querySelector('[data-myvh-media-preview]');
            const clearButton = field.querySelector('[data-myvh-media-clear]');

            if (!preview || !clearButton) {
                return;
            }

            if (url) {
                preview.classList.add('has-image');
                preview.innerHTML = '<img src="' + url.replace(/"/g, '&quot;') + '" alt="Selected logo">';
                clearButton.style.display = '';
                return;
            }

            preview.classList.remove('has-image');
            preview.innerHTML = '';
            clearButton.style.display = 'none';
        };

        mediaFields.forEach((field) => {
            if (field.dataset.mediaBound === '1') {
                return;
            }

            const input = field.querySelector('[data-myvh-media-input]');
            const selectButton = field.querySelector('[data-myvh-media-select]');
            const clearButton = field.querySelector('[data-myvh-media-clear]');

            if (!input || !selectButton || !clearButton) {
                return;
            }

            selectButton.addEventListener('click', () => {
                const frame = window.wp.media({
                    title: 'Select portal logo',
                    button: { text: 'Use this logo' },
                    multiple: false,
                    library: { type: 'image' }
                });

                frame.on('select', () => {
                    const attachment = frame.state().get('selection').first().toJSON();
                    const selectedUrl = attachment.url || '';
                    input.value = selectedUrl;
                    updatePreview(field, selectedUrl);
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });

                frame.open();
            });

            clearButton.addEventListener('click', () => {
                input.value = '';
                updatePreview(field, '');
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });

            field.dataset.mediaBound = '1';
        });
    }

    /**
     * Submit a portal action via AJAX POST.
     * @param {string} action - The WP AJAX action name
     * @param {HTMLFormElement|Object} payload - Form element or key/value payload
     * @returns {Promise<object>} - The JSON response
     */
    function postPortalForm(action, payload) {
        return window.MyvhPortalAjax.post(action, payload, { scope: 'portal' });
    }

    function resolveMessageText(payload, fallbackText) {
        if (typeof payload === 'string' && payload.trim() !== '') {
            return payload;
        }

        if (!payload || typeof payload !== 'object') {
            return fallbackText || '';
        }

        if (typeof payload.message === 'string' && payload.message.trim() !== '') {
            return payload.message;
        }

        if (payload.errors && typeof payload.errors === 'object') {
            for (const key in payload.errors) {
                if (!Object.prototype.hasOwnProperty.call(payload.errors, key)) {
                    continue;
                }

                const value = payload.errors[key];
                if (Array.isArray(value) && value.length > 0 && typeof value[0] === 'string' && value[0].trim() !== '') {
                    return value[0];
                }

                if (typeof value === 'string' && value.trim() !== '') {
                    return value;
                }
            }
        }

        if (Array.isArray(payload) && payload.length > 0) {
            const first = payload.find(item => typeof item === 'string' && item.trim() !== '');
            if (first) {
                return first;
            }
        }

        return fallbackText || '';
    }

    /**
     * Show a message in a target element, with color for error/success.
     */
    function showMessage(target, text, isError, fallbackText = '') {
        if (!target) return;
        target.textContent = resolveMessageText(text, fallbackText);
        target.style.color = isError ? '#b32d2e' : '#2d5a27';
    }

    function formatMoney(value) {
        const amount = Number.parseFloat(String(value || 0));
        if (!Number.isFinite(amount)) {
            return '£0.00';
        }

        return '£' + amount.toFixed(2);
    }

    function renderRoomRateTesterResult(data) {
        const output = document.getElementById('myvh-room-rate-tester-output');
        if (!output) {
            return;
        }

        const charge = data && data.charge ? data.charge : {};
        const usedRates = Array.isArray(charge.used_rates) ? charge.used_rates : [];
        const segments = Array.isArray(charge.segments) ? charge.segments : [];

        const usedRatesMarkup = usedRates.length > 0
            ? '<ul style="margin:0; padding-left:18px;">' + usedRates.map((usedRate) => {
                const id = usedRate.room_rate_id || 0;
                const name = String(usedRate.name || '').trim() || 'Unnamed rate';
                const scope = String(usedRate.scope || '').trim();
                const scopeLabel = scope ? ' (' + scope + ')' : '';

                return '<li>' + name + ' [ID ' + id + ']' + scopeLabel + '</li>';
            }).join('') + '</ul>'
            : '<p style="margin:0;">No room rate details were returned.</p>';

        const segmentRows = segments.length > 0
            ? segments.map((segment) => {
                const segmentName = String(segment.room_rate_name || '').trim() || 'Unnamed rate';
                const segmentId = segment.room_rate_id || 0;
                return '' +
                    '<tr>' +
                        '<td><span class="myvh-date-time-value">' + (segment.start || '-') + '</span></td>' +
                        '<td><span class="myvh-date-time-value">' + (segment.end || '-') + '</span></td>' +
                        '<td>' + segmentName + ' [ID ' + segmentId + ']</td>' +
                        '<td>' + (segment.scope || '-') + '</td>' +
                        '<td>' + (segment.charge_type || '-') + '</td>' +
                        '<td>' + (segment.rate ? formatMoney(segment.rate) : '-') + '</td>' +
                        '<td>' + (segment.hours || '0') + '</td>' +
                        '<td>' + formatMoney(segment.subtotal || 0) + '</td>' +
                    '</tr>';
            }).join('')
            : '<tr><td colspan="8">No matching schedule segments were returned.</td></tr>';

        output.innerHTML = '' +
            '<div class="myvh-card" style="padding:16px; border:1px solid #e0d8cd; background:#fff9f2;">' +
                '<h3 style="margin-top:0;">Test Result</h3>' +
                '<p style="margin:0 0 8px;"><strong>Total:</strong> ' + formatMoney(charge.total || 0) + '</p>' +
                '<p style="margin:0 0 8px;"><strong>Quantity:</strong> ' + (charge.quantity || 0) + ' hour(s)</p>' +
                '<p style="margin:0 0 8px;"><strong>Unit Price:</strong> ' + formatMoney(charge.unit_price || 0) + '</p>' +
                '<p style="margin:0 0 12px;"><strong>Validity Date Used:</strong> <span class="myvh-date-time-value">' + (charge.validity_reference_date || '-') + '</span></p>' +
                '<div style="margin:0 0 12px;"><strong>Room Rate(s) Used:</strong>' + usedRatesMarkup + '</div>' +
                '<table class="myvh-customer-list-table" style="width:100%;">' +
                    '<thead><tr><th>Start</th><th>End</th><th>Room Rate</th><th>Scope</th><th>Type</th><th>Rate</th><th>Hours</th><th>Subtotal</th></tr></thead>' +
                    '<tbody>' + segmentRows + '</tbody>' +
                '</table>' +
            '</div>';

        output.style.display = '';
    }

    /**
     * Escape text for safe inline HTML rendering.
     * @param {string} value
     * @returns {string}
     */
    function escapeHtmlText(value) {
        return String(value).replace(/[&<>"']/g, function (character) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
        });
    }

    /**
     * Load a portal page via AJAX and re-initialize widgets.
     * @param {string} page - The page slug
     * @param {object} params - Extra query params
     */
    function loadPage(page, params = {}) {

        const legacyBookingAction = getLegacyBookingAction(page, params);
        const resolvedPage = legacyBookingAction ? legacyBookingAction.page : page;

        const query = new URLSearchParams({
            action: 'myvh_portal_page',
            page: resolvedPage,
            nonce: myvhPortal.nonce,
        });

        Object.keys(params || {}).forEach((key) => {
            if (key === 'action' || key === 'page' || key === 'nonce') {
                return;
            }

            if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
                query.set(key, params[key]);
            }
        });

        fetch(
            myvhPortal.ajax_url
            + "?" + query.toString()
        )
            .then(async (response) => {
                const text = await response.text();
                return {
                    ok: response.ok,
                    status: response.status,
                    text,
                    contentType: response.headers.get('content-type') || '',
                };
            })
            .then(({ ok, status, text, contentType }) => {
                const portalContent = document.getElementById('portal-content');
                if (!portalContent) {
                    return;
                }

                const trimmed = (text || '').trim();
                let html = text;

                const looksLikeJson = contentType.includes('application/json') || (trimmed.startsWith('{') && trimmed.endsWith('}'));
                if (looksLikeJson) {
                    try {
                        const payload = JSON.parse(trimmed);
                        if (payload && payload.success === false) {
                            const message = resolveMessageText(payload.data, 'Unable to load this portal page.');
                            html = '<div class="myvh-card"><p class="myvh-error">' + escapeHtmlText(message) + '</p></div>';
                        }
                    } catch (_error) {
                        // Keep non-JSON text untouched.
                    }
                }

                if (!trimmed) {
                    html = '<div class="myvh-card"><p class="myvh-error">Unable to load this portal page. Please refresh and try again.</p></div>';
                }

                if (!ok && (!html || !html.trim())) {
                    html = '<div class="myvh-card"><p class="myvh-error">Unable to load this portal page (HTTP ' + status + ').</p></div>';
                }

                portalContent.innerHTML = html;
                initPortalPage();

                if (legacyBookingAction) {
                    runWhenPortalBookingFlowReady(legacyBookingAction.run);
                }
            })
            .catch(() => {
                const portalContent = document.getElementById('portal-content');
                if (!portalContent) {
                    return;
                }

                portalContent.innerHTML = '<div class="myvh-card"><p class="myvh-error">Unable to load this portal page. Please check your connection and try again.</p></div>';
            });
    }

    /**
     * Navigate using hash and force router when destination hash is unchanged.
     * @param {string} targetHash - Hash route with or without leading #
     */
    function navigateToHash(targetHash) {
        if (!targetHash) {
            return;
        }

        const normalized = targetHash.charAt(0) === '#' ? targetHash : ('#' + targetHash);

        if (window.location.hash === normalized) {
            router();
            return;
        }

        window.location.hash = normalized;
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

    initPortalNavigation();

    // Ensure in-content hash links still work when clicking the same route twice.
    document.getElementById('portal-content').addEventListener('click', function (e) {
        const dashboardInvoiceRow = e.target.closest('[data-dashboard-invoice-route]');
        if (dashboardInvoiceRow) {
            const clickedControl = e.target.closest('a, button, input, select, textarea, label');
            if (!clickedControl) {
                e.preventDefault();

                const rowHash = dashboardInvoiceRow.getAttribute('data-dashboard-invoice-route') || '';
                if (rowHash) {
                    navigateToHash(rowHash);
                }
                return;
            }
        }

        const link = e.target.closest('a[href^="#"]');
        if (!link) {
            return;
        }

        // Booking table action icons are handled by Bookings.bindActions().
        // Let that handler own edit/view/delete so we do not invoke legacy
        // booking actions twice from both listeners.
        if (link.closest('.myvh-booking-actions-inline') && link.classList.contains('myvh-action-icon')) {
            return;
        }

        const href = link.getAttribute('href') || '';
        if (!href || href === '#') {
            return;
        }

        e.preventDefault();

        const route = getHashRoute(href);
        const resolvedPage = routeAliases[route.page] || route.page;
        const legacyBookingAction = getLegacyBookingAction(resolvedPage, route.params);

        if (legacyBookingAction) {
            runWhenPortalBookingFlowReady(legacyBookingAction.run);
            return;
        }

        navigateToHash(href);
    });

    document.addEventListener('myvh:portal-booking-changed', function() {
        const currentRoute = getHashRoute(window.location.hash || '#dashboard');
        const currentPage = routeAliases[currentRoute.page] || currentRoute.page;

        if (currentPage === 'calendar') {
            return;
        }

        // If on a legacy booking action route (e.g. #new-booking), navigate to the
        // resolved page instead of re-running router() which would re-trigger the
        // booking modal action again.
        const legacyAction = getLegacyBookingAction(currentRoute.page, currentRoute.params);
        if (legacyAction) {
            navigateToHash(legacyAction.page);
            return;
        }

        router();
    });

    // Delegate form submission handling for all portal forms
    document.getElementById('portal-content').addEventListener('submit', async function (e) {
        const form = e.target;

        // Account details form
        if (form.id === 'myvh-account-details-form') {
            e.preventDefault();

            const message = document.getElementById('myvh-account-details-message');
            showMessage(message, 'Saving...', false);

            postPortalForm('myvh_portal_update_account', form)
                .then(res => {
                    if (!res.success) {
                        showMessage(message, res.data, true, 'Failed to update details');
                        return;
                    }
                    syncPortalAccountLabel(res.data?.display_name || form.elements.name?.value);
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
                        showMessage(message, res.data, true, 'Failed to update password');
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

        if (form.id === 'myvh-portal-invoice-status-form') {
            e.preventDefault();

            const message = form.querySelector('[data-invoice-status-message]');
            showMessage(message, 'Updating invoice status...', false);

            postPortalForm('myvh_portal_update_invoice_status', form)
                .then(res => {
                    if (!res.success) {
                        showMessage(message, res.data, true, 'Failed to update invoice status');
                        return;
                    }

                    const badges = Array.from(document.querySelectorAll('[data-invoice-status-badge]'));
                    const statusValues = Array.from(document.querySelectorAll('[data-current-invoice-status]'));
                    const updatedAtValue = document.querySelector('[data-invoice-updated-at]');
                    const nextStatus = res.data?.status || '';
                    const nextLabel = res.data?.status_label || nextStatus;
                    const updatedAtLocal = res.data?.updated_at_local || '';

                    if (badges.length && nextStatus) {
                        badges.forEach(badge => {
                            badge.className = 'myvh-status-badge myvh-status-' + nextStatus;
                            badge.textContent = nextLabel;
                        });
                    }

                    if (statusValues.length && nextLabel) {
                        statusValues.forEach(statusValue => {
                            statusValue.textContent = nextLabel;
                        });
                    }

                    if (updatedAtValue && updatedAtLocal) {
                        updatedAtValue.textContent = updatedAtLocal;
                    }

                    showMessage(message, res.data?.message || 'Invoice status updated', false);
                })
                .catch(() => {
                    showMessage(message, 'Unexpected error updating invoice status', true);
                });

            return;
        }

        if (form.id === 'myvh-room-rate-tester-form') {
            e.preventDefault();

            const message = document.getElementById('myvh-room-rate-tester-message');
            const output = document.getElementById('myvh-room-rate-tester-output');
            if (output) {
                output.style.display = 'none';
                output.innerHTML = '';
            }

            showMessage(message, 'Testing...', false);

            postPortalForm('myvh_portal_test_room_rate', form)
                .then(res => {
                    if (!res.success) {
                        showMessage(message, res.data, true, 'Test failed');
                        return;
                    }

                    showMessage(message, res.message || 'Test complete', false);
                    renderRoomRateTesterResult(res.data || {});
                })
                .catch(() => {
                    showMessage(message, 'Unexpected error while testing rate schedule', true);
                });

            return;
        }

        // Generic portal action forms (with data-portal-action)
        const portalAction = form.dataset.portalAction;
        if (portalAction) {
            e.preventDefault();

            const confirmMessage = form.dataset.confirm || '';
            if (confirmMessage && !(await portalConfirm(confirmMessage))) {
                return;
            }

            const messageTargetId = form.dataset.messageTarget || '';
            const reloadPage = form.dataset.reloadPage || '';
            const message = messageTargetId ? document.getElementById(messageTargetId) : null;

            showMessage(message, 'Saving...', false);

            postPortalForm(portalAction, form)
                .then(res => {
                    if (!res.success) {
                        showMessage(message, res.data, true, 'Request failed');
                        return;
                    }

                    if (form.tagName === 'FORM') {
                        form.reset();
                    }

                    showMessage(message, res.data?.message || 'Saved', false);

                    const redirectRoute = res.data?.redirect || reloadPage;
                    if (redirectRoute) {
                        setTimeout(() => navigateToHash(redirectRoute), 200);
                    }
                })
                .catch(() => {
                    showMessage(message, 'Unexpected error while saving', true);
                });
        }
    });

    // Send password reset email handler
    document.getElementById('portal-content').addEventListener('click', async function (e) {
        const button = e.target.closest('.myvh-send-password-reset, .myvh-send-password-reset-btn');
        if (!button) {
            return;
        }

        e.preventDefault();

        const customerId = button.dataset.customerId;
        if (!customerId) {
            await portalAlert('Invalid customer ID');
            return;
        }

        if (button.disabled) {
            return;
        }

        button.disabled = true;
        const originalText = button.textContent;
        button.textContent = 'Sending...';

        postPortalForm('myvh_portal_send_password_reset', { customer_id: customerId })
            .then(res => {
                button.disabled = false;
                button.textContent = originalText;

                if (!res.success) {
                    portalAlert('Error: ' + (res.data?.message || res.data || 'Failed to send email'));
                    return;
                }

                portalAlert(res.data?.message || 'Password reset email sent successfully');
            })
            .catch(() => {
                button.disabled = false;
                button.textContent = originalText;
                portalAlert('An error occurred. Please try again.');
            });
    });

    // Initial page load
    router();
});

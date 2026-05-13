











// Bookings list and actions for My Village Hall

window.Bookings = (function() {

    let globalHandlersBound = false;
    let boundFilterRoot = null;

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

    function runWhenBookingFlowReady(action) {
        if (typeof action !== 'function') {
            return;
        }

        if (window.MyvhPortalCalendarFlow) {
            action();
            return;
        }

        let onReady = function() {
            document.removeEventListener('myvh:portal-booking-flow-ready', onReady);
            action();
        };

        document.addEventListener('myvh:portal-booking-flow-ready', onReady, { once: true });
    }

    function getRowDataValue(row, name) {
        let value = row.getAttribute('data-' + name);
        if (value === null || value === undefined) {
            return '';
        }

        return String(value);
    }

    function normalizeTimeValue(timeValue) {
        let raw = String(timeValue || '').trim();
        if (!raw) {
            return '';
        }

        if (/^\d{2}:\d{2}:\d{2}$/.test(raw)) {
            return raw;
        }

        if (/^\d{2}:\d{2}$/.test(raw)) {
            return raw + ':00';
        }

        return raw;
    }

    function toDateTimeString(dateValue, timeValue) {
        let date = String(dateValue || '').trim();
        let time = normalizeTimeValue(timeValue);

        if (!date) {
            return '';
        }

        if (!time) {
            return date;
        }

        return date + ' ' + time;
    }

    function buildPrefillFromRow(row) {
        let startDate = getRowDataValue(row, 'start-date');
        let endDate = getRowDataValue(row, 'end-date') || startDate;

        return {
            start: toDateTimeString(startDate, getRowDataValue(row, 'start-time')),
            end: toDateTimeString(endDate, getRowDataValue(row, 'end-time')),
            roomId: getRowDataValue(row, 'room-id'),
            roomName: getRowDataValue(row, 'room-name'),
            customerId: getRowDataValue(row, 'customer'),
            customerName: getRowDataValue(row, 'customer-name'),
            organisationId: getRowDataValue(row, 'organisation'),
            organisationName: getRowDataValue(row, 'organisation-name'),
            description: getRowDataValue(row, 'description'),
            status: getRowDataValue(row, 'status')
        };
    }

    function handlePortalBookingActionClick(event) {
        let actionLink = event.target.closest('.myvh-booking-actions-inline .myvh-action-icon[href^="#booking-"]');
        if (!actionLink) {
            return;
        }

        let row = actionLink.closest('tr.myvh-bookings-table-row');
        if (!row || !row.hasAttribute('data-booking-id')) {
            return;
        }

        let bookingId = parseInt(row.getAttribute('data-booking-id') || '0', 10);
        if (!bookingId) {
            return;
        }

        let href = String(actionLink.getAttribute('href') || '');
        if (!href) {
            return;
        }

        let prefill = buildPrefillFromRow(row);

        event.preventDefault();

        runWhenBookingFlowReady(function() {
            let flow = window.MyvhPortalCalendarFlow;
            if (!flow) {
                return;
            }

            if (href.indexOf('#booking-edit') === 0 && typeof flow.openEdit === 'function') {
                flow.openEdit(bookingId, { prefill: prefill });
                return;
            }

            if (href.indexOf('#booking-delete') === 0 && typeof flow.deleteBooking === 'function') {
                flow.deleteBooking(bookingId);
                return;
            }

            if (typeof flow.openView === 'function') {
                flow.openView(bookingId, { prefill: prefill });
            }
        });
    }

    /**
     * Filter bookings table rows by selected statuses.
     */
    function filterByStatus() {
        let checked = Array.from(document.querySelectorAll('.myvh-status-filter:checked')).map(function(cb) {
            return cb.value;
        });

        // Filter rows with data-status attribute (child rows and standalone bookings)
        let rows = document.querySelectorAll('#myvh-bookings-table tbody tr[data-status]');
        rows.forEach(function(row) {
            let status = row.getAttribute('data-status');
            if (!status || checked.indexOf(status) !== -1) {
                row.classList.remove('myvh-hidden-by-filter');
            } else {
                row.classList.add('myvh-hidden-by-filter');
            }
        });

        // Hide/show group headers based on whether any children match the filter
        let groupHeaders = document.querySelectorAll('#myvh-bookings-table tbody tr.myvh-booking-group-header');
        groupHeaders.forEach(function(header) {
            let groupId = header.getAttribute('data-group');
            if (!groupId) {
                return;
            }

            let childRows = document.querySelectorAll('tr.myvh-recurring-child[data-group="' + groupId + '"]');
            let hasVisibleChild = false;

            childRows.forEach(function(child) {
                if (!child.classList.contains('myvh-hidden-by-filter')) {
                    hasVisibleChild = true;
                }
            });

            // Show or hide the group header based on visible children
            if (hasVisibleChild) {
                header.classList.remove('myvh-hidden-by-filter');
            } else {
                header.classList.add('myvh-hidden-by-filter');
            }
        });
    }

    /**
     * Bind status filter checkboxes for client-side filtering.
     */
    function bindStatusFilters() {
        let boxes = document.querySelectorAll('.myvh-status-filter');
        boxes.forEach(function(cb) {
            cb.addEventListener('change', applyAllFilters);
        });
        // Initial filter
        applyAllFilters();
    }

    /**
     * Initialize filters from URL hash parameters.
     * Parses status, room, and datePreset from the hash and applies them.
     */
    function initializeFiltersFromUrl() {
        // Parse hash parameters
        let hashParts = window.location.hash.split('?');
        if (hashParts.length < 2) {
            return; // No parameters
        }

        let params = new URLSearchParams(hashParts[1]);
        let statusParam = params.get('status');
        let roomParam = params.get('room');
        let datePresetParam = params.get('datePreset');

        // Determine which page we're on
        let bookingsTable = document.getElementById('myvh-bookings-table');
        let dashboardTable = document.getElementById('myvh-dashboard-bookings-table');
        let isBookingsPage = !!bookingsTable;
        let prefix = isBookingsPage ? 'myvh-filter-' : 'myvh-dashboard-filter-';

        // Apply status filter
        if (statusParam) {
            let statusCheckbox = document.querySelector('.myvh-status-filter[value="' + statusParam + '"]');
            if (statusCheckbox) {
                // Uncheck all, then check only the requested status
                document.querySelectorAll('.myvh-status-filter').forEach(function(cb) {
                    cb.checked = false;
                });
                statusCheckbox.checked = true;
            }
        }

        // Apply room filter
        if (roomParam) {
            let roomSelect = document.getElementById(prefix + 'room');
            if (roomSelect) {
                roomSelect.value = decodeURIComponent(roomParam);
            }
        }

        // Apply date preset filter
        if (datePresetParam) {
            let datePresets = document.querySelectorAll('.' + prefix + 'date-preset');
            datePresets.forEach(function(btn) {
                btn.classList.remove('is-active');
                if (btn.getAttribute('data-preset') === datePresetParam) {
                    btn.classList.add('is-active');
                }
            });

            // Hide date picker if not custom preset
            if (datePresetParam !== 'custom') {
                let datePicker = document.getElementById(prefix + 'date-picker');
                if (datePicker) {
                    datePicker.hidden = true;
                }
            }
        }

        // Apply the filters
        applyAllFilters();
    }
    /**
     * Bind group header toggles for expanding/collapsing booking groups.
     */
    function bindGroupToggles() {
        document.addEventListener('click', function(e) {
            let header = e.target.closest('.myvh-booking-group-header');
            if (!header) {
                return;
            }

            // Ignore clicks on links inside the group header.
            if (e.target.closest('a')) {
                return;
            }

            let group = header.getAttribute('data-group');
            let groupToggle = header.querySelector('.myvh-group-toggle');
            let isOpen = !header.classList.contains('is-open');

            header.classList.toggle('is-open', isOpen);

            if (groupToggle) {
                groupToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }

            if (!group) {
                return;
            }

            let children = document.querySelectorAll('tr.myvh-recurring-child[data-group="' + group + '"]');
            children.forEach(function(row) {
                row.classList.toggle('is-open', isOpen);
            });

            if (e.target.closest('.myvh-group-toggle')) {
                e.preventDefault();
            }
        });
    }

    /**
     * Bind actions for bookings, e.g. cancel button with confirmation.
     */
    function bindActions() {
        document.addEventListener('click', function(e) {
            handlePortalBookingActionClick(e);

            let cancelBtn = e.target.closest('.myvh-cancel-booking');
            if (!cancelBtn) {
                return;
            }

            e.preventDefault();

            portalConfirm('Cancel this booking?').then(function(confirmed) {
                if (!confirmed) {
                    return;
                }

                if (cancelBtn.tagName === 'A' && cancelBtn.href) {
                    window.location.href = cancelBtn.href;
                    return;
                }

                if (cancelBtn.form) {
                    cancelBtn.form.submit();
                }
            });
        });

        document.addEventListener('contextmenu', function(e) {
            let statusChip = e.target.closest('.myvh-status-chip');
            if (!statusChip) {
                return;
            }

            let row = statusChip.closest('tr.myvh-bookings-table-row');
            if (!row || row.getAttribute('data-status') !== 'pending') {
                return;
            }

            let bookingId = parseInt(row.getAttribute('data-booking-id') || '0', 10);
            if (!bookingId || !window.MyvhPortalAjax) {
                return;
            }

            e.preventDefault();

            if (row.dataset.confirmingStatus === '1') {
                return;
            }

            portalConfirm('Change this booking status from Pending to Confirmed?').then(function(confirmed) {
                if (!confirmed) {
                    return;
                }

                row.dataset.confirmingStatus = '1';

                return window.MyvhPortalAjax.get(
                    {
                        action: 'myvh_portal_get_booking',
                        booking_id: String(bookingId)
                    },
                    { scope: 'portal' }
                )
            .then(function(result) {
                if (!result || !result.success || !result.data || !result.data.booking) {
                    throw new Error('Unable to load booking details.');
                }

                let booking = result.data.booking;
                let startDate = booking.StartDate || '';
                let endDate = booking.EndDate || startDate;
                let startTime = String(booking.StartTime || '00:00:00').slice(0, 8);
                let endTime = String(booking.EndTime || '00:00:00').slice(0, 8);
                let payload = {
                    booking_id: String(bookingId),
                    start: startDate + ' ' + startTime,
                    end: endDate + ' ' + endTime,
                    room_id: String(booking.RoomId || ''),
                    customer_id: String(booking.CustomerId || ''),
                    organisation_id: String(booking.OrganisationId || ''),
                    text: String(booking.Description || ''),
                    status: 'confirmed',
                    public: String(parseInt(booking.Public || 0, 10) ? 1 : 0),
                    no_invoice_required: String(parseInt(booking.NoInvoiceRequired || 0, 10) ? 1 : 0)
                };

                return window.MyvhPortalAjax.post('myvh_portal_update_booking_modal', payload, { scope: 'portal' });
            })
            .then(function(result) {
                if (!result || !result.success) {
                    let message = (result && result.data) ? String(result.data) : 'Could not update booking status.';
                    throw new Error(message);
                }

                row.setAttribute('data-status', 'confirmed');
                statusChip.className = statusChip.className.replace(/\bis-[^\s]+\b/g, '').trim() + ' is-confirmed';
                statusChip.textContent = 'Confirmed';
                applyAllFilters();
            })
            .catch(function(err) {
                return portalAlert(err && err.message ? err.message : 'Could not update booking status.');
            })
            .finally(function() {
                delete row.dataset.confirmingStatus;
            });
            });
        });
    }

    /**
     * Apply all active filters to the bookings table.
     */
    function applyAllFilters() {
        let rows = document.querySelectorAll('.myvh-bookings-table-row, .myvh-booking-group-header');
        let groups = {};

        rows.forEach(function(row) {
            if (row.classList.contains('myvh-booking-group-header')) {
                return; // Skip group headers for now
            }

            // Check all filter conditions
            let filterState = getFilterState();
            let matchesStatus = checkStatusFilter(row);
            let matchesRoom = !filterState.room || row.getAttribute('data-room') === filterState.room;
            let matchesCustomer = !filterState.customer || row.getAttribute('data-customer') === filterState.customer;
            let matchesOrg = !filterState.organisation || row.getAttribute('data-organisation') === filterState.organisation;
            let matchesDescription = !filterState.description || (row.getAttribute('data-description-search') || '').includes(filterState.description.toLowerCase());
            let matchesDate = checkDateFilter(row);

            let isVisible = matchesStatus && matchesRoom && matchesCustomer && matchesOrg && matchesDescription && matchesDate;

            row.classList.toggle('myvh-hidden-by-filter', !isVisible);

            // Track group visibility
            let groupId = row.getAttribute('data-group');
            if (!groups[groupId]) {
                groups[groupId] = false;
            }
            if (isVisible) {
                groups[groupId] = true;
            }
        });

        // Show/hide group headers based on child visibility
        let groupHeaders = document.querySelectorAll('.myvh-booking-group-header');
        groupHeaders.forEach(function(header) {
            let groupId = header.getAttribute('data-group');
            header.classList.toggle('myvh-hidden-by-filter', !groups[groupId]);
        });
    }

    /**
     * Get current filter state from UI elements.
     */
    function getFilterState() {
        // Determine which filter container we're in (bookings page vs dashboard)
        let bookingsTable = document.getElementById('myvh-bookings-table');
        let dashboardTable = document.getElementById('myvh-dashboard-bookings-table');
        let isBookingsPage = !!bookingsTable;

        let prefix = isBookingsPage ? 'myvh-filter-' : 'myvh-dashboard-filter-';

        return {
            room: getSelectValue(prefix + 'room'),
            customer: getSelectValue(prefix + 'customer'),
            organisation: getSelectValue(prefix + 'organisation'),
            datePreset: getDatePreset(prefix),
            dateStart: getDateInput(prefix + 'date-start'),
            dateEnd: getDateInput(prefix + 'date-end'),
            description: (getInputValue(prefix + 'description') || '').toLowerCase()
        };
    }

    /**
     * Get value from a select element.
     */
    function getSelectValue(elementId) {
        let el = document.getElementById(elementId);
        return el ? el.value : '';
    }

    /**
     * Get value from a text input element.
     */
    function getInputValue(elementId) {
        let el = document.getElementById(elementId);
        return el ? el.value : '';
    }

    /**
     * Get Date object from a date input element.
     */
    function getDateInput(elementId) {
        let el = document.getElementById(elementId);
        if (!el || !el.value) {
            return null;
        }
        return new Date(el.value);
    }

    /**
     * Get the currently selected date preset.
     */
    function getDatePreset(prefix) {
        let activePreset = document.querySelector('.' + prefix + 'date-preset.is-active');
        if (activePreset) {
            return activePreset.getAttribute('data-preset') || 'all';
        }
        return 'all';
    }

    /**
     * Check if a row matches the status filter.
     */
    function checkStatusFilter(row) {
        let status = row.getAttribute('data-status');
        let checkbox = document.querySelector('.myvh-status-filter[value="' + status + '"]');
        return checkbox ? checkbox.checked : true;
    }

    /**
     * Check if a row matches the date filter.
     */
    function checkDateFilter(row) {
        let bookingDateStr = row.getAttribute('data-booking-date');
        if (!bookingDateStr) {
            return true;
        }

        let filterState = getFilterState();
        let bookingDate = new Date(bookingDateStr);
        let today = new Date();
        today.setHours(0, 0, 0, 0);

        if (filterState.datePreset === 'upcoming') {
            return bookingDate >= today;
        } else if (filterState.datePreset === 'past') {
            return bookingDate < today;
        } else if (filterState.datePreset === 'custom') {
            let matches = true;
            if (filterState.dateStart) {
                matches = matches && bookingDate >= filterState.dateStart;
            }
            if (filterState.dateEnd) {
                let endOfDay = new Date(filterState.dateEnd);
                endOfDay.setHours(23, 59, 59, 999);
                matches = matches && bookingDate <= endOfDay;
            }
            return matches;
        }

        return true; // 'all' preset shows everything
    }

    /**
     * Setup event listeners for expanded filters.
     */
    function bindExpandedFilters() {
        // Determine which page we're on
        let bookingsTable = document.getElementById('myvh-bookings-table');
        let isBookingsPage = !!bookingsTable;
        let prefix = isBookingsPage ? 'myvh-filter-' : 'myvh-dashboard-filter-';
        let expandedFiltersId = isBookingsPage ? 'myvh-expanded-filters' : 'myvh-dashboard-expanded-filters';

        // Collapsible expanded filters toggle
        let filterToggleBtn = document.querySelector('.myvh-filter-toggle-btn');
        let expandedFilters = document.getElementById(expandedFiltersId);

        if (filterToggleBtn && expandedFilters) {
            filterToggleBtn.addEventListener('click', function() {
                let isExpanded = this.getAttribute('aria-expanded') === 'true';
                this.setAttribute('aria-expanded', !isExpanded);
                expandedFilters.hidden = isExpanded;
                let icon = this.querySelector('.myvh-filter-toggle-icon');
                if (icon) {
                    icon.style.transform = isExpanded ? '' : 'rotate(180deg)';
                }
            });
        }

        // Room filter
        let roomFilter = document.getElementById(prefix + 'room');
        if (roomFilter) {
            roomFilter.addEventListener('change', applyAllFilters);
        }

        // Customer filter
        let customerFilter = document.getElementById(prefix + 'customer');
        if (customerFilter) {
            customerFilter.addEventListener('change', applyAllFilters);
        }

        // Organisation filter
        let orgFilter = document.getElementById(prefix + 'organisation');
        if (orgFilter) {
            orgFilter.addEventListener('change', applyAllFilters);
        }

        // Date preset buttons
        let datePresets = document.querySelectorAll('.' + prefix + 'date-preset');
        datePresets.forEach(function(btn) {
            btn.addEventListener('click', function() {
                let preset = this.getAttribute('data-preset');
                let datePicker = document.getElementById(prefix + 'date-picker');

                if (preset === 'custom') {
                    if (datePicker) {
                        datePicker.hidden = false;
                    }
                    datePresets.forEach(function(b) {
                        b.classList.remove('is-active');
                    });
                    this.classList.add('is-active');
                } else {
                    if (datePicker) {
                        datePicker.hidden = true;
                    }
                    datePresets.forEach(function(b) {
                        b.classList.remove('is-active');
                    });
                    this.classList.add('is-active');
                    applyAllFilters();
                }
            });
        });

        // Set initial active preset to 'All'
        if (datePresets.length > 0) {
            datePresets[0].classList.add('is-active');
        }

        // Custom date picker inputs
        let dateStart = document.getElementById(prefix + 'date-start');
        let dateEnd = document.getElementById(prefix + 'date-end');

        if (dateStart) {
            dateStart.addEventListener('change', applyAllFilters);
        }
        if (dateEnd) {
            dateEnd.addEventListener('change', applyAllFilters);
        }

        // Description search
        let descriptionFilter = document.getElementById(prefix + 'description');
        if (descriptionFilter) {
            descriptionFilter.addEventListener('input', applyAllFilters);
        }

        // Clear filters button
        let clearBtn = document.getElementById(prefix + 'clear');
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                // Reset all filter selects
                if (roomFilter) roomFilter.value = '';
                if (customerFilter) customerFilter.value = '';
                if (orgFilter) orgFilter.value = '';
                if (descriptionFilter) descriptionFilter.value = '';
                if (dateStart) dateStart.value = '';
                if (dateEnd) dateEnd.value = '';

                // Hide date picker
                let datePicker = document.getElementById(prefix + 'date-picker');
                if (datePicker) {
                    datePicker.hidden = true;
                }

                // Reset preset buttons
                datePresets.forEach(function(b) {
                    b.classList.remove('is-active');
                });
                if (datePresets.length > 0) {
                    datePresets[0].classList.add('is-active'); // 'All' preset
                }

                // Reset visible status checkboxes to checked
                document.querySelectorAll('.myvh-status-filter').forEach(function(cb) {
                    cb.checked = true;
                });

                applyAllFilters();
            });
        }
    }

    /**
     * Initialize bookings logic (accordion, actions). Only runs once.
     */
    function init() {
        if (!globalHandlersBound) {
            bindGroupToggles();
            bindActions();
            globalHandlersBound = true;
        }

        let currentFilterRoot = document.getElementById('myvh-bookings-table') || document.getElementById('myvh-dashboard-bookings-table');
        if (!currentFilterRoot || currentFilterRoot === boundFilterRoot) {
            return;
        }

        boundFilterRoot = currentFilterRoot;
        bindStatusFilters();
        bindExpandedFilters();
        initializeFiltersFromUrl();
    }

    return {
        init: init
    };

})();
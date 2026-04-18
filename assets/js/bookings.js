











// Bookings list and actions for My Village Hall

var Bookings = (function() {

    var globalHandlersBound = false;
    var boundFilterRoot = null;

    function portalAlert(message) {
        if (window.MyvhPortalDialog && typeof window.MyvhPortalDialog.alert === 'function') {
            return window.MyvhPortalDialog.alert(message);
        }

        window.alert(message);
        return Promise.resolve(true);
    }

    function portalConfirm(message) {
        if (window.MyvhPortalDialog && typeof window.MyvhPortalDialog.confirm === 'function') {
            return window.MyvhPortalDialog.confirm(message);
        }

        return Promise.resolve(window.confirm(message));
    }

    /**
     * Filter bookings table rows by selected statuses.
     */
    function filterByStatus() {
        var checked = Array.from(document.querySelectorAll('.myvh-status-filter:checked')).map(function(cb) {
            return cb.value;
        });

        // Filter rows with data-status attribute (child rows and standalone bookings)
        var rows = document.querySelectorAll('#myvh-bookings-table tbody tr[data-status]');
        rows.forEach(function(row) {
            var status = row.getAttribute('data-status');
            if (!status || checked.indexOf(status) !== -1) {
                row.classList.remove('myvh-hidden-by-filter');
            } else {
                row.classList.add('myvh-hidden-by-filter');
            }
        });

        // Hide/show group headers based on whether any children match the filter
        var groupHeaders = document.querySelectorAll('#myvh-bookings-table tbody tr.myvh-booking-group-header');
        groupHeaders.forEach(function(header) {
            var groupId = header.getAttribute('data-group');
            if (!groupId) {
                return;
            }

            var childRows = document.querySelectorAll('tr.myvh-recurring-child[data-group="' + groupId + '"]');
            var hasVisibleChild = false;

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
        var boxes = document.querySelectorAll('.myvh-status-filter');
        boxes.forEach(function(cb) {
            cb.addEventListener('change', applyAllFilters);
        });
        // Initial filter
        applyAllFilters();
    }

    /**
     * Bind group header toggles for expanding/collapsing booking groups.
     */
    function bindGroupToggles() {
        document.addEventListener('click', function(e) {
            var header = e.target.closest('.myvh-booking-group-header');
            if (!header) {
                return;
            }

            // Ignore clicks on links inside the group header.
            if (e.target.closest('a')) {
                return;
            }

            var group = header.getAttribute('data-group');
            var groupToggle = header.querySelector('.myvh-group-toggle');
            var isOpen = !header.classList.contains('is-open');

            header.classList.toggle('is-open', isOpen);

            if (groupToggle) {
                groupToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }

            if (!group) {
                return;
            }

            var children = document.querySelectorAll('tr.myvh-recurring-child[data-group="' + group + '"]');
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
            var cancelBtn = e.target.closest('.myvh-cancel-booking');
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
            var statusChip = e.target.closest('.myvh-status-chip');
            if (!statusChip) {
                return;
            }

            var row = statusChip.closest('tr.myvh-bookings-table-row');
            if (!row || row.getAttribute('data-status') !== 'pending') {
                return;
            }

            var bookingId = parseInt(row.getAttribute('data-booking-id') || '0', 10);
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

                var booking = result.data.booking;
                var startDate = booking.StartDate || '';
                var endDate = booking.EndDate || startDate;
                var startTime = String(booking.StartTime || '00:00:00').slice(0, 8);
                var endTime = String(booking.EndTime || '00:00:00').slice(0, 8);
                var payload = {
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
                    var message = (result && result.data) ? String(result.data) : 'Could not update booking status.';
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
        var rows = document.querySelectorAll('.myvh-bookings-table-row, .myvh-booking-group-header');
        var groups = {};

        rows.forEach(function(row) {
            if (row.classList.contains('myvh-booking-group-header')) {
                return; // Skip group headers for now
            }

            // Check all filter conditions
            var filterState = getFilterState();
            var matchesStatus = checkStatusFilter(row);
            var matchesRoom = !filterState.room || row.getAttribute('data-room') === filterState.room;
            var matchesCustomer = !filterState.customer || row.getAttribute('data-customer') === filterState.customer;
            var matchesOrg = !filterState.organisation || row.getAttribute('data-organisation') === filterState.organisation;
            var matchesDescription = !filterState.description || (row.getAttribute('data-description-search') || '').includes(filterState.description.toLowerCase());
            var matchesDate = checkDateFilter(row);

            var isVisible = matchesStatus && matchesRoom && matchesCustomer && matchesOrg && matchesDescription && matchesDate;

            row.classList.toggle('myvh-hidden-by-filter', !isVisible);

            // Track group visibility
            var groupId = row.getAttribute('data-group');
            if (!groups[groupId]) {
                groups[groupId] = false;
            }
            if (isVisible) {
                groups[groupId] = true;
            }
        });

        // Show/hide group headers based on child visibility
        var groupHeaders = document.querySelectorAll('.myvh-booking-group-header');
        groupHeaders.forEach(function(header) {
            var groupId = header.getAttribute('data-group');
            header.classList.toggle('myvh-hidden-by-filter', !groups[groupId]);
        });
    }

    /**
     * Get current filter state from UI elements.
     */
    function getFilterState() {
        // Determine which filter container we're in (bookings page vs dashboard)
        var bookingsTable = document.getElementById('myvh-bookings-table');
        var dashboardTable = document.getElementById('myvh-dashboard-bookings-table');
        var isBookingsPage = !!bookingsTable;

        var prefix = isBookingsPage ? 'myvh-filter-' : 'myvh-dashboard-filter-';

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
        var el = document.getElementById(elementId);
        return el ? el.value : '';
    }

    /**
     * Get value from a text input element.
     */
    function getInputValue(elementId) {
        var el = document.getElementById(elementId);
        return el ? el.value : '';
    }

    /**
     * Get Date object from a date input element.
     */
    function getDateInput(elementId) {
        var el = document.getElementById(elementId);
        if (!el || !el.value) {
            return null;
        }
        return new Date(el.value);
    }

    /**
     * Get the currently selected date preset.
     */
    function getDatePreset(prefix) {
        var activePreset = document.querySelector('.' + prefix + 'date-preset.is-active');
        if (activePreset) {
            return activePreset.getAttribute('data-preset') || 'all';
        }
        return 'all';
    }

    /**
     * Check if a row matches the status filter.
     */
    function checkStatusFilter(row) {
        var status = row.getAttribute('data-status');
        var checkbox = document.querySelector('.myvh-status-filter[value="' + status + '"]');
        return checkbox ? checkbox.checked : true;
    }

    /**
     * Check if a row matches the date filter.
     */
    function checkDateFilter(row) {
        var bookingDateStr = row.getAttribute('data-booking-date');
        if (!bookingDateStr) {
            return true;
        }

        var filterState = getFilterState();
        var bookingDate = new Date(bookingDateStr);
        var today = new Date();
        today.setHours(0, 0, 0, 0);

        if (filterState.datePreset === 'upcoming') {
            return bookingDate >= today;
        } else if (filterState.datePreset === 'past') {
            return bookingDate < today;
        } else if (filterState.datePreset === 'custom') {
            var matches = true;
            if (filterState.dateStart) {
                matches = matches && bookingDate >= filterState.dateStart;
            }
            if (filterState.dateEnd) {
                var endOfDay = new Date(filterState.dateEnd);
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
        var bookingsTable = document.getElementById('myvh-bookings-table');
        var isBookingsPage = !!bookingsTable;
        var prefix = isBookingsPage ? 'myvh-filter-' : 'myvh-dashboard-filter-';
        var expandedFiltersId = isBookingsPage ? 'myvh-expanded-filters' : 'myvh-dashboard-expanded-filters';

        // Collapsible expanded filters toggle
        var filterToggleBtn = document.querySelector('.myvh-filter-toggle-btn');
        var expandedFilters = document.getElementById(expandedFiltersId);

        if (filterToggleBtn && expandedFilters) {
            filterToggleBtn.addEventListener('click', function() {
                var isExpanded = this.getAttribute('aria-expanded') === 'true';
                this.setAttribute('aria-expanded', !isExpanded);
                expandedFilters.hidden = isExpanded;
                var icon = this.querySelector('.myvh-filter-toggle-icon');
                if (icon) {
                    icon.style.transform = isExpanded ? '' : 'rotate(180deg)';
                }
            });
        }

        // Room filter
        var roomFilter = document.getElementById(prefix + 'room');
        if (roomFilter) {
            roomFilter.addEventListener('change', applyAllFilters);
        }

        // Customer filter
        var customerFilter = document.getElementById(prefix + 'customer');
        if (customerFilter) {
            customerFilter.addEventListener('change', applyAllFilters);
        }

        // Organisation filter
        var orgFilter = document.getElementById(prefix + 'organisation');
        if (orgFilter) {
            orgFilter.addEventListener('change', applyAllFilters);
        }

        // Date preset buttons
        var datePresets = document.querySelectorAll('.' + prefix + 'date-preset');
        datePresets.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var preset = this.getAttribute('data-preset');
                var datePicker = document.getElementById(prefix + 'date-picker');

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
        var dateStart = document.getElementById(prefix + 'date-start');
        var dateEnd = document.getElementById(prefix + 'date-end');

        if (dateStart) {
            dateStart.addEventListener('change', applyAllFilters);
        }
        if (dateEnd) {
            dateEnd.addEventListener('change', applyAllFilters);
        }

        // Description search
        var descriptionFilter = document.getElementById(prefix + 'description');
        if (descriptionFilter) {
            descriptionFilter.addEventListener('input', applyAllFilters);
        }

        // Clear filters button
        var clearBtn = document.getElementById(prefix + 'clear');
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
                var datePicker = document.getElementById(prefix + 'date-picker');
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

        var currentFilterRoot = document.getElementById('myvh-bookings-table') || document.getElementById('myvh-dashboard-bookings-table');
        if (!currentFilterRoot || currentFilterRoot === boundFilterRoot) {
            return;
        }

        boundFilterRoot = currentFilterRoot;
        bindStatusFilters();
        bindExpandedFilters();
    }

    return {
        init: init
    };

})();
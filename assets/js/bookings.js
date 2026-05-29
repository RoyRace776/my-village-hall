











// Bookings list and actions for My Village Hall

window.Bookings = (function() {

    let globalHandlersBound = false;
    let findSlotHandlersBound = false;
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

    function getTodayDateString() {
        let today = new Date();
        let month = String(today.getMonth() + 1).padStart(2, '0');
        let day = String(today.getDate()).padStart(2, '0');
        return String(today.getFullYear()) + '-' + month + '-' + day;
    }

    function getDefaultStartTime(row) {
        let fromRow = row ? String(getRowDataValue(row, 'start-time') || '').trim() : '';
        if (/^\d{2}:\d{2}(:\d{2})?$/.test(fromRow)) {
            return fromRow.slice(0, 5);
        }

        let now = new Date();
        let minutes = now.getMinutes();
        let roundedMinutes = Math.ceil(minutes / 15) * 15;
        if (roundedMinutes >= 60) {
            now.setHours(now.getHours() + 1);
            roundedMinutes = 0;
        }

        let hh = String(now.getHours()).padStart(2, '0');
        let mm = String(roundedMinutes).padStart(2, '0');
        return hh + ':' + mm;
    }

    function parsePositiveInteger(value, fallback) {
        let number = parseInt(String(value || '').trim(), 10);
        if (!Number.isFinite(number) || number <= 0) {
            return fallback;
        }

        return number;
    }

    function getBookingLengthFromRow(row) {
        if (!row) {
            return 60;
        }

        let startDate = getRowDataValue(row, 'start-date');
        let endDate = getRowDataValue(row, 'end-date') || startDate;
        let startTime = normalizeTimeValue(getRowDataValue(row, 'start-time'));
        let endTime = normalizeTimeValue(getRowDataValue(row, 'end-time'));

        if (!startDate || !startTime || !endDate || !endTime) {
            return 60;
        }

        let start = new Date(startDate + 'T' + startTime);
        let end = new Date(endDate + 'T' + endTime);
        let diffMs = end.getTime() - start.getTime();

        if (!Number.isFinite(diffMs) || diffMs <= 0) {
            return 60;
        }

        return Math.max(15, Math.round(diffMs / 60000));
    }

    async function loadRoomOptions() {
        let roomMap = new Map();

        document.querySelectorAll('tr.myvh-bookings-table-row[data-room-id][data-room-name]').forEach(function(row) {
            let roomId = parseInt(row.getAttribute('data-room-id') || '0', 10);
            let roomName = String(row.getAttribute('data-room-name') || '').trim();
            if (roomId > 0 && roomName) {
                roomMap.set(roomId, roomName);
            }
        });

        if (window.myvhCal && window.myvhCal.ajax_url && window.myvhCal.nonce) {
            try {
                let response = await fetch(
                    window.myvhCal.ajax_url + '?action=myvh_calendar_rooms&nonce=' + encodeURIComponent(window.myvhCal.nonce) + '&context=portal'
                );
                let payload = await response.json();
                let rooms = Array.isArray(payload) ? payload : (Array.isArray(payload && payload.data) ? payload.data : []);

                rooms.forEach(function(room) {
                    let roomId = parseInt((room && room.id) || 0, 10);
                    let roomName = String((room && room.name) || '').trim();
                    if (roomId > 0 && roomName) {
                        roomMap.set(roomId, roomName);
                    }
                });
            } catch (error) {
                // Fall back to room options discovered from the bookings table.
            }
        }

        return Array.from(roomMap.entries())
            .map(function(entry) {
                return { id: entry[0], name: entry[1] };
            })
            .sort(function(left, right) {
                return left.name.localeCompare(right.name);
            });
    }

    function ensureSlotFinderDialogStyles() {
        if (document.getElementById('myvh-slot-finder-styles')) {
            return;
        }

        let style = document.createElement('style');
        style.id = 'myvh-slot-finder-styles';
        style.textContent = [
            '.myvh-slot-finder-backdrop{position:fixed;inset:0;background:rgba(17,24,39,.45);z-index:100001;display:flex;align-items:center;justify-content:center;padding:16px;}',
            '.myvh-slot-finder-dialog{width:min(640px,100%);max-height:calc(100vh - 32px);overflow:auto;background:#fff;border-radius:14px;box-shadow:0 22px 56px rgba(15,23,42,.28);}',
            '.myvh-slot-finder-head{padding:16px 18px;border-bottom:1px solid #e5e7eb;}',
            '.myvh-slot-finder-title{margin:0;font-size:1.1rem;font-weight:700;color:#111827;}',
            '.myvh-slot-finder-sub{margin:6px 0 0;color:#4b5563;font-size:.92rem;}',
            '.myvh-slot-finder-note{margin:8px 0 0;color:#6b7280;font-size:.82rem;}',
            '.myvh-slot-finder-body{padding:16px 18px;display:grid;gap:12px;}',
            '.myvh-slot-finder-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}',
            '.myvh-slot-finder-field{display:flex;flex-direction:column;gap:6px;}',
            '.myvh-slot-finder-field label{font-size:.88rem;color:#374151;font-weight:600;}',
            '.myvh-slot-finder-field input,.myvh-slot-finder-field select{height:38px;border:1px solid #d1d5db;border-radius:8px;padding:0 10px;font:inherit;color:#111827;background:#fff;}',
            '.myvh-slot-finder-checkbox{display:flex;align-items:center;gap:8px;font-weight:600;color:#1f2937;}',
            '.myvh-slot-finder-recurring{display:none;gap:12px;padding:10px;border:1px solid #e5e7eb;border-radius:10px;background:#f9fafb;}',
            '.myvh-slot-finder-actions{padding:12px 18px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:8px;}',
            '.myvh-slot-finder-btn{appearance:none;border:1px solid #d1d5db;background:#fff;color:#111827;border-radius:8px;padding:8px 14px;cursor:pointer;font:inherit;}',
            '.myvh-slot-finder-btn-primary{background:#2271b1;color:#fff;border-color:#2271b1;}',
            '@media (max-width: 680px){.myvh-slot-finder-grid{grid-template-columns:1fr;}}'
        ].join('');

        document.head.appendChild(style);
    }

    function createSlotFinderField(labelText, inputEl) {
        let wrapper = document.createElement('div');
        wrapper.className = 'myvh-slot-finder-field';

        let label = document.createElement('label');
        label.textContent = labelText;

        wrapper.appendChild(label);
        wrapper.appendChild(inputEl);
        return wrapper;
    }

    function showSlotFinderDialog(seed, rooms) {
        ensureSlotFinderDialogStyles();

        let row = seed && seed.row ? seed.row : null;
        let defaultDate = row ? (getRowDataValue(row, 'start-date') || getTodayDateString()) : getTodayDateString();
        let defaultTime = getDefaultStartTime(row);
        let defaultLength = getBookingLengthFromRow(row);
        let defaultRecurring = !!(row && row.classList.contains('myvh-recurring-child'));
        let defaultRoomId = row ? parseInt(getRowDataValue(row, 'room-id') || '0', 10) : 0;

        return new Promise(function(resolve) {
            let backdrop = document.createElement('div');
            backdrop.className = 'myvh-slot-finder-backdrop';

            let dialog = document.createElement('div');
            dialog.className = 'myvh-slot-finder-dialog';
            dialog.setAttribute('role', 'dialog');
            dialog.setAttribute('aria-modal', 'true');

            let head = document.createElement('div');
            head.className = 'myvh-slot-finder-head';
            head.innerHTML = '<h3 class="myvh-slot-finder-title">Find Booking Slot</h3><p class="myvh-slot-finder-sub">Choose your criteria and we will find the next available slot.</p><p class="myvh-slot-finder-note">Times are rounded up to the next 15-minute slot.</p>';

            let form = document.createElement('form');
            form.className = 'myvh-slot-finder-body';

            let gridTop = document.createElement('div');
            gridTop.className = 'myvh-slot-finder-grid';

            let dateInput = document.createElement('input');
            dateInput.type = 'date';
            dateInput.value = defaultDate;
            dateInput.required = true;

            let timeInput = document.createElement('input');
            timeInput.type = 'time';
            timeInput.step = '900';
            timeInput.value = defaultTime;
            timeInput.required = true;

            let lengthInput = document.createElement('input');
            lengthInput.type = 'number';
            lengthInput.min = '15';
            lengthInput.step = '15';
            lengthInput.value = String(defaultLength);
            lengthInput.required = true;

            gridTop.appendChild(createSlotFinderField('Start From Date', dateInput));
            gridTop.appendChild(createSlotFinderField('Start From Time', timeInput));

            let lengthRow = document.createElement('div');
            lengthRow.className = 'myvh-slot-finder-grid';
            lengthRow.appendChild(createSlotFinderField('Booking Length (minutes)', lengthInput));
            lengthRow.appendChild(document.createElement('div'));

            let gridScope = document.createElement('div');
            gridScope.className = 'myvh-slot-finder-grid';

            let scopeSelect = document.createElement('select');
            let scopeAll = document.createElement('option');
            scopeAll.value = 'all';
            scopeAll.textContent = 'All rooms';
            let scopeSpecific = document.createElement('option');
            scopeSpecific.value = 'specific';
            scopeSpecific.textContent = 'Specific room';
            scopeSelect.appendChild(scopeAll);
            scopeSelect.appendChild(scopeSpecific);

            let roomSelect = document.createElement('select');
            let roomPlaceholder = document.createElement('option');
            roomPlaceholder.value = '';
            roomPlaceholder.textContent = rooms.length ? 'Select a room' : 'No rooms available';
            roomSelect.appendChild(roomPlaceholder);
            rooms.forEach(function(room) {
                let option = document.createElement('option');
                option.value = String(room.id);
                option.textContent = room.name;
                roomSelect.appendChild(option);
            });

            if (defaultRoomId > 0) {
                scopeSelect.value = 'specific';
                roomSelect.value = String(defaultRoomId);
            } else {
                scopeSelect.value = 'all';
            }

            roomSelect.disabled = scopeSelect.value !== 'specific';

            scopeSelect.addEventListener('change', function() {
                roomSelect.disabled = scopeSelect.value !== 'specific';
            });

            gridScope.appendChild(createSlotFinderField('Room Scope', scopeSelect));
            gridScope.appendChild(createSlotFinderField('Room', roomSelect));

            let recurringToggleWrap = document.createElement('label');
            recurringToggleWrap.className = 'myvh-slot-finder-checkbox';
            let recurringToggle = document.createElement('input');
            recurringToggle.type = 'checkbox';
            recurringToggle.checked = defaultRecurring;
            recurringToggleWrap.appendChild(recurringToggle);
            recurringToggleWrap.appendChild(document.createTextNode('Find recurring booking slot'));

            let recurringPanel = document.createElement('div');
            recurringPanel.className = 'myvh-slot-finder-recurring';

            let recurrenceType = document.createElement('select');
            ['daily', 'weekly', 'monthly', 'yearly'].forEach(function(type) {
                let option = document.createElement('option');
                option.value = type;
                option.textContent = type.charAt(0).toUpperCase() + type.slice(1);
                recurrenceType.appendChild(option);
            });
            recurrenceType.value = 'weekly';

            let recurrenceInterval = document.createElement('input');
            recurrenceInterval.type = 'number';
            recurrenceInterval.min = '1';
            recurrenceInterval.value = '1';

            let recurrenceCount = document.createElement('input');
            recurrenceCount.type = 'number';
            recurrenceCount.min = '1';
            recurrenceCount.max = '365';
            recurrenceCount.value = '10';

            let recurringGrid = document.createElement('div');
            recurringGrid.className = 'myvh-slot-finder-grid';
            recurringGrid.appendChild(createSlotFinderField('Recurrence Type', recurrenceType));
            recurringGrid.appendChild(createSlotFinderField('Repeat Every (interval)', recurrenceInterval));

            let countWrap = createSlotFinderField('Occurrences To Fit', recurrenceCount);

            recurringPanel.appendChild(recurringGrid);
            recurringPanel.appendChild(countWrap);

            recurringPanel.style.display = recurringToggle.checked ? 'grid' : 'none';
            recurringToggle.addEventListener('change', function() {
                recurringPanel.style.display = recurringToggle.checked ? 'grid' : 'none';
            });

            form.appendChild(gridTop);
            form.appendChild(lengthRow);
            form.appendChild(gridScope);
            form.appendChild(recurringToggleWrap);
            form.appendChild(recurringPanel);

            let actions = document.createElement('div');
            actions.className = 'myvh-slot-finder-actions';

            let cancelButton = document.createElement('button');
            cancelButton.type = 'button';
            cancelButton.className = 'myvh-slot-finder-btn';
            cancelButton.textContent = 'Cancel';

            let submitButton = document.createElement('button');
            submitButton.type = 'button';
            submitButton.className = 'myvh-slot-finder-btn myvh-slot-finder-btn-primary';
            submitButton.textContent = 'Find Slot';

            actions.appendChild(cancelButton);
            actions.appendChild(submitButton);

            function closeDialog(result) {
                if (backdrop.parentNode) {
                    backdrop.parentNode.removeChild(backdrop);
                }
                resolve(result);
            }

            cancelButton.addEventListener('click', function() {
                closeDialog(null);
            });

            submitButton.addEventListener('click', function() {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                    return;
                }

                form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            });

            backdrop.addEventListener('click', function(event) {
                if (event.target === backdrop) {
                    closeDialog(null);
                }
            });

            form.addEventListener('submit', function(event) {
                event.preventDefault();

                let dateValue = String(dateInput.value || '').trim();
                if (!/^\d{4}-\d{2}-\d{2}$/.test(dateValue)) {
                    portalAlert('Please enter a valid date in YYYY-MM-DD format.');
                    return;
                }

                let scope = scopeSelect.value;
                let roomId = 0;

                if (scope === 'specific') {
                    roomId = parsePositiveInteger(roomSelect.value, 0);
                    if (roomId <= 0) {
                        portalAlert('Please select a room.');
                        return;
                    }
                }

                let request = {
                    date: dateValue,
                    start_time: String(timeInput.value || '').trim(),
                    length_minutes: parsePositiveInteger(lengthInput.value, defaultLength),
                    room_id: roomId,
                    is_recurring: recurringToggle.checked ? 1 : 0
                };

                if (request.is_recurring) {
                    request.recurrence_type = String(recurrenceType.value || 'weekly').toLowerCase();
                    request.recurrence_interval = parsePositiveInteger(recurrenceInterval.value, 1);
                    request.max_occurrences = parsePositiveInteger(recurrenceCount.value, 10);
                }

                closeDialog(request);
            });

            dialog.appendChild(head);
            dialog.appendChild(form);
            dialog.appendChild(actions);
            backdrop.appendChild(dialog);
            document.body.appendChild(backdrop);
            submitButton.focus();
        });
    }

    async function collectSlotFinderRequest(seed) {
        let rooms = await loadRoomOptions();
        return showSlotFinderDialog(seed, rooms);
    }

    async function requestSlot(request) {
        if (!window.MyvhPortalAjax || typeof window.MyvhPortalAjax.post !== 'function') {
            throw new Error('Portal booking tools are not available on this page.');
        }

        let payload = {
            room_id: String(request.room_id || 0),
            date: String(request.date || ''),
            start_time: String(request.start_time || ''),
            length_minutes: String(request.length_minutes || 60),
            is_recurring: request.is_recurring ? '1' : '0',
            recurrence_type: String(request.recurrence_type || ''),
            recurrence_interval: String(request.recurrence_interval || ''),
            max_occurrences: String(request.max_occurrences || ''),
            recurrence_end_date: String(request.recurrence_end_date || '')
        };

        let result = await window.MyvhPortalAjax.post('myvh_portal_next_booking_slot', payload, { scope: 'portal' });
        if (!result || !result.success || !result.data) {
            throw new Error((result && (result.message || result.data)) || 'Could not find an available slot.');
        }

        return result.data;
    }

    function openFoundSlotInModal(slot, request, seed) {
        let seedPrefill = (seed && seed.prefill) ? seed.prefill : {};
        let recurring = null;

        if (request.is_recurring) {
            recurring = {
                type: request.recurrence_type || 'weekly',
                interval: request.recurrence_interval || 1,
                endType: 'count',
                maxOccurrences: request.max_occurrences || 10
            };
        }

        runWhenBookingFlowReady(function() {
            let flow = window.MyvhPortalCalendarFlow;
            if (!flow || typeof flow.openCreate !== 'function') {
                portalAlert('Booking flow is not available right now.');
                return;
            }

            flow.openCreate({
                start: String(slot.start || ''),
                end: String(slot.end || ''),
                room_id: String(slot.room_id || request.room_id || ''),
                customer_id: seedPrefill.customerId || '',
                organisation_id: seedPrefill.organisationId || '',
                text: seedPrefill.description || '',
                recurring: recurring
            });
        });
    }

    function handleFindSlotActionClick(event) {
        let trigger = event.target.closest('.myvh-find-slot-trigger, [data-myvh-find-slot]');
        if (!trigger) {
            return;
        }

        event.preventDefault();

        let row = trigger.closest('tr.myvh-bookings-table-row');
        let seed = {
            row: row,
            prefill: row ? buildPrefillFromRow(row) : {}
        };

        collectSlotFinderRequest(seed)
            .then(function(request) {
                if (!request) {
                    return null;
                }

                return requestSlot(request).then(function(slot) {
                    openFoundSlotInModal(slot, request, seed);
                    return slot;
                });
            })
            .catch(function(error) {
                portalAlert(error && error.message ? error.message : 'Could not find an available slot.');
            });
    }

    function bindFindSlotActions() {
        if (findSlotHandlersBound) {
            return;
        }

        document.addEventListener('click', handleFindSlotActionClick);
        findSlotHandlersBound = true;
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
     * Check whether the recurring flat-view mode is active.
     */
    function isRecurringFlatView() {
        var flatRadio = document.querySelector('.myvh-recurring-view-mode[value="flat"]');
        return flatRadio ? flatRadio.checked : false;
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
        let flatView = isRecurringFlatView();
        let table = document.getElementById('myvh-bookings-table');

        if (table) {
            table.classList.toggle('myvh-flat-recurring-view', flatView);
        }

        groupHeaders.forEach(function(header) {
            let groupId = header.getAttribute('data-group');
            // In flat view, always hide group headers; otherwise hide based on child visibility
            header.classList.toggle('myvh-hidden-by-filter', flatView || !groups[groupId]);
        });

        // Sort rows by date in flat view; restore original order in grouped view
        if (table) {
            let tbody = table.querySelector('tbody');
            if (tbody) {
                // Snapshot original order the first time we see this tbody
                let allRows = Array.from(tbody.children);
                allRows.forEach(function(row, index) {
                    if (!row.hasAttribute('data-original-order')) {
                        row.setAttribute('data-original-order', String(index));
                    }
                });

                if (flatView) {
                    allRows.sort(function(a, b) {
                        let dateA = (a.getAttribute('data-start-date') || '') + ' ' + (a.getAttribute('data-start-time') || '');
                        let dateB = (b.getAttribute('data-start-date') || '') + ' ' + (b.getAttribute('data-start-time') || '');
                        return dateA < dateB ? -1 : dateA > dateB ? 1 : 0;
                    });
                } else {
                    allRows.sort(function(a, b) {
                        return parseInt(a.getAttribute('data-original-order') || '0', 10) -
                               parseInt(b.getAttribute('data-original-order') || '0', 10);
                    });
                }

                allRows.forEach(function(row) {
                    tbody.appendChild(row);
                });
            }
        }

        // Update the subtitle to reflect current view mode
        let subtitle = document.getElementById('myvh-bookings-subtitle');
        if (subtitle) {
            if (flatView) {
                subtitle.textContent = 'Grouped bookings are shown individually';
            } else {
                let originalSubtitle = subtitle.getAttribute('data-original');
                if (originalSubtitle) {
                    subtitle.textContent = originalSubtitle;
                }
            }
        }
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

        // Recurring view mode toggle (grouped vs flat)
        document.querySelectorAll('.myvh-recurring-view-mode').forEach(function(radio) {
            radio.addEventListener('change', applyAllFilters);
        });

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

                // Reset recurring view mode to 'grouped'
                document.querySelectorAll('.myvh-recurring-view-mode').forEach(function(radio) {
                    radio.checked = (radio.value === 'grouped');
                });

                applyAllFilters();
            });
        }
    }

    /**
     * Initialize bookings logic (accordion, actions). Only runs once.
     */
    function init() {
        bindFindSlotActions();

        // Cache the original subtitle text so it can be restored when leaving flat view
        let subtitle = document.getElementById('myvh-bookings-subtitle');
        if (subtitle && !subtitle.hasAttribute('data-original')) {
            subtitle.setAttribute('data-original', subtitle.textContent);
        }

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

    bindFindSlotActions();

    return {
        init: init
    };

})();
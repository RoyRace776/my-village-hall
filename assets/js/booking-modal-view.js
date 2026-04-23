window.BookingModalView = (function() {
    let config = {};
    let modal, form;
    let isBound = false;
    let currentBookingId = 0;
    let currentCanEdit = false;

    function portalAlert(message) {
        if (window.MyvhPortalDialog && typeof window.MyvhPortalDialog.alert === 'function') {
            return window.MyvhPortalDialog.alert(message);
        }

        window.alert(message);
        return Promise.resolve(true);
    }

    /**
     * Initialize the booking modal with configuration and bind events.
     * @param {object} userConfig - Configuration overrides and hooks
     */
    function init(userConfig) {
        config = {
            ajax_url: null,
            nonce: 'myvh_calendar',
            context: null,

            // Data providers (pluggable)
            loadRooms: null,
            loadCustomers: null,
            loadOrganisations: null,
            customerOrganisations: {},

            // Behaviour flags
            lockCustomer: false,
            lockOrganisation: false,
            hideCustomer: false,
            hideOrganisation: false,
            requireOrganisation: false,
            canManageNoInvoiceRequired: false,

            // Hooks
            onSuccess: () => {},
            onEdit: () => {},
            onOpen: () => {},
            onClose: () => {},
            beforeSubmit: () => true, // allow validation hook
            lockAddonPrices: false,

            ...userConfig
        };

        modal = document.getElementById("myvh-booking-modal-view");
        form  = document.getElementById("myvh-booking-form-view");

        if (!modal || !form) {
            return;
        }

        window.MyvhFlatpickr?.initWithin(modal);

        if (!isBound) {
            bindEvents();
            isBound = true;
        }
    }

    /**
     * Bind all modal and form events (submit, cancel, outside click, etc).
     */
    function bindEvents() {
        form.addEventListener("submit", function(e) {
            e.preventDefault();
        });

        const cancelButtons = modal.querySelectorAll(".myvh-cancel");
        cancelButtons.forEach((button) => {
            button.addEventListener("click", function (event) {
                event.preventDefault();
                close();
            });
        });

        const editButtons = modal.querySelectorAll(".myvh-edit-booking");
        editButtons.forEach((button) => {
            button.addEventListener("click", function(event) {
                event.preventDefault();

                if (!currentCanEdit || !currentBookingId) {
                    return;
                }

                const bookingId = currentBookingId;
                close();
                config.onEdit({ bookingId: bookingId });
            });
        });

        // Click outside closes modal
        modal.addEventListener("click", function(e) {
            if (e.target === modal) {
                close();
            }
        });

        bindDependentControls();
    }

    function normalizePayloadFromResponse(res) {
        if (!res || !res.success || !res.data.booking) {
            throw new Error('Booking not found');
        }

        return {
            booking: res.data.booking,
            addons: Array.isArray(res.data.addons) ? res.data.addons : [],
            canEdit: !!res.data.can_edit,
            editReason: res.data.edit_reason || '',
            canManageNoInvoiceRequired: !!res.data.can_manage_no_invoice_required
        };
    }

    function isCompletePayload(payload) {
        if (!payload || typeof payload !== 'object' || !payload.booking || typeof payload.booking !== 'object') {
            return false;
        }

        const booking = payload.booking;

        if (!Object.prototype.hasOwnProperty.call(payload, 'addons') || !Array.isArray(payload.addons)) {
            return false;
        }

        if (!Object.prototype.hasOwnProperty.call(payload, 'canEdit')) {
            return false;
        }

        if (!Object.prototype.hasOwnProperty.call(payload, 'editReason')) {
            return false;
        }

        if (!Object.prototype.hasOwnProperty.call(payload, 'canManageNoInvoiceRequired')) {
            return false;
        }

        return !!(
            booking['RoomId'] &&
            booking['RoomName'] &&
            booking['CustomerId'] &&
            booking['CustomerName'] &&
            booking['OrganisationId'] &&
            booking['OrganisationName'] &&
            booking['StartDate'] &&
            booking['StartTime'] &&
            booking['EndTime'] &&
            Object.prototype.hasOwnProperty.call(booking, 'NoInvoiceRequired')
        );
    }

    function applyPayloadToForm(payload, data) {
        const booking = payload.booking;
        config.canManageNoInvoiceRequired = !!payload.canManageNoInvoiceRequired;

        setSelectDisplayOption('room_id', booking['RoomId'], booking['RoomName'], {
            allowMultiday: booking['AllowMultiDayBookings']
        });
        setSelectDisplayOption('customer_id', booking['CustomerId'], booking['CustomerName']);
        setSelectDisplayOption('organisation_id', booking['OrganisationId'], booking['OrganisationName']);
        setValue('status', formatStatus(booking['Status']));
        setValue('description', booking['Description']);
        setValue('public', !!booking['Public']);
        setValue('no_invoice_required', !!booking['NoInvoiceRequired']);
        applyExistingAddons(payload.addons || []);
        currentCanEdit = !!payload.canEdit;
        updateEditButtons(currentCanEdit, payload.editReason);
        applyNoInvoiceRequiredVisibility();

        const startDateTime = `${booking['StartDate'] || ''} ${booking['StartTime'] || ''}`.trim();
        const endDate = booking['EndDate'] || booking['StartDate'] || '';
        const endDateTime = `${endDate} ${booking['EndTime'] || ''}`.trim();
        setDisplayedDateTimes(startDateTime, endDateTime);
        syncEndDateVisibilityFromBooking(booking['StartDate'], endDate);

        form.querySelectorAll('input, select, textarea')
            .forEach(el => el.disabled = true);
        config.onOpen(data);
    }

    /**
     * Open the modal and populate with data.
     * @param {object} data - Data to prefill the form
     */
    function open(data = {}) {
        const bookingId = data.bookingId || data.args?.id || data.id;
        if (!bookingId) {
            return;
        }

        currentBookingId = Number(bookingId) || 0;
        currentCanEdit = false;
        updateEditButtons(false, 'Loading booking permissions...');
        applyNoInvoiceRequiredVisibility();

        modal.classList.remove('hidden');
        setLoading(true);

        form.querySelectorAll('button[type="submit"]').forEach(button => {
            button.style.display = 'none';
        });

        // Pre-fill dates/times and room immediately from calendar event data
        const prefill = data.prefill || {};
        if (prefill.start || prefill.end) {
            setDisplayedDateTimes(prefill.start || '', prefill.end || '');
        }
        if (prefill.roomId && prefill.roomName) {
            setSelectDisplayOption('room_id', prefill.roomId, prefill.roomName);
        }
        if (prefill.customerId && prefill.customerName) {
            setSelectDisplayOption('customer_id', prefill.customerId, prefill.customerName);
        }
        if (prefill.organisationId && prefill.organisationName) {
            setSelectDisplayOption('organisation_id', prefill.organisationId, prefill.organisationName);
        }
            if (prefill.status) {
                setValue('status', formatStatus(prefill.status));
            }
            if (prefill.description !== undefined && prefill.description !== null) {
                setValue('description', prefill.description);
            }

        const incomingPayload = data.payload && typeof data.payload === 'object' ? data.payload : null;
        if (isCompletePayload(incomingPayload)) {
            applyPayloadToForm(incomingPayload, data);
            setLoading(false);
            return;
        }

        const nonce = config.context === 'portal' ? config.nonce : 'myvh_calendar';
        const loadAction = config.context === 'portal' ? 'myvh_portal_get_booking' : 'myvh_calendar_get_booking';

        fetch(`${config.ajax_url}?action=${encodeURIComponent(loadAction)}&booking_id=${encodeURIComponent(bookingId)}&nonce=${encodeURIComponent(nonce)}`)
            .then(r => r.json())
            .then(normalizePayloadFromResponse)
            .then((payload) => {
                applyPayloadToForm(payload, data);
            })
            .catch(err => {
                console.error(err);
                portalAlert('Failed to load booking');
                close();
            })
            .finally(() => {
                setLoading(false);
            });
    }

    // Add a close() function to hide modal and reset form
    function close() {
        modal.classList.add('hidden');
        form.reset();
        currentBookingId = 0;
        currentCanEdit = false;
        updateEditButtons(false, '');
        form.querySelectorAll("select, input, textarea").forEach(el => {
            if (el.name === 'room_id' || el.name === 'customer_id' || el.name === 'organisation_id' || el.name === 'public') {
                el.disabled = true;
                return;
            }

            if (el.name === 'no_invoice_required') {
                el.disabled = true;
                return;
            }

            if (el.id === 'myvh-modal-start-date' || el.id === 'myvh-modal-start-time' || el.id === 'myvh-modal-end-date' || el.id === 'myvh-modal-end-time') {
                el.disabled = true;
                return;
            }

            el.disabled = false;
        });
        applyExistingAddons([]);
        applyNoInvoiceRequiredVisibility();
    }

    function applyNoInvoiceRequiredVisibility() {
        const row = form.querySelector('#myvh-modal-view-no-invoice-row');
        const checkbox = form.querySelector('[name=no_invoice_required]');

        if (row) {
            row.style.display = config.canManageNoInvoiceRequired ? '' : 'none';
        }

        if (!checkbox) {
            return;
        }

        if (!config.canManageNoInvoiceRequired) {
            checkbox.checked = false;
        }

        checkbox.disabled = true;
    }

    function bindDependentControls() {
        const customer = form.querySelector("[name=customer_id]");
        const room = form.querySelector("[name=room_id]");
        const organisation = form.querySelector("[name=organisation_id]");

        if (customer) {
            customer.addEventListener("change", () => {
                refreshOrganisations("");
            });
        }

        if (organisation) {
            organisation.addEventListener("change", applyPublicDefaultFromOrganisation);
        }

        if (room) {
            room.addEventListener("change", syncEndDateVisibility);
        }
    }

    function bindRecurringControls() {
        const recurringToggle = form.querySelector("#myvh-modal-is-recurring");
        const recurringOptions = form.querySelector("#myvh-modal-recurring-options");
        const recurrenceType = form.querySelector("#myvh-modal-rec-type");

        if (recurringToggle && recurringOptions) {
            recurringToggle.addEventListener("change", () => {
                recurringOptions.style.display = recurringToggle.checked ? "block" : "none";
            });
        }

        if (recurrenceType) {
            recurrenceType.addEventListener("change", syncRecurringType);
        }

        form.querySelectorAll("input[name=recurrence_end_type]").forEach(radio => {
            radio.addEventListener("change", syncRecurrenceEndMode);
        });

        const intervalMd = form.querySelector("[name=recurrence_interval_md]");
        if (intervalMd) {
            intervalMd.addEventListener("input", () => {
                if ((recurrenceType?.value || "") === "monthly_day") {
                    const interval = form.querySelector("[name=recurrence_interval]");
                    if (interval) {
                        interval.value = intervalMd.value;
                    }
                }
            });
        }

        syncRecurringType();
        syncRecurrenceEndMode();
    }

    function syncRecurringType() {
        const recurrenceType = form.querySelector("#myvh-modal-rec-type");
        const intervalRow = form.querySelector("#myvh-modal-interval-row");
        const monthlyDayRow = form.querySelector("#myvh-modal-monthly-day-row");
        const intervalLabel = form.querySelector("#myvh-modal-interval-label");
        const interval = form.querySelector("[name=recurrence_interval]");
        const intervalMd = form.querySelector("[name=recurrence_interval_md]");

        if (!recurrenceType) return;

        const labels = {
            daily: "day(s)",
            weekly: "week(s)",
            monthly: "month(s)",
            yearly: "year(s)"
        };

        const isMonthlyDay = recurrenceType.value === "monthly_day";

        if (intervalRow) intervalRow.style.display = isMonthlyDay ? "none" : "";
        if (monthlyDayRow) monthlyDayRow.style.display = isMonthlyDay ? "" : "none";
        if (intervalLabel) intervalLabel.textContent = labels[recurrenceType.value] || "";

        if (isMonthlyDay && interval && intervalMd) {
            interval.value = intervalMd.value;
        }
    }

    function syncRecurrenceEndMode() {
        const endType = form.querySelector("input[name=recurrence_end_type]:checked")?.value || "date";
        const maxOccurrences = form.querySelector("[name=max_occurrences]");
        const recurrenceEndDate = form.querySelector("[name=recurrence_end_date]");

        if (maxOccurrences) {
            maxOccurrences.disabled = endType !== "count";
        }

        if (recurrenceEndDate) {
            recurrenceEndDate.disabled = endType !== "date";
        }
    }

    function bindAddonControls() {
        form.querySelectorAll(".myvh-modal-addon-checkbox").forEach(checkbox => {
            checkbox.addEventListener("change", () => {
                const row = checkbox.closest(".myvh-modal-addon-row");
                if (!row) return;
                toggleAddonRow(row, checkbox.checked);
            });
        });
    }

    function isAddonPriceLocked() {
        return !!config.lockAddonPrices || config.context === "portal";
    }

    function toggleAddonRow(row, enabled) {
        const enabledField = row.querySelector(".myvh-modal-addon-enabled");
        if (enabledField) {
            enabledField.value = enabled ? "1" : "0";
        }

        row.querySelectorAll(".myvh-modal-addon-price").forEach(input => {
            if (isAddonPriceLocked()) {
                input.disabled = !enabled;
                input.readOnly = true;
            } else {
                input.disabled = !enabled;
                input.readOnly = false;
            }
        });

        row.querySelectorAll(".myvh-modal-addon-qty").forEach(input => {
            input.disabled = !enabled;
        });

        const checkbox = row.querySelector(".myvh-modal-addon-checkbox");
        if (checkbox) {
            checkbox.checked = enabled;
        }
    }

    function applyExistingAddons(addons) {
        const addonMap = {};
        let selectedCount = 0;

        (Array.isArray(addons) ? addons : []).forEach(addon => {
            const addonId = String(addon.AddonId || addon.addon_id || '');
            if (addonId) {
                addonMap[addonId] = addon;
            }
        });

        form.querySelectorAll('.myvh-modal-addon-row').forEach(row => {
            const addonIdField = row.querySelector('input[name$="[addon_id]"]');
            const priceField = row.querySelector('.myvh-modal-addon-price');
            const quantityField = row.querySelector('.myvh-modal-addon-qty');
            const addonId = addonIdField ? String(addonIdField.value || '') : '';
            const addon = addonId ? addonMap[addonId] : null;

            if (addon && priceField) {
                const resolvedPrice = addon.UnitPrice ?? addon.unit_price ?? priceField.value ?? 0;
                priceField.value = Number(resolvedPrice).toFixed(2);
            }

            if (addon && quantityField) {
                quantityField.value = String(addon.Quantity ?? addon.quantity ?? 1);
            } else if (quantityField) {
                quantityField.value = '1';
            }

            const isSelected = !!addon;
            toggleAddonRow(row, isSelected);
            row.style.display = isSelected ? '' : 'none';

            if (isSelected) {
                selectedCount += 1;
            }
        });

        updateAddonEmptyState(selectedCount);
    }

    function updateAddonEmptyState(selectedCount) {
        const table = form.querySelector('#myvh-modal-addons-table');
        const empty = form.querySelector('#myvh-modal-addons-empty');

        if (table) {
            table.style.display = selectedCount > 0 ? '' : 'none';
        }

        if (empty) {
            empty.style.display = selectedCount > 0 ? 'none' : '';
        }
    }

    // ─────────────────────────────
    // Context + UI behaviour
    // ─────────────────────────────
    function applyContext(data) {

        if (config.lockCustomer && data.customer_id) {
            setAndLock("customer_id", data.customer_id);
        }

        if (config.lockOrganisation && data.organisation_id) {
            setAndLock("organisation_id", data.organisation_id);
        }
    }

    function applyVisibility() {

        toggleField("customer_id", !config.hideCustomer);
        toggleField("organisation_id", !config.hideOrganisation);
    }

    function toggleField(field, show) {

        const el = form.querySelector(`[name=${field}]`);
        if (!el) return;

        const row = el.closest("tr");
        if (row) {
            row.style.display = show ? "" : "none";
        }
    }

    function applyPublicDefaultFromOrganisation() {
        const publicCheckbox = form.querySelector("[name=public]");
        const organisationSelect = form.querySelector("[name=organisation_id]");

        if (!publicCheckbox || !organisationSelect) {
            return;
        }

        const selected = organisationSelect.options[organisationSelect.selectedIndex];
        const defaultPublic = selected ? String(selected.dataset.defaultPublic || "0") === "1" : false;
        publicCheckbox.checked = defaultPublic;
    }

    function setDisplayedDateTimes(start, end) {
        const s = parseDateTime(start);
        const e = parseDateTime(end);

        const startDate = form.querySelector("#myvh-modal-start-date");
        const endDate = form.querySelector("#myvh-modal-end-date");
        const startTime = form.querySelector("#myvh-modal-start-time");
        const endTime = form.querySelector("#myvh-modal-end-time");

        if (startDate) window.MyvhFlatpickr ? window.MyvhFlatpickr.setValue(startDate, s.date) : (startDate.value = s.date);
        if (endDate) window.MyvhFlatpickr ? window.MyvhFlatpickr.setValue(endDate, e.date) : (endDate.value = e.date);
        if (startTime) window.MyvhFlatpickr ? window.MyvhFlatpickr.setValue(startTime, s.time) : (startTime.value = s.time);
        if (endTime) window.MyvhFlatpickr ? window.MyvhFlatpickr.setValue(endTime, e.time) : (endTime.value = e.time);
    }

    function parseDateTime(value) {
        const raw = String(value || "").trim();
        if (!raw) return { date: "", time: "" };

        const normalized = raw.replace(" ", "T");
        const parts = normalized.split("T");
        const date = parts[0] || "";
        const time = (parts[1] || "").slice(0, 5);

        return { date, time };
    }

    function formatStatus(value) {
        const raw = String(value || '').trim();
        if (!raw) {
            return '';
        }

        return raw.charAt(0).toUpperCase() + raw.slice(1).toLowerCase();
    }

    function roomAllowsMultiday() {
        const roomSelect = form.querySelector("[name=room_id]");
        if (!roomSelect) return false;

        const selected = roomSelect.options[roomSelect.selectedIndex];
        if (!selected) return false;

        return String(selected.dataset.allowMultiday || "0") === "1";
    }

    function syncEndDateVisibility() {
        const row = form.querySelector("#myvh-modal-end-date-row");
        if (!row) return;

        row.style.display = roomAllowsMultiday() ? "" : "none";
    }

    function syncEndDateVisibilityFromBooking(startDate, endDate) {
        const row = form.querySelector("#myvh-modal-end-date-row");
        if (!row) return;

        row.style.display = (startDate && endDate && startDate !== endDate) ? "" : "none";
    }

    function setAndLock(field, value) {
        const el = form.querySelector(`[name=${field}]`);
        if (!el) return;

        el.value = value;
        el.disabled = true;
        el.dataset.locked = "true"; // 👈 mark as intentionally locked
    }

    function setValue(field, value) {
        const el = form.querySelector(`[name=${field}]`);
        if (el && value !== undefined) {
            if (el.type === 'checkbox') {
                el.checked = !!value && String(value) !== '0';
            } else {
                el.value = value;
            }
        }
    }

    function setSelectDisplayOption(field, value, label, options = {}) {
        const el = form.querySelector(`[name=${field}]`);
        if (!el) return;

        const text = String(label || value || '-');
        el.innerHTML = '';

        const opt = document.createElement('option');
        opt.value = value || '';
        opt.text = text;

        if (options.allowMultiday !== undefined) {
            opt.dataset.allowMultiday = String(options.allowMultiday ? 1 : 0);
        }

        el.appendChild(opt);
        el.value = value || '';
    }

    function updateEditButtons(canEdit, reason = '') {
        modal.querySelectorAll('.myvh-edit-booking').forEach((button) => {
            button.style.display = canEdit ? '' : 'none';
            button.disabled = !canEdit;
            button.title = canEdit ? '' : (reason || 'Editing not available');
        });
    }

    // ─────────────────────────────
    // Dropdowns
    // ─────────────────────────────
    function populateDropdown(select, loader) {

        if (!select || !loader) return Promise.resolve();

        select.innerHTML = '<option value="">Loading...</option>';

        return loader().then(data => {

            select.innerHTML = '<option value="">Select...</option>';

            data.forEach(item => {
                const opt = document.createElement("option");
                opt.value = item.id || item.Id;
                opt.text  = item.name || item.Name;
                const allowMultiday = item.allow_multiday ?? item.AllowMultiDayBookings;
                if (allowMultiday !== undefined) {
                    opt.dataset.allowMultiday = String(allowMultiday);
                }
                select.appendChild(opt);
            });
        });
    }

    function populateDropdowns() {

        return Promise.all([
            populateDropdown(form.querySelector("[name=room_id]"), config.loadRooms),
            populateDropdown(form.querySelector("[name=customer_id]"), config.loadCustomers)
        ]);
    }

    function refreshOrganisations(preferredOrgId) {
        const customerSelect = form.querySelector("[name=customer_id]");
        const organisationSelect = form.querySelector("[name=organisation_id]");
        const lockOrganisation = config.lockOrganisation || organisationSelect?.dataset.locked === "true";
        const requireOrganisation = config.requireOrganisation || lockOrganisation;

        if (!organisationSelect) {
            return Promise.resolve();
        }

        const customerId = customerSelect ? customerSelect.value : "";

        if (!customerId) {
            organisationSelect.innerHTML = requireOrganisation
                ? '<option value="">No organisations available</option>'
                : '<option value="">Select...</option>';
            organisationSelect.disabled = true;
            return Promise.resolve();
        }

        organisationSelect.disabled = true;
        organisationSelect.innerHTML = '<option value="">Loading...</option>';

        return Promise.resolve(config.loadOrganisations ? config.loadOrganisations(customerId) : [])
            .then(data => {
                const organisations = Array.isArray(data) ? data : [];
                organisationSelect.innerHTML = requireOrganisation
                    ? ''
                    : '<option value="">Select...</option>';

                organisations.forEach(item => {
                    const opt = document.createElement("option");
                    opt.value = item.id || item.Id;
                    opt.text = item.name || item.Name;
                    const defaultPublic = item.default_public ?? item.DefaultPublic;
                    if (defaultPublic !== undefined) {
                        opt.dataset.defaultPublic = String(defaultPublic);
                    }
                    organisationSelect.appendChild(opt);
                });

                if (preferredOrgId && organisations.some(item => String(item.id || item.Id) === String(preferredOrgId))) {
                    organisationSelect.value = String(preferredOrgId);
                } else if (organisations.length >= 1 && requireOrganisation) {
                    organisationSelect.value = String(organisations[0].id || organisations[0].Id);
                } else if (organisations.length === 1) {
                    organisationSelect.value = String(organisations[0].id || organisations[0].Id);
                } else {
                    organisationSelect.value = "";
                }

                organisationSelect.disabled = lockOrganisation || organisations.length === 0;
                applyPublicDefaultFromOrganisation();
            })
            .catch(() => {
                organisationSelect.innerHTML = requireOrganisation
                    ? '<option value="">No organisations available</option>'
                    : '<option value="">Select...</option>';
                organisationSelect.disabled = true;
                applyPublicDefaultFromOrganisation();
            });
    }

    // ─────────────────────────────
    // UX helpers
    // ─────────────────────────────
    function setLoading(state) {

        const btn = form.querySelector("button[type=submit]");

        if (btn) {
            btn.disabled = state;
            btn.textContent = state ? "Loading..." : "View Booking";
        }

        if (state) {
            form.querySelectorAll("input, select").forEach(el => {
                el.disabled = state;
            });
        }
    }

    // ─────────────────────────────
    // Public API
    // ─────────────────────────────
    return {
        init,
        open,
        close,
    };

})();
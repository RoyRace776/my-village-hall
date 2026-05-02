window.BookingModalCreate = (function() {
    let config = {};
    let modal, form;
    let isBound = false;
    let roomsCache = null;
    let customersCache = null;
    const organisationsCache = {};

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
            onOpen: () => {},
            onClose: () => {},
            onDelete: () => {},
            beforeSubmit: () => true, // allow validation hook
            lockAddonPrices: false,
            editMode: false,
            editBookingId: 0,

            ...userConfig
        };

        modal = document.getElementById("myvh-booking-modal-create");
        form  = document.getElementById("myvh-booking-form-create");

        if (!modal || !form) {
            return;
        }

        if (!isBound) {
            bindEvents();
            isBound = true;
        }

        initializePickers();

        applyNoInvoiceRequiredVisibility();
    }

    /**
     * Bind all modal and form events (submit, cancel, outside click, etc).
     */
    function bindEvents() {
        form.addEventListener("submit", submit);

        const cancelButtons = modal.querySelectorAll(".myvh-cancel");
        cancelButtons.forEach((button) => {
            button.addEventListener("click", function (event) {
                event.preventDefault();
                close();
            });
        });

        const deleteButtons = modal.querySelectorAll('.myvh-delete-booking');
        deleteButtons.forEach((button) => {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                if (!button.disabled && config.editBookingId) {
                    config.onDelete({ bookingId: config.editBookingId });
                }
            });
        });

        // Click outside closes modal
        modal.addEventListener("click", function(e) {
            if (e.target === modal) {
                close();
            }
        });

        bindRecurringControls();
        bindAddonControls();
        bindDependentControls();

        [
            "#myvh-modal-start-date",
            "#myvh-modal-end-date",
            "#myvh-modal-start-time",
            "#myvh-modal-end-time"
        ].forEach((selector) => {
            const input = form.querySelector(selector);
            if (!input) {
                return;
            }

            input.addEventListener("change", syncHiddenDateTimes);
            input.addEventListener("input", syncHiddenDateTimes);
        });
    }

    function initializePickers() {
        if (!modal || !window.MyvhFlatpickr) {
            return;
        }

        window.MyvhFlatpickr.initWithin(modal);
        syncPickerValues();
    }

    function resetEditState() {
        config.editMode = false;
        config.editBookingId = 0;
        setValue('booking_id', '');
        resetRecurringEditScope();
        updateModalMode(false);
    }

    /**
     * Open the modal and populate with data.
     * @param {object} data - Data to prefill the form
     */
    function open(data = {}) {
        // Always define submitBtn at the top so it's available in both modes
        const submitBtn = form.querySelector('button[type="submit"]');
        const editBookingId = data.bookingId || data.args?.id || 0;

        // If viewOnly and id provided, fetch booking details and populate, then disable all fields
        if (data.viewOnly && data.args?.id) {

            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.style.display = 'none';

            let closeBtn = form.querySelector('.myvh-modal-close');
            if (!closeBtn) {
                closeBtn = document.createElement('button');
                closeBtn.type = 'button';
                closeBtn.className = 'myvh-modal-close myvh-button';
                closeBtn.textContent = 'Close';
                closeBtn.addEventListener('click', close);
                form.appendChild(closeBtn);
            } else {
                closeBtn.style.display = '';
            }

            const nonce = config.context === "portal" ? config.nonce : "myvh_calendar";
            const loadAction = config.context === 'portal' ? 'myvh_portal_get_booking' : 'myvh_calendar_get_booking';

            setLoading(true);

            fetch(`${config.ajax_url}?action=${encodeURIComponent(loadAction)}&booking_id=${encodeURIComponent(data.args.id)}&nonce=${encodeURIComponent(nonce)}`)
                .then(r => r.json())
                .then(res => {
                    if (!res || !res.success || !res.data.booking) {
                        throw new Error('Booking not found');
                    }

                    const booking = res.data.booking;
                    booking.__addons = Array.isArray(res.data.addons) ? res.data.addons : [];

                    // 🔑 IMPORTANT: load dropdowns first
                    return populateDropdowns()
                        .then(() => refreshOrganisations(booking['OrganisationId']))
                        .then(() => booking);
                })
                .then((booking) => {
                    // Set values first, then disable fields to keep controlled inputs stable.
                    setValue('room_id', booking['RoomId']);
                    setValue('customer_id', booking['CustomerId']);
                    setValue('organisation_id', booking['OrganisationId']);
                    setValue('description', booking['Description']);

                    setDisplayedDateTimes(booking['StartDate'], booking['EndDate']);
                    applyExistingAddons(booking.__addons || []);
                    setRecurringEditScopeVisibility(!!booking['RecurringPatternId']);

                    form.querySelectorAll('input, select, textarea')
                        .forEach(el => el.disabled = true);

                    syncPickerValues();

                    const recurringOptions = form.querySelector('#myvh-modal-recurring-options');
                    if (recurringOptions) recurringOptions.style.display = 'none';

                    modal.classList.remove('hidden');
                    config.onOpen(data);
                })
                .catch(err => {
                    console.error(err);
                    portalAlert('Failed to load booking');
                })
                .finally(() => {
                    setLoading(false);
                });

            return;
        }

        if (data.editMode && editBookingId) {
            resetEditState();
            config.editMode = true;
            config.editBookingId = Number(editBookingId) || 0;
            setValue('booking_id', config.editBookingId);
            updateModalMode(true);

            form.reset();
            setValue('booking_id', config.editBookingId);
            modal.classList.remove('hidden');

            const prefill = data.prefill || {};
            if (prefill.start || prefill.end) {
                setDisplayedDateTimes(prefill.start || '', prefill.end || '');
                setValue('start', prefill.start || '');
                setValue('end', prefill.end || '');
            }
            if (prefill.roomId && prefill.roomName) {
                setSelectDisplayOption('room_id', prefill.roomId, prefill.roomName);
                syncEndDateVisibility();
            }
            if (prefill.customerId && prefill.customerName) {
                setSelectDisplayOption('customer_id', prefill.customerId, prefill.customerName);
            }
            if (prefill.organisationId && prefill.organisationName) {
                setSelectDisplayOption('organisation_id', prefill.organisationId, prefill.organisationName);
            }
            if (prefill.description !== undefined && prefill.description !== null) {
                setValue('text', prefill.description);
            }
            if (prefill.status) {
                setValue('status', String(prefill.status).toLowerCase());
            }

            setLoading(true);

            const loadAction = config.context === 'portal' ? 'myvh_portal_get_booking' : 'myvh_calendar_get_booking';
            fetch(`${config.ajax_url}?action=${encodeURIComponent(loadAction)}&booking_id=${encodeURIComponent(config.editBookingId)}&nonce=${encodeURIComponent(config.nonce)}`)
                .then(r => r.json())
                .then(res => {
                    if (!res || !res.success || !res.data.booking) {
                        throw new Error('Booking not found');
                    }

                    const booking = res.data.booking;
                    booking.__addons = Array.isArray(res.data.addons) ? res.data.addons : [];
                    booking.__canDelete = !!res.data.can_delete;
                    booking.__deleteReason = res.data.delete_reason || '';

                    return populateDropdowns()
                        .then(() => {
                            setValue('customer_id', booking['CustomerId']);
                            return refreshOrganisations(booking['OrganisationId']);
                        })
                        .then(() => booking);
                })
                .then((booking) => {
                    const startDateTime = `${booking['StartDate'] || ''} ${booking['StartTime'] || ''}`.trim();
                    const endDate = booking['EndDate'] || booking['StartDate'] || '';
                    const endDateTime = `${endDate} ${booking['EndTime'] || ''}`.trim();

                    setValue('room_id', booking['RoomId']);
                    setValue('customer_id', booking['CustomerId']);
                    setValue('organisation_id', booking['OrganisationId']);
                    setValue('text', booking['Description'] || '');
                    setValue('status', String(booking['Status'] || 'pending').toLowerCase());
                    setValue('start', startDateTime);
                    setValue('end', endDateTime);
                    setDisplayedDateTimes(startDateTime, endDateTime);
                    applyExistingAddons(booking.__addons || []);
                    setRecurringEditScopeVisibility(!!booking['RecurringPatternId']);

                    const publicCheckbox = form.querySelector("[name=public]");
                    if (publicCheckbox) {
                        publicCheckbox.checked = !!booking['Public'];
                    }

                    setValue('no_invoice_required', !!booking['NoInvoiceRequired']);

                    form.querySelectorAll("select, input, textarea").forEach(el => {
                        if (!el.dataset.locked) {
                            el.disabled = false;
                        }
                    });

                    syncPickerValues();

                    applyNoInvoiceRequiredVisibility();

                    const recurringOptions = form.querySelector("#myvh-modal-recurring-options");
                    if (recurringOptions) {
                        recurringOptions.style.display = "none";
                    }

                    const maxOccurrences = form.querySelector("[name=max_occurrences]");
                    const recurrenceEndDate = form.querySelector("[name=recurrence_end_date]");

                    if (maxOccurrences) {
                        maxOccurrences.disabled = true;
                    }

                    if (recurrenceEndDate) {
                        recurrenceEndDate.disabled = false;
                    }

                    syncRecurringType();
                    syncEndDateVisibility();
                    syncPickerValues();
                    syncHiddenDateTimes();

                    modal.querySelectorAll('.myvh-delete-booking').forEach((button) => {
                        button.disabled = !booking.__canDelete;
                        button.title = booking.__canDelete ? '' : (booking.__deleteReason || 'Deleting not available');
                    });

                    config.onOpen(data);
                })
                .catch(err => {
                    console.error(err);
                    portalAlert('Failed to load booking');
                    close();
                })
                .finally(() => {
                    setLoading(false);
                });

            return;
        }

        // Only normal (add/edit) mode remains
        resetEditState();
        if (submitBtn) submitBtn.style.display = '';
        form.reset();

        applyVisibility();

        // Populate dropdowns (rooms, customers), then organisations
        populateDropdowns()
            .then(() => {
                if (data.customer_id) {
                    setValue('customer_id', data.customer_id);
                }

                return refreshOrganisations(data.organisation_id || '');
            })
            .then(() => {
                applyContext(data);
            });

        // Prepopulate start/end and displayed date/times if provided
        if (data.start) setValue('start', data.start);
        if (data.end) setValue('end', data.end);
        if (data.start || data.end) setDisplayedDateTimes(data.start, data.end);

        // Re-enable all fields
        form.querySelectorAll("select, input").forEach(el => {
            el.disabled = false;
        });

        const recurringOptions = form.querySelector("#myvh-modal-recurring-options");
        if (recurringOptions) {
            recurringOptions.style.display = "none";
        }

        resetRecurringEditScope();

        const maxOccurrences = form.querySelector("[name=max_occurrences]");
        const recurrenceEndDate = form.querySelector("[name=recurrence_end_date]");

        if (maxOccurrences) {
            maxOccurrences.disabled = true;
        }

        if (recurrenceEndDate) {
            recurrenceEndDate.disabled = false;
        }

        syncRecurringType();

        form.querySelectorAll(".myvh-modal-addon-row").forEach(row => {
            toggleAddonRow(row, false);
        });

        const orgSelect = form.querySelector("[name=organisation_id]");
        if (orgSelect) {
            orgSelect.innerHTML = '<option value="">Select...</option>';
            orgSelect.disabled = true;
        }

        const publicCheckbox = form.querySelector("[name=public]");
        if (publicCheckbox) {
            publicCheckbox.checked = false;
        }

        setValue('no_invoice_required', false);
        applyNoInvoiceRequiredVisibility();

        syncEndDateVisibility();
        syncPickerValues();
        syncHiddenDateTimes();

        // Show modal in add/edit mode
        modal.classList.remove('hidden');
    }

    // Add a close() function to hide modal and reset form
    function close() {
        modal.classList.add('hidden');
        form.reset();
        resetEditState();
        // Optionally, re-enable all fields
        form.querySelectorAll("select, input, textarea").forEach(el => {
            el.disabled = false;
        });
        applyNoInvoiceRequiredVisibility();
        syncEndDateVisibility();
        syncPickerValues();
        syncHiddenDateTimes();
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
            room.addEventListener("change", () => {
                syncEndDateVisibility();
                syncHiddenDateTimes();
            });
        }

        const startDate = form.querySelector("#myvh-modal-start-date");
        if (startDate) {
            startDate.addEventListener("change", () => {
                syncEndDateVisibility();
                syncHiddenDateTimes();
            });
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
            window.MyvhFlatpickr?.syncState(recurrenceEndDate);
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

            toggleAddonRow(row, !!addon);
        });
    }

    function resetRecurringEditScope() {
        const scopeRow = form.querySelector('#myvh-modal-edit-scope-row');
        form.querySelectorAll('input[name=edit_scope]').forEach((radio, index) => {
            radio.checked = index === 0;
        });

        if (scopeRow) {
            scopeRow.style.display = 'none';
        }
    }

    function setRecurringEditScopeVisibility(show) {
        const scopeRow = form.querySelector('#myvh-modal-edit-scope-row');
        if (!scopeRow) {
            return;
        }

        scopeRow.style.display = show && config.editMode ? '' : 'none';

        if (!show || !config.editMode) {
            form.querySelectorAll('input[name=edit_scope]').forEach((radio, index) => {
                radio.checked = index === 0;
            });
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

    function applyNoInvoiceRequiredVisibility() {
        const row = form.querySelector('#myvh-modal-no-invoice-row');
        const checkbox = form.querySelector('[name=no_invoice_required]');
        const canManage = !!config.canManageNoInvoiceRequired;
        const isLoading = modal?.dataset.loading === '1';

        if (row) {
            row.style.display = canManage ? '' : 'none';
        }

        if (!checkbox) {
            return;
        }

        if (!canManage) {
            checkbox.checked = false;
        }

        checkbox.disabled = !canManage || isLoading;
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

        if (startDate) startDate.value = s.date;
        if (endDate) endDate.value = e.date;
        if (startTime) startTime.value = s.time;
        if (endTime) endTime.value = e.time;

        syncPickerValues();
        syncHiddenDateTimes();
    }

    function syncPickerValues() {
        if (!window.MyvhFlatpickr || !form) {
            return;
        }

        [
            form.querySelector("#myvh-modal-start-date"),
            form.querySelector("#myvh-modal-end-date"),
            form.querySelector("#myvh-modal-start-time"),
            form.querySelector("#myvh-modal-end-time"),
            form.querySelector("[name=recurrence_end_date]")
        ].forEach((input) => {
            if (!input) {
                return;
            }

            window.MyvhFlatpickr.setValue(input, input.value || "");
            window.MyvhFlatpickr.syncState(input);
        });
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

        const allowMultiday = roomAllowsMultiday();
        const startDate = form.querySelector("#myvh-modal-start-date");
        const endDate = form.querySelector("#myvh-modal-end-date");

        row.style.display = allowMultiday ? "" : "none";

        if (endDate) {
            endDate.disabled = !allowMultiday;

            if (!allowMultiday && startDate) {
                endDate.value = startDate.value || "";
            }

            window.MyvhFlatpickr?.setValue(endDate, endDate.value || "");
            window.MyvhFlatpickr?.syncState(endDate);
        }
    }

    function syncHiddenDateTimes() {
        const startDate = form.querySelector("#myvh-modal-start-date")?.value || "";
        const endDateInput = form.querySelector("#myvh-modal-end-date");
        const startTime = form.querySelector("#myvh-modal-start-time")?.value || "";
        const endTime = form.querySelector("#myvh-modal-end-time")?.value || "";
        const allowMultiday = roomAllowsMultiday();
        const endDate = allowMultiday ? (endDateInput?.value || startDate) : startDate;

        setValue("start", composeDateTime(startDate, startTime));
        setValue("end", composeDateTime(endDate, endTime));
    }

    function composeDateTime(date, time) {
        if (!date || !time) {
            return "";
        }

        return `${date} ${time}`;
    }

    function setAndLock(field, value) {
        const el = form.querySelector(`[name=${field}]`);
        if (!el) return;

        el.value = value;
        el.disabled = true;
        el.dataset.locked = "true"; // 👈 mark as intentionally locked
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

    function setValue(field, value) {
        const el = form.querySelector(`[name=${field}]`);
        if (el && value !== undefined) {
            if (el.type === 'checkbox') {
                el.checked = !!value && String(value) !== '0';
                return;
            }

            el.value = value;
        }
    }

    function updateModalMode(isEdit) {
        const title = modal.querySelector('h2');
        const hint = modal.querySelector('.myvh-account-hint');
        const submitButtons = modal.querySelectorAll('button[type="submit"]');
        const statusRow = form.querySelector('#myvh-modal-status-row');

        if (title) {
            title.textContent = isEdit ? 'Edit Booking' : 'Create Booking';
        }

        if (hint) {
            hint.textContent = isEdit
                ? 'Update the booking details below.'
                : 'Complete the details below to create a booking.';
        }

        submitButtons.forEach((button) => {
            button.style.display = '';
            button.textContent = isEdit ? 'Update Booking' : 'Create Booking';
        });

        modal.querySelectorAll('.myvh-delete-booking').forEach((button) => {
            button.style.display = isEdit ? '' : 'none';
            button.disabled = true;
        });

        if (statusRow) {
            statusRow.style.display = isEdit ? '' : 'none';
        }
    }

    // ─────────────────────────────
    // Dropdowns
    // ─────────────────────────────
    function populateDropdown(select, loader, cacheKey = '') {

        if (!select || !loader) return Promise.resolve();

        if (cacheKey === 'rooms' && Array.isArray(roomsCache)) {
            renderOptions(select, roomsCache);
            return Promise.resolve();
        }

        if (cacheKey === 'customers' && Array.isArray(customersCache)) {
            renderOptions(select, customersCache);
            return Promise.resolve();
        }

        select.innerHTML = '<option value="">Loading...</option>';

        return loader().then(data => {
            const items = Array.isArray(data) ? data : [];

            if (cacheKey === 'rooms') {
                roomsCache = items;
            }

            if (cacheKey === 'customers') {
                customersCache = items;
            }

            renderOptions(select, items);
        });
    }

    function renderOptions(select, data) {
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
    }

    function populateDropdowns() {

        return Promise.all([
            populateDropdown(form.querySelector("[name=room_id]"), config.loadRooms, 'rooms'),
            populateDropdown(form.querySelector("[name=customer_id]"), config.loadCustomers, 'customers')
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

        if (organisationsCache[customerId]) {
            return Promise.resolve().then(() => {
                renderOrganisationOptions(organisationSelect, organisationsCache[customerId], requireOrganisation, preferredOrgId, lockOrganisation);
            });
        }

        return Promise.resolve(config.loadOrganisations ? config.loadOrganisations(customerId) : [])
            .then(data => {
                const organisations = Array.isArray(data) ? data : [];
                organisationsCache[customerId] = organisations;
                renderOrganisationOptions(organisationSelect, organisations, requireOrganisation, preferredOrgId, lockOrganisation);
            })
            .catch(() => {
                organisationSelect.innerHTML = requireOrganisation
                    ? '<option value="">No organisations available</option>'
                    : '<option value="">Select...</option>';
                organisationSelect.disabled = true;
                applyPublicDefaultFromOrganisation();
            });
    }

    function renderOrganisationOptions(organisationSelect, organisations, requireOrganisation, preferredOrgId, lockOrganisation) {
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
    }

    function resolveErrorMessage(payload, fallbackMessage) {
        if (typeof payload === "string" && payload.trim() !== "") {
            return payload;
        }

        if (!payload || typeof payload !== "object") {
            return fallbackMessage;
        }

        if (typeof payload.message === "string" && payload.message.trim() !== "") {
            return payload.message;
        }

        if (Array.isArray(payload) && payload.length > 0) {
            const first = payload.find(item => typeof item === "string" && item.trim() !== "");
            if (first) {
                return first;
            }
        }

        if (payload.errors && typeof payload.errors === "object") {
            for (const key in payload.errors) {
                if (!Object.prototype.hasOwnProperty.call(payload.errors, key)) {
                    continue;
                }

                const value = payload.errors[key];
                if (Array.isArray(value) && value.length > 0 && typeof value[0] === "string" && value[0].trim() !== "") {
                    return value[0];
                }

                if (typeof value === "string" && value.trim() !== "") {
                    return value;
                }
            }
        }

        return fallbackMessage;
    }

    // ─────────────────────────────
    // Submit
    // ─────────────────────────────
    function submit(e) {

        e.preventDefault();

        if (modal && modal.dataset.loading === '1') {
            return;
        }

        if (!config.beforeSubmit(form)) {
            return;
        }

        const formData = buildSubmitFormData();
        if (!formData) {
            return;
        }

        setLoading(true);
        const action = config.context === 'portal'
            ? (config.editMode ? 'myvh_portal_update_booking_modal' : 'myvh_portal_create_booking')
            : (config.editMode ? 'myvh_update_event' : 'myvh_create_event');
        formData.append("action", action);
        formData.append("nonce", config.nonce);
        if (config.context && config.context !== 'portal') {
            formData.append("context", config.context);
        }

        fetch(config.ajax_url, {
            method: "POST",
            body: formData
        })
        .then(r => r.json())
        .then(res => {

            if (!res.success) {
                portalAlert(resolveErrorMessage(res.data, "Failed to save booking"));
                return;
            }

            close();
            config.onSuccess(res.data);
        })
        .catch(err => {
            console.error(err);
            portalAlert("Unexpected error saving booking");
        })
        .finally(() => {
            setLoading(false);
        });
    }

    function buildSubmitFormData() {

        syncHiddenDateTimes();

        const formData = new FormData(form);
        const publicCheckbox = form.querySelector("[name=public]");
        const noInvoiceCheckbox = form.querySelector("[name=no_invoice_required]");

        // Always send explicit visibility for modal creates, even when unchecked.
        if (publicCheckbox) {
            formData.set("public", publicCheckbox.checked ? "1" : "0");
        }

        if (noInvoiceCheckbox && config.canManageNoInvoiceRequired) {
            formData.set("no_invoice_required", noInvoiceCheckbox.checked ? "1" : "0");
        }

        // Disabled controls are excluded from FormData, but locked fields are intentional selections.
        form.querySelectorAll("[name][data-locked=true]").forEach(el => {
            if (!formData.has(el.name)) {
                formData.append(el.name, el.value);
            }
        });

        // Portal create/edit can hide or lock selectors; keep IDs explicit to avoid backend 400 validation.
        if (config.context === "portal") {
            const customerId = String(formData.get("customer_id") || "").trim();
            const organisationId = String(formData.get("organisation_id") || "").trim();
            const defaultCustomerId = String((window.myvhCal && window.myvhCal.currentCustomerId) || "").trim();
            const defaultOrganisationId = String((window.myvhCal && window.myvhCal.defaultOrganisationId) || "").trim();

            if (!customerId && defaultCustomerId) {
                formData.set("customer_id", defaultCustomerId);
            }

            if (!organisationId && defaultOrganisationId) {
                formData.set("organisation_id", defaultOrganisationId);
            }

            if (!String(formData.get("customer_id") || "").trim()) {
                portalAlert("Please select a customer before saving this booking.");
                return null;
            }

            if (!String(formData.get("organisation_id") || "").trim()) {
                portalAlert("Please select an organisation before saving this booking.");
                return null;
            }
        }

        return formData;
    }

    // ─────────────────────────────
    // UX helpers
    // ─────────────────────────────
    function setLoading(state) {

        if (modal) {
            modal.dataset.loading = state ? '1' : '0';
        }

        form.querySelectorAll("button[type=submit]").forEach(btn => {
            btn.disabled = state;
            btn.textContent = state ? "Saving..." : (config.editMode ? "Update Booking" : "Create Booking");
        });

        form.querySelectorAll("input, select").forEach(el => {
            // Only toggle fields that aren't intentionally locked
            if (!el.dataset.locked) {
                el.disabled = state;
            }
        });

        applyNoInvoiceRequiredVisibility();
    }

    // ─────────────────────────────
    // Public API
    // ─────────────────────────────
    return {
        init,
        open,
        close
    };

})();
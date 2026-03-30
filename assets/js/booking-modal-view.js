window.BookingModalView = (function() {

    // TODO take out references to view only mode
    let config = {};
    let modal, form;

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

            // Hooks
            onSuccess: () => {},
            onOpen: () => {},
            onClose: () => {},
            beforeSubmit: () => true, // allow validation hook
            lockAddonPrices: false,

            ...userConfig
        };

        modal = document.getElementById("myvh-booking-modal-view");
        form  = document.getElementById("myvh-booking-form-view");

        if (!modal || !form) {
            console.error("BookingModalView: modal or form not found");
            return;
        }

        bindEvents();
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

        // Click outside closes modal
        modal.addEventListener("click", function(e) {
            if (e.target === modal) {
                close();
            }
        });

        bindRecurringControls();
        bindAddonControls();
        bindDependentControls();
    }

    /**
     * Open the modal and populate with data.
     * @param {object} data - Data to prefill the form
     */
    function open(data = {}) {
        // Always define submitBtn at the top so it's available in both modes
        const submitBtn = form.querySelector('button[type="submit"]');

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

            setLoading(true);

            fetch(`${config.ajax_url}?action=myvh_calendar_get_booking&booking_id=${encodeURIComponent(data.args.id)}&nonce=${encodeURIComponent(nonce)}`)
                .then(r => r.json())
                .then(res => {
                    console.log('Raw response:', res);  // ← add this
                    if (!res || !res.success || !res.data.booking) {
                        throw new Error('Booking not found');
                    }

                    const booking = res.data.booking;

                    // 🔑 IMPORTANT: load dropdowns first
                    return populateDropdowns()
                        .then(() => refreshOrganisations(booking['OrganisationId']))
                        .then(() => booking);
                })
                .then((booking) => {
                    //TODO: This needs tidying up - it’s a mix of old and new code, but the key point is to set values first, then disable fields, and avoid any direct DOM manipulation that conflicts with our controlled approach.
                    // ✅ Safe field mapping
                    setValue('room_id', booking['RoomId']);
                    setValue('customer_id', booking['CustomerId']);
                    setValue('organisation_id', booking['OrganisationId']);
                    setValue('description', booking['Description']);

                    // ✅ Use your helper (don’t manually split)
                    setDisplayedDateTimes(booking['StartDate'], booking['EndDate']);

                    // ❌ REMOVE this (it’s wrong and conflicts)
                    // form.querySelector("[name=text]").value = booking.Description;

                    // ✅ Disable AFTER values set
                    form.querySelectorAll('input, select, textarea')
                        .forEach(el => el.disabled = true);

                    const recurringOptions = form.querySelector('#myvh-modal-recurring-options');
                    if (recurringOptions) recurringOptions.style.display = 'none';

                    modal.classList.remove('hidden');
                    config.onOpen(data);
                })
                .catch(err => {
                    console.error(err);
                    alert('Failed to load booking');
                })
                .finally(() => {
                    setLoading(false);
                });

            return;
        }

        // Only normal (add/edit) mode remains
        if (submitBtn) submitBtn.style.display = '';
        form.reset();

        // Populate dropdowns (rooms, customers), then organisations
        populateDropdowns().then(() => {
            refreshOrganisations();
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

        syncEndDateVisibility();

        // Show modal in add/edit mode
        modal.classList.remove('hidden');
    }

    // Add a close() function to hide modal and reset form
    function close() {
        modal.classList.add('hidden');
        form.reset();
        // Optionally, re-enable all fields
        form.querySelectorAll("select, input, textarea").forEach(el => {
            el.disabled = false;
        });
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

        if (startDate) startDate.value = s.date;
        if (endDate) endDate.value = e.date;
        if (startTime) startTime.value = s.time;
        if (endTime) endTime.value = e.time;
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

        row.style.display = roomAllowsMultiday() ? "" : "none";
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
            el.value = value;
        }
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
    // Submit
    // ─────────────────────────────
    function submit(e) {

        e.preventDefault();

        if (!config.beforeSubmit(form)) {
            return;
        }

        const formData = buildSubmitFormData();

        setLoading(true);
        formData.append("action", "myvh_create_event");
        formData.append("nonce", config.nonce);
        if (config.context) {
            formData.append("context", config.context);
        }

        fetch(config.ajax_url, {
            method: "POST",
            body: formData
        })
        .then(r => r.json())
        .then(res => {

            if (!res.success) {
                alert(res.data || "Failed to create booking");
                return;
            }

            close();
            config.onSuccess(res.data);
        })
        .catch(err => {
            console.error(err);
            alert("Unexpected error creating booking");
        })
        .finally(() => {
            setLoading(false);
        });
    }

    function buildSubmitFormData() {

        const formData = new FormData(form);
        const publicCheckbox = form.querySelector("[name=public]");

        // Always send explicit visibility for modal creates, even when unchecked.
        if (publicCheckbox) {
            formData.set("public", publicCheckbox.checked ? "1" : "0");
        }

        // Disabled controls are excluded from FormData, but locked fields are intentional selections.
        form.querySelectorAll("[name][data-locked=true]").forEach(el => {
            if (!formData.has(el.name)) {
                formData.append(el.name, el.value);
            }
        });

        return formData;
    }

    // ─────────────────────────────
    // UX helpers
    // ─────────────────────────────
    function setLoading(state) {

        const btn = form.querySelector("button[type=submit]");

        if (btn) {
            btn.disabled = state;
            btn.textContent = state ? "Saving..." : "Create Booking";
        }

        form.querySelectorAll("input, select").forEach(el => {
            // Only toggle fields that aren't intentionally locked
            if (!el.dataset.locked) {
                el.disabled = state;
            }
        });
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
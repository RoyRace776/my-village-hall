window.MYVH_BookingModal = (function() {

    let config = {};
    let modal, form;

    function init(userConfig) {

        config = {
            ajax_url: null,
            nonce: null,

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

            // Hooks
            onSuccess: () => {},
            onOpen: () => {},
            onClose: () => {},
            beforeSubmit: () => true, // allow validation hook

            ...userConfig
        };

        modal = document.getElementById("myvh-booking-modal");
        form  = document.getElementById("myvh-booking-form");

        if (!modal || !form) {
            console.error("MYVH_BookingModal: modal or form not found");
            return;
        }

        bindEvents();
    }

    // ─────────────────────────────
    // Events
    // ─────────────────────────────
    function bindEvents() {

        form.addEventListener("submit", submit);

        const cancelBtn = modal.querySelector(".myvh-cancel");
        if (cancelBtn) {
            cancelBtn.addEventListener("click", close);
        }

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

    // ─────────────────────────────
    // Open / Close
    // ─────────────────────────────
    function open(data = {}) {

        resetForm();

        setValue("start", data.start);
        setValue("end", data.end);
        setValue("text", data.text || "");
        setDisplayedDateTimes(data.start, data.end);

        setLoading(true);

        populateDropdowns()
            .then(() => {

                applyContext(data);
                applyVisibility();
                return refreshOrganisations(data.organisation_id || "");
            })
            .then(() => {
                syncEndDateVisibility();

                modal.classList.remove("hidden");
                config.onOpen(data);
            })
            .catch(err => {
                console.error(err);
                alert("Failed to load booking data");
            })
            .finally(() => {
                setLoading(false);
            });
    }

    function close() {
        modal.classList.add("hidden");
        config.onClose();
    }

    function resetForm() {
        form.reset();

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

        syncEndDateVisibility();
    }

    function bindDependentControls() {
        const customer = form.querySelector("[name=customer_id]");
        const room = form.querySelector("[name=room_id]");

        if (customer) {
            customer.addEventListener("change", () => {
                refreshOrganisations("");
            });
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

    function toggleAddonRow(row, enabled) {
        const enabledField = row.querySelector(".myvh-modal-addon-enabled");
        if (enabledField) {
            enabledField.value = enabled ? "1" : "0";
        }

        row.querySelectorAll(".myvh-modal-addon-price, .myvh-modal-addon-qty").forEach(input => {
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

        if (!organisationSelect) {
            return Promise.resolve();
        }

        const customerId = customerSelect ? customerSelect.value : "";

        if (!customerId) {
            organisationSelect.innerHTML = '<option value="">Select...</option>';
            organisationSelect.disabled = true;
            return Promise.resolve();
        }

        organisationSelect.disabled = true;
        organisationSelect.innerHTML = '<option value="">Loading...</option>';

        return Promise.resolve(config.loadOrganisations ? config.loadOrganisations(customerId) : [])
            .then(data => {
                const organisations = Array.isArray(data) ? data : [];
                organisationSelect.innerHTML = '<option value="">Select...</option>';

                organisations.forEach(item => {
                    const opt = document.createElement("option");
                    opt.value = item.id || item.Id;
                    opt.text = item.name || item.Name;
                    organisationSelect.appendChild(opt);
                });

                if (preferredOrgId && organisations.some(item => String(item.id || item.Id) === String(preferredOrgId))) {
                    organisationSelect.value = String(preferredOrgId);
                } else if (organisations.length === 1) {
                    organisationSelect.value = String(organisations[0].id || organisations[0].Id);
                } else {
                    organisationSelect.value = "";
                }

                organisationSelect.disabled = false;
            })
            .catch(() => {
                organisationSelect.innerHTML = '<option value="">Select...</option>';
                organisationSelect.disabled = false;
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
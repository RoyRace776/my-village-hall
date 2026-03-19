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
    }

    // ─────────────────────────────
    // Open / Close
    // ─────────────────────────────
    function open(data = {}) {

        resetForm();

        setValue("start", data.start);
        setValue("end", data.end);
        setValue("text", data.text || "");

        setLoading(true);

        populateDropdowns()
            .then(() => {

                applyContext(data);
                applyVisibility();

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

    function setAndLock(field, value) {
        const el = form.querySelector(`[name=${field}]`);
        if (!el) return;

        el.value = value;
        el.disabled = true;
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
                select.appendChild(opt);
            });
        });
    }

    function populateDropdowns() {

        return Promise.all([
            populateDropdown(form.querySelector("[name=room_id]"), config.loadRooms),
            populateDropdown(form.querySelector("[name=customer_id]"), config.loadCustomers),
            populateDropdown(form.querySelector("[name=organisation_id]"), config.loadOrganisations)
        ]);
    }

    // ─────────────────────────────
    // Submit
    // ─────────────────────────────
    function submit(e) {

        e.preventDefault();

        if (!config.beforeSubmit(form)) {
            return;
        }

        setLoading(true);

        const formData = new FormData(form);
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
            el.disabled = state || el.disabled;
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
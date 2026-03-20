var MYVH_Calendar = (function() {

    var api = null;

    function setActiveViewButton(view) {
        document.querySelectorAll('.myvh-view-btn').forEach(function(button) {
            button.classList.toggle('active', button.dataset.view === view);
        });
    }

    // ─────────────────────────────
    // UI Controls (view + nav)
    // ─────────────────────────────
    function bindControls() {

        const dayBtn   = document.getElementById('myvh-day');
        const weekBtn  = document.getElementById('myvh-week');
        const monthBtn = document.getElementById('myvh-month');

        const nextBtn  = document.getElementById('myvh-next');
        const prevBtn  = document.getElementById('myvh-prev');
        const todayBtn = document.getElementById('myvh-today');

        if (dayBtn) dayBtn.addEventListener('click', () => {
            api.setView('Day');
            setActiveViewButton('Day');
        });

        if (weekBtn) weekBtn.addEventListener('click', () => {
            api.setView('Week');
            setActiveViewButton('Week');
        });

        if (monthBtn) monthBtn.addEventListener('click', () => {
            api.setView('Month');
            setActiveViewButton('Month');
        });

        if (nextBtn)  nextBtn.addEventListener('click', () => api.next());
        if (prevBtn)  prevBtn.addEventListener('click', () => api.prev());
        if (todayBtn) todayBtn.addEventListener('click', () => api.today());
    }

    // ─────────────────────────────
    // Init
    // ─────────────────────────────
    function init() {

        const modal = MYVH_BookingModal;

        modal.init({
            ajax_url: myvhCal.ajax_url,
            nonce: myvhCal.nonce,
            context: 'portal',

            loadRooms: () =>
                fetch(`${myvhCal.ajax_url}?action=myvh_calendar_rooms&nonce=${myvhCal.nonce}&context=portal`)
                    .then(r => r.json()),

            loadCustomers: () =>
                fetch(`${myvhCal.ajax_url}?action=myvh_customers&nonce=${myvhCal.nonce}`)
                    .then(r => r.json()),

            loadOrganisations: (customerId) =>
                fetch(`${myvhCal.ajax_url}?action=myvh_organisations&nonce=${myvhCal.nonce}&customer_id=${encodeURIComponent(customerId || '')}`)
                    .then(r => r.json()),

            lockCustomer: true,
            lockOrganisation: true,
            hideCustomer: true,

            onClose: () => api?.clearSelection?.(),
            onSuccess: () => api.reload()
        });

        api = MYVH_CalendarCore.init("myvh-calendar", {

            context:    "portal",
            ajax_url:   myvhCal.ajax_url,
            nonce:      myvhCal.nonce,
            headerDateFormat: myvhCal.headerDateFormat || null,

            editable:   false,
            selectable: true,
            readOnly:   true,

            onEventClick: function(args) {
                const id = args.e.id ? args.e.id() : args.e.data.id;
                window.location.href = '/dashboard/?tab=view-booking&id=' + id;
            },

            onTimeRangeSelected: function(args) {
                modal.open({
                    start: args.start.toString(),
                    end: args.end.toString(),
                    customer_id: myvhCal.currentCustomerId || '',
                    organisation_id: myvhCal.defaultOrganisationId || ''
                });

                api.clearSelection?.();
            }
        });

        bindControls();
        setActiveViewButton('Week');
    }

    return {
        init: init
    };

})();

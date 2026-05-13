// Public calendar logic for My Village Hall
window.CalendarPublic = (function() {

    let api = null;

    /**
     * Bind UI controls for calendar view and navigation.
     */
    function bindControls() {

        let dayBtn   = document.getElementById('myvh-day');
        let weekBtn  = document.getElementById('myvh-week');
        let monthBtn = document.getElementById('myvh-month');
        let nextBtn  = document.getElementById('myvh-next');
        let prevBtn  = document.getElementById('myvh-prev');
        let todayBtn = document.getElementById('myvh-today');

        if (dayBtn)   dayBtn.addEventListener('click',   function() { api.setView('Day'); });
        if (weekBtn)  weekBtn.addEventListener('click',  function() { api.setView('Week'); });
        if (monthBtn) monthBtn.addEventListener('click', function() { api.setView('Month'); });

        if (nextBtn)  nextBtn.addEventListener('click',  function() { api.next(); });
        if (prevBtn)  prevBtn.addEventListener('click',  function() { api.prev(); });
        if (todayBtn) todayBtn.addEventListener('click', function() { api.today(); });
    }

    function portalAlert(message) {
        if (window.MyvhPortalDialog && typeof window.MyvhPortalDialog.alert === 'function') {
            return window.MyvhPortalDialog.alert(message);
        }

        return Promise.resolve(true);
    }

    /**
     * Initialize the public calendar and controls.
     */
    function init() {

        api = CalendarCore.init('myvh-calendar', {

            context:    'public',
            ajax_url:    myvhCalConfig.ajax_url,
            nonce:      myvhCalConfig.nonce,
            headerDateFormat: myvhCalConfig.headerDateFormat || null,
            startOfWeek: Number.isInteger(Number(myvhCalConfig.startOfWeek)) ? Number(myvhCalConfig.startOfWeek) : 1,
            visibleStartHour: myvhCalConfig.visibleStartHour,
            visibleEndHour: myvhCalConfig.visibleEndHour,
            schedulerOrientation: String(myvhCalConfig.schedulerOrientation || 'horizontal').toLowerCase(),
            statusColors: (myvhCalConfig && myvhCalConfig.statusColors) ? myvhCalConfig.statusColors : null,

            editable:   false,
            selectable: false,
            readOnly:   true,

            onEventClick: function(args) {
                let data = args.e.data;
                portalAlert((data.text || 'Booking') + '\n' + data.start + ' - ' + data.end);
            }
        });

        bindControls();
    }

    return { init: init };

}());

// Public calendar logic for My Village Hall
var CalendarPublic = (function() {

    var api = null;

    /**
     * Bind UI controls for calendar view and navigation.
     */
    function bindControls() {

        var dayBtn   = document.getElementById('myvh-day');
        var weekBtn  = document.getElementById('myvh-week');
        var monthBtn = document.getElementById('myvh-month');
        var nextBtn  = document.getElementById('myvh-next');
        var prevBtn  = document.getElementById('myvh-prev');
        var todayBtn = document.getElementById('myvh-today');

        if (dayBtn)   dayBtn.addEventListener('click',   function() { api.setView('Day'); });
        if (weekBtn)  weekBtn.addEventListener('click',  function() { api.setView('Week'); });
        if (monthBtn) monthBtn.addEventListener('click', function() { api.setView('Month'); });

        if (nextBtn)  nextBtn.addEventListener('click',  function() { api.next(); });
        if (prevBtn)  prevBtn.addEventListener('click',  function() { api.prev(); });
        if (todayBtn) todayBtn.addEventListener('click', function() { api.today(); });
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
            visibleStartHour: myvhCalConfig.visibleStartHour,
            visibleEndHour: myvhCalConfig.visibleEndHour,
            schedulerOrientation: String(myvhCalConfig.schedulerOrientation || 'horizontal').toLowerCase(),
            statusColors: (myvhCalConfig && myvhCalConfig.statusColors) ? myvhCalConfig.statusColors : null,

            editable:   false,
            selectable: false,
            readOnly:   true,

            onEventClick: function(args) {
                var data = args.e.data;
                alert((data.text || 'Booking') + '\n' + data.start + '  ' + data.end);
            }
        });

        bindControls();
    }

    return { init: init };

}());

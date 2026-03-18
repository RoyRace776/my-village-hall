var MYVH_CalendarPublic = (function () {

    var api = null;

    function bindControls() {

        var dayBtn   = document.getElementById('myvh-day');
        var weekBtn  = document.getElementById('myvh-week');
        var monthBtn = document.getElementById('myvh-month');
        var nextBtn  = document.getElementById('myvh-next');
        var prevBtn  = document.getElementById('myvh-prev');
        var todayBtn = document.getElementById('myvh-today');

        if (dayBtn)   dayBtn.addEventListener('click',   function () { api.setView('Day'); });
        if (weekBtn)  weekBtn.addEventListener('click',  function () { api.setView('Week'); });
        if (monthBtn) monthBtn.addEventListener('click', function () { api.setView('Month'); });

        if (nextBtn)  nextBtn.addEventListener('click',  function () { api.next(); });
        if (prevBtn)  prevBtn.addEventListener('click',  function () { api.prev(); });
        if (todayBtn) todayBtn.addEventListener('click', function () { api.today(); });
    }

    function init() {

        api = MYVH_CalendarCore.initCalendar('myvh-calendar', {
            ajaxUrl:    myvhCalConfig.ajaxUrl,   // ← was MYVH.ajax_url
            context:    'public',
            editable:   false,
            selectable: false,
            readOnly:   true,

            onEventClick: function (args) {
                var data = args.e.data;
                alert((data.text || 'Booking') + '\n' + data.start + ' – ' + data.end);
            }
        });

        bindControls();
    }

    return { init: init };

}());
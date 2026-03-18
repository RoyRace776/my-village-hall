
var MYVH_Calendar = (function() {

    var api = null;

    function bindControls() {

        const dayBtn   = document.getElementById('myvh-day');
        const weekBtn  = document.getElementById('myvh-week');
        const monthBtn = document.getElementById('myvh-month');

        const nextBtn  = document.getElementById('myvh-next');
        const prevBtn  = document.getElementById('myvh-prev');
        const todayBtn = document.getElementById('myvh-today');

        if (dayBtn)   dayBtn.addEventListener('click', () => api.setView('Day'));
        if (weekBtn)  weekBtn.addEventListener('click', () => api.setView('Week'));
        if (monthBtn) monthBtn.addEventListener('click', () => api.setView('Month'));

        if (nextBtn)  nextBtn.addEventListener('click', api.next);
        if (prevBtn)  prevBtn.addEventListener('click', api.prev);
        if (todayBtn) todayBtn.addEventListener('click', api.today);
    }

    function init() {

        api = MYVH_CalendarCore.initCalendar("myvh-calendar", {

            context: "portal",
            ajaxUrl: MYVH.ajax_url,

            editable: false,
            selectable: false,
            readOnly: false,

            onEventClick: function(args) {
                const id = args.e.id();

                // Route inside dashboard
                window.location.href = '/dashboard/?tab=view-booking&id=' + id;
            }
        });

        bindControls();
    }

    return {
        init: init
    };

})();
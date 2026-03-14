document.addEventListener("DOMContentLoaded", function () {

    const CalendarManager = {

        calendar: null,
        monthCalendar: null,
        currentView: "Week",
        weekCalendarReady: false,

        init() {

            // BUG FIX 5: Don't call switchView("Week") here — initWeekCalendar()
            // is async (fetches rooms), so this.calendar isn't ready yet.
            // Instead, set the initial active button state directly, and let
            // initWeekCalendar() call this.calendar.init() once rooms are loaded.
            this.setActiveViewButton("Week");

            this.initWeekCalendar();
            this.initMonthCalendar();

            this.attachButtons();

        },

        /*
        -----------------------------
        Week / Day calendar
        -----------------------------
        */

        initWeekCalendar() {

            this.calendar = new DayPilot.Calendar("vbc-calendar");

            this.calendar.headerDateFormat = this.phpDateToDayPilot(myvhAdminCal.dateFormat);

            this.calendar.startDate = DayPilot.Date.today();
            this.calendar.viewType = "Week";

            this.calendar.businessBeginsHour = 7;
            this.calendar.businessEndsHour = 23;

            this.calendar.cellDuration = 15;

            this.calendar.timeRangeSelectedHandling = "Enabled";
            this.calendar.eventMoveHandling = "Update";
            this.calendar.eventResizeHandling = "Update";

            /*
            Create booking
            */

            this.calendar.onTimeRangeSelected = (args) => {

                const start = args.start.toString();
                const end = args.end.toString();
                const room = args.resource;

                window.location =
                    "admin.php?page=my-village-hall&add=1" +
                    "&room_id=" + room +
                    "&start=" + encodeURIComponent(start) +
                    "&end=" + encodeURIComponent(end);

                this.calendar.clearSelection();

            };

            /*
            Move booking
            */

            this.calendar.onEventMoved = (args) => {

                this.updateBooking(
                    args.e.id(),
                    args.newStart,
                    args.newEnd,
                    args.newResource
                );

            };

            /*
            Resize booking
            */

            this.calendar.onEventResized = (args) => {

                this.updateBooking(
                    args.e.id(),
                    args.newStart,
                    args.newEnd,
                    args.newResource
                );

            };

            /*
            Edit booking
            */

            this.calendar.onEventClicked = (args) => {

                this.editBooking(args.e.id());

            };

            /*
            Load rooms
            */

            fetch(ajaxurl + "?action=myvh_calendar_rooms")
                .then(r => r.json())
                .then(rooms => {

                    this.calendar.columns.list = rooms;

                    // Only init and load if the user hasn't switched away
                    // from a week/day view while the fetch was in flight.
                    if (this.currentView !== "Month") {

                        this.calendar.init();

                        this.weekCalendarReady = true;

                        this.loadEvents();

                    }

                });

        },

        /*
        -----------------------------
        Month calendar
        -----------------------------
        */

        initMonthCalendar() {

            // BUG FIX 3: Use a separate container ID for the month calendar
            // so it doesn't collide with the week/day calendar div.
            this.monthCalendar = new DayPilot.Month("vbc-month-calendar");

            this.monthCalendar.startDate = DayPilot.Date.today();

            this.monthCalendar.eventHeight = 20;

            this.monthCalendar.onEventClicked = (args) => {

                this.editBooking(args.e.id());

            };

        },

        /*
        -----------------------------
        Set active view button
        -----------------------------
        */

        setActiveViewButton(view) {

            document
                .querySelectorAll(".vbc-view-btn")
                .forEach(b => b.classList.remove("active"));

            // BUG FIX 1: Match the capitalised data-view values used in the PHP
            document
                .querySelector(`[data-view="${view}"]`)
                ?.classList.add("active");

        },

        /*
        -----------------------------
        Switch view
        -----------------------------
        */

        switchView(view) {

            this.currentView = view;

            this.setActiveViewButton(view);

            if (view === "Month") {

                // Hide week/day calendar, show month calendar
                document.getElementById("vbc-calendar").style.display = "none";
                document.getElementById("vbc-month-calendar").style.display = "";

                this.monthCalendar.init();

                this.loadMonthEvents();

            } else {

                // Hide month calendar, show week/day calendar
                document.getElementById("vbc-calendar").style.display = "";
                document.getElementById("vbc-month-calendar").style.display = "none";

                // BUG FIX 4: update viewType and call update() rather than
                // dispose() + init() — the calendar object was already init'd
                // by initWeekCalendar() and must not be re-init'd from scratch.
                this.calendar.viewType = view;

                if (this.weekCalendarReady) {
                    this.calendar.update();
                } else {
                    this.calendar.init();
                    this.weekCalendarReady = true;
                }

                this.loadEvents();

            }

        },

        /*
        -----------------------------
        Load week/day events
        -----------------------------
        */

        loadEvents() {

            const start = this.calendar.visibleStart();
            const end = this.calendar.visibleEnd();

            this.calendar.events.load(
                ajaxurl +
                "?action=myvh_calendar_events" +
                "&start=" + start +
                "&end=" + end
            );

        },

        /*
        -----------------------------
        Load month events
        -----------------------------
        */

        loadMonthEvents() {

            const start = this.monthCalendar.visibleStart();
            const end = this.monthCalendar.visibleEnd();

            this.monthCalendar.events.load(
                ajaxurl +
                "?action=myvh_calendar_events" +
                "&start=" + start +
                "&end=" + end
            );

        },

        /*
        -----------------------------
        Update booking
        -----------------------------
        */

        updateBooking(id, start, end, room) {

            fetch(ajaxurl, {

                method: "POST",

                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },

                body: new URLSearchParams({
                    action: "myvh_move_booking",
                    id: id,
                    start: start,
                    end: end,
                    room_id: room
                })

            })
                .then(r => r.json())
                .then(data => {

                    if (!data.success) {

                        alert(data.data);

                        this.loadEvents();

                    }

                });

        },

        /*
        -----------------------------
        Edit booking
        -----------------------------
        */

        editBooking(id) {

            window.location =
                "admin.php?page=my-village-hall&edit=" + id;

        },

        /*
        -----------------------------
        Navigation
        -----------------------------
        */

        attachButtons() {

            document
                .getElementById("vbc-prev")
                .addEventListener("click", () => {

                    this.navigatePrev();
                });

            document
                .getElementById("vbc-next")
                .addEventListener("click", () => {

                    this.navigateNext();
                });

            document
                .getElementById("vbc-today")
                .addEventListener("click", () => {

                    this.navigateToday();
                });

            /*
            View buttons
            BUG FIX 1 & 2: Match the capitalised data-view values from the PHP,
            and handle the "Resources" view (the Rooms button).
            */

            document
                .querySelectorAll(".vbc-view-btn")
                .forEach(btn => {

                    btn.addEventListener("click", () => {

                        const view = btn.dataset.view;

                        if (view === "Resources") this.switchView("Resources");
                        if (view === "Day") this.switchView("Day");
                        if (view === "Week") this.switchView("Week");
                        if (view === "Month") this.switchView("Month");

                    });

                });

        },

        /*
        -----------------------------
        Convert PHP date format to DayPilot format
        -----------------------------
        */
        phpDateToDayPilot(phpFormat) {

            const map = {
                'd': 'dd',    // Day, leading zero
                'j': 'd',     // Day, no leading zero
                'D': 'ddd',   // Mon, Tue…
                'l': 'dddd',  // Monday, Tuesday…
                'm': 'MM',    // Month, leading zero
                'n': 'M',     // Month, no leading zero
                'M': 'MMM',   // Jan, Feb…
                'F': 'MMMM',  // January, February…
                'Y': 'yyyy',  // 4-digit year
                'y': 'yy',    // 2-digit year
            };

            return phpFormat
                .split('')
                .map(ch => map[ch] ?? ch)
                .join('');

        },

        navigatePrev() {

            if (this.currentView === "Month") {
                this.monthCalendar.startDate = this.monthCalendar.startDate.addMonths(-1);
                this.monthCalendar.update();
                this.loadMonthEvents();
            } else if (this.currentView === "Week") {
                this.calendar.startDate = this.calendar.startDate.addDays(-7);
                this.calendar.update();
                this.loadEvents();
            } else { // Day
                this.calendar.startDate = this.calendar.startDate.addDays(-1);
                this.calendar.update();
                this.loadEvents();
            }

        },

        navigateNext() {

            if (this.currentView === "Month") {
                this.monthCalendar.startDate = this.monthCalendar.startDate.addMonths(1);
                this.monthCalendar.update();
                this.loadMonthEvents();
            } else if (this.currentView === "Week") {
                this.calendar.startDate = this.calendar.startDate.addDays(7);
                this.calendar.update();
                this.loadEvents();
            } else { // Day
                this.calendar.startDate = this.calendar.startDate.addDays(1);
                this.calendar.update();
                this.loadEvents();
            }

        },

        navigateToday() {

            if (this.currentView === "Month") {

                this.monthCalendar.startDate = DayPilot.Date.today();
                this.monthCalendar.update();
                this.loadMonthEvents();

            } else {

                this.calendar.startDate = DayPilot.Date.today();
                this.calendar.update();
                this.loadEvents();

            }

        }

    };



    CalendarManager.init();

});

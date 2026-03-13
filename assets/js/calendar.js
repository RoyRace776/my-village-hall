document.addEventListener("DOMContentLoaded", function () {

    const calendar = new DayPilot.Calendar("vbc-calendar");

    calendar.startDate = DayPilot.Date.today();
    calendar.viewType = "Resources";

    calendar.businessBeginsHour = 7;
    calendar.businessEndsHour = 23;

    calendar.cellDuration = 15;
    calendar.timeRangeSelectedHandling = "Enabled";

    calendar.eventMoveHandling = "Update";
    calendar.eventResizeHandling = "Update";

    /*
    --------------------------------
    Create booking by dragging
    --------------------------------
    */

    calendar.onTimeRangeSelected = function (args) {

        const start = args.start.toString();
        const end = args.end.toString();
        const room = args.resource;

        window.location =
            "admin.php?page=myvh-bookings&action=new" +
            "&room_id=" + room +
            "&start=" + encodeURIComponent(start) +
            "&end=" + encodeURIComponent(end);

        calendar.clearSelection();
    };

    /*
    --------------------------------
    Move booking
    --------------------------------
    */

    calendar.onEventMoved = function (args) {

        updateBooking(
            args.e.id(),
            args.newStart,
            args.newEnd,
            args.newResource
        );

    };

    /*
    --------------------------------
    Resize booking
    --------------------------------
    */

    calendar.onEventResized = function (args) {

        updateBooking(
            args.e.id(),
            args.newStart,
            args.newEnd,
            args.newResource
        );

    };

    /*
    --------------------------------
    Click booking to edit
    --------------------------------
    */

    calendar.onEventClicked = function (args) {

        window.location =
            "admin.php?page=myvh-bookings&action=edit&id=" +
            args.e.id();

    };

    /*
    --------------------------------
    Load rooms first
    --------------------------------
    */

    fetch(ajaxurl + "?action=myvh_calendar_rooms")
        .then(r => r.json())
        .then(rooms => {

            calendar.columns.list = rooms;

            calendar.init();

            loadEvents();

        });

    /*
    --------------------------------
    Load bookings
    --------------------------------
    */

    function loadEvents() {

        calendar.events.load(
            ajaxurl + "?action=myvh_calendar_events"
        );

    }

    /*
    --------------------------------
    Update booking (drag/drop)
    --------------------------------
    */

    function updateBooking(id, start, end, room) {

        fetch(ajaxurl, {
            method: "POST",
            headers: {
                "Content-Type":
                    "application/x-www-form-urlencoded"
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

                    loadEvents();
                }

            });

    }

    /*
    --------------------------------
    Navigation buttons
    --------------------------------
    */

    document
        .getElementById("vbc-prev")
        .addEventListener("click", () => calendar.prev());

    document
        .getElementById("vbc-next")
        .addEventListener("click", () => calendar.next());

    document
        .getElementById("vbc-today")
        .addEventListener("click", () => calendar.today());

    /*
    --------------------------------
    View buttons
    --------------------------------
    */

    document
        .querySelectorAll(".vbc-view-btn")
        .forEach(btn => {

            btn.addEventListener("click", function () {

                document
                    .querySelectorAll(".vbc-view-btn")
                    .forEach(b =>
                        b.classList.remove("active")
                    );

                this.classList.add("active");

                calendar.viewType =
                    this.dataset.view;

                calendar.update();

            });

        });

});
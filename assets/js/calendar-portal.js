import { initCalendar } from './calendar-core.js';

document.addEventListener('DOMContentLoaded', () => {

    const calendar = initCalendar("myvh-calendar", {
        ajaxUrl: myvhPortal.ajaxUrl,
        context: "portal",

        // Portal is usually read-only (adjust if needed)
        editable: false,
        selectable: false,

        // ─────────────────────────────
        // Event hooks (portal behaviour)
        // ─────────────────────────────
        onEventClick: (args) => {
            openBookingModal(args.e.data);
        }
    });

    // ─────────────────────────────
    // View buttons (Day / Week / Month)
    // ─────────────────────────────
    document.querySelectorAll('[data-view]').forEach(button => {
        button.addEventListener('click', () => {
            const view = button.dataset.view;
            calendar.setView(view);
        });
    });

    // ─────────────────────────────
    // Navigation buttons
    // ─────────────────────────────
    const nextBtn  = document.querySelector('[data-nav="next"]');
    const prevBtn  = document.querySelector('[data-nav="prev"]');
    const todayBtn = document.querySelector('[data-nav="today"]');

    if (nextBtn)  nextBtn.addEventListener('click', calendar.next);
    if (prevBtn)  prevBtn.addEventListener('click', calendar.prev);
    if (todayBtn) todayBtn.addEventListener('click', calendar.today);

});

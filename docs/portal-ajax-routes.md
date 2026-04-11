# Portal AJAX Routes

This inventory lists active portal-facing AJAX actions, their current backend owner, and primary frontend callers.

| Action | Backend owner | Primary caller(s) | Nonce scope | Notes |
| --- | --- | --- | --- | --- |
| myvh_portal_page | PortalController::load_page | assets/js/portal-app.js | myvh_portal | Page-shell loader for hash-routed portal content. |
| myvh_portal_get_uninvoiced_bookings | PortalController::get_uninvoiced_bookings_for_entity | assets/js/portal-app.js | myvh_portal | Invoices drilldown for customer/organisation rows. |
| myvh_portal_update_account | PortalController::update_account | assets/js/portal-app.js | myvh_portal | Account details form submit. |
| myvh_portal_change_password | PortalController::change_password | assets/js/portal-app.js | myvh_portal | Account password form submit. |
| myvh_portal_update_invoice_status | PortalController::update_invoice_status | assets/js/portal-app.js | myvh_portal | Invoice detail status update form. |
| myvh_portal_send_password_reset | PortalController::send_password_reset_email | assets/js/portal-app.js | myvh_portal | Customer password reset button in portal lists. |
| myvh_portal_update_booking | PortalBookingAjaxController::update | templates/Portal/booking-edit.php | myvh_portal | Registered via PortalBookingAjaxController in bootstrap. |
| myvh_portal_create_booking | PortalBookingAjaxController::create_for_modal | assets/js/booking-modal-create.js | myvh_portal | Portal modal create path (phase 2). |
| myvh_portal_update_booking_modal | PortalBookingAjaxController::update_for_modal | assets/js/booking-modal-create.js | myvh_portal | Portal modal edit path (phase 2). |
| myvh_portal_delete_booking | PortalBookingAjaxController::delete | assets/js/calendar-portal.js, templates/Portal/booking-delete.php | myvh_portal | Calendar portal booking delete now uses MyvhPortalAjax helper. |
| myvh_portal_get_booking | PortalBookingAjaxController::get | assets/js/booking-modal-create.js, assets/js/booking-modal-view.js | myvh_portal | Portal modal booking load path (phase 2). |
| myvh_calendar_get_booking | CalendarAjaxController::get_booking | calendar/admin flows | myvh_calendar | Portal modal flows migrated in phase 2. |
| myvh_create_event | CalendarAjaxController::create_event | admin/calendar contexts | myvh_calendar | Retained for non-portal calendar flows. |
| myvh_update_event | CalendarAjaxController::update_event | admin/calendar contexts | myvh_calendar | Retained for non-portal calendar flows. |

## Migration Notes

- Added shared transport helper: assets/js/portal-ajax.js.
- Wired PortalBookingAjaxController registration in bootstrap.
- Kept action names stable for low-risk migration.
- Migrated portal modal get/create/update onto portal booking routes.

# Portal AJAX Routes

This inventory lists active portal-facing AJAX actions, their current backend owner, and primary frontend callers.

| Action | Backend owner | Primary caller(s) | Nonce scope | Notes |
| --- | --- | --- | --- | --- |
| myvh_portal_page | PortalPageAjaxController::load_page | assets/js/portal-app.js | myvh_portal | Page-shell loader for hash-routed portal content. |
| myvh_portal_get_uninvoiced_bookings | PortalBillingAjaxController::get_uninvoiced_bookings_for_entity | assets/js/portal-app.js | myvh_portal | Invoices drilldown for customer/organisation rows. |
| myvh_portal_create_invoice | PortalBillingAjaxController::create_invoice | assets/js/portal-app.js | myvh_portal | Manual invoice creation from selected bookings. |
| myvh_portal_run_auto_invoicing | AutoInvoicingAjaxController::run_portal | assets/js/portal-app.js | myvh_portal | Runs auto-invoicing using configured rules. |
| myvh_portal_update_invoice_status | PortalBillingAjaxController::update_invoice_status | assets/js/portal-app.js | myvh_portal | Invoice detail status update form. |
| myvh_portal_create_payment | PortalBillingAjaxController::create_payment | assets/js/portal-app.js | myvh_portal | Payment create in portal payments view. |
| myvh_portal_delete_payment | PortalBillingAjaxController::delete_payment | assets/js/portal-app.js | myvh_portal | Payment delete in portal payments view. |
| myvh_portal_update_account | PortalAccountAjaxController::update_account | assets/js/portal-app.js | myvh_portal | Account details form submit. |
| myvh_portal_change_password | PortalAccountAjaxController::change_password | assets/js/portal-app.js | myvh_portal | Account password form submit. |
| myvh_portal_send_password_reset | PortalAccountAjaxController::send_password_reset_email | assets/js/portal-app.js | myvh_portal | Customer password reset button in portal lists. |
| myvh_portal_add_client_admin | PortalPeopleAjaxController::add_client_admin | assets/js/portal-app.js | myvh_portal | Add client admin assignment. |
| myvh_portal_remove_client_admin | PortalPeopleAjaxController::remove_client_admin | assets/js/portal-app.js | myvh_portal | Remove client admin assignment. |
| myvh_portal_save_customer | PortalPeopleAjaxController::save_customer | assets/js/portal-app.js | myvh_portal | Create/update customer profile from portal. |
| myvh_portal_delete_customer | PortalPeopleAjaxController::delete_customer | assets/js/portal-app.js | myvh_portal | Delete customer profile from portal. |
| myvh_portal_request_org_membership | PortalOrganisationAjaxController::request_organisation_membership | assets/js/portal-app.js | myvh_portal | Customer membership request action. |
| myvh_portal_approve_org_request | PortalOrganisationAjaxController::approve_organisation_membership_request | assets/js/portal-app.js | myvh_portal | Membership request approval flow. |
| myvh_portal_reject_org_request | PortalOrganisationAjaxController::reject_organisation_membership_request | assets/js/portal-app.js | myvh_portal | Membership request rejection flow. |
| myvh_portal_add_organisation | PortalOrganisationAjaxController::add_organisation | assets/js/portal-app.js | myvh_portal | Create organisation from portal form. |
| myvh_portal_save_org_type_assignment | PortalOrganisationAjaxController::save_organisation_type_assignment | assets/js/portal-app.js | myvh_portal | Assign/update organisation type by admin. |
| myvh_portal_save_org_billing | PortalOrganisationAjaxController::save_organisation_billing | assets/js/portal-app.js | myvh_portal | Organisation billing details update. |
| myvh_portal_org_add_member | PortalOrganisationAjaxController::organisation_add_member | assets/js/portal-app.js | myvh_portal | Add organisation member by email. |
| myvh_portal_org_remove_member | PortalOrganisationAjaxController::organisation_remove_member | assets/js/portal-app.js | myvh_portal | Remove organisation member. |
| myvh_portal_org_set_admin | PortalOrganisationAjaxController::organisation_set_member_admin | assets/js/portal-app.js | myvh_portal | Toggle organisation member admin status. |
| myvh_portal_save_org_type | PortalAdminConfigAjaxController::save_organisation_type | assets/js/portal-app.js | myvh_portal | Create/update organisation type catalogue. |
| myvh_portal_delete_org_type | PortalAdminConfigAjaxController::delete_organisation_type | assets/js/portal-app.js | myvh_portal | Delete organisation type catalogue entry. |
| myvh_portal_save_room | PortalAdminConfigAjaxController::save_room | assets/js/portal-app.js | myvh_portal | Create/update room record. |
| myvh_portal_delete_room | PortalAdminConfigAjaxController::delete_room | assets/js/portal-app.js | myvh_portal | Delete room record. |
| myvh_portal_save_venue | PortalAdminConfigAjaxController::save_venue | assets/js/portal-app.js | myvh_portal | Create/update venue record. |
| myvh_portal_delete_venue | PortalAdminConfigAjaxController::delete_venue | assets/js/portal-app.js | myvh_portal | Delete venue record. |
| myvh_portal_save_room_rate | PortalAdminConfigAjaxController::save_room_rate | assets/js/portal-app.js | myvh_portal | Create/update room rate record. |
| myvh_portal_delete_room_rate | PortalAdminConfigAjaxController::delete_room_rate | assets/js/portal-app.js | myvh_portal | Delete room rate record. |
| myvh_portal_save_addon | PortalAdminConfigAjaxController::save_addon | assets/js/portal-app.js | myvh_portal | Create/update add-on record. |
| myvh_portal_delete_addon | PortalAdminConfigAjaxController::delete_addon | assets/js/portal-app.js | myvh_portal | Archive add-on record. |
| myvh_portal_save_client_settings | PortalAdminConfigAjaxController::save_client_settings | assets/js/portal-app.js | myvh_portal | Save portal client settings group. |
| myvh_portal_update_booking | PortalBookingAjaxController::update | templates/Portal/booking-edit.php | myvh_portal | Registered via PortalBookingAjaxController in bootstrap. |
| myvh_portal_create_booking | PortalBookingAjaxController::create_for_modal | assets/js/booking-modal-create.js | myvh_portal | Portal modal create path (phase 2). |
| myvh_portal_update_booking_modal | PortalBookingAjaxController::update_for_modal | assets/js/booking-modal-create.js | myvh_portal | Portal modal edit path (phase 2). |
| myvh_portal_delete_booking | PortalBookingAjaxController::delete | assets/js/calendar-portal.js, templates/Portal/booking-delete.php | myvh_portal | Calendar portal booking delete now uses MyvhPortalAjax helper. |
| myvh_portal_get_booking | PortalBookingAjaxController::get | assets/js/booking-modal-create.js, assets/js/booking-modal-view.js | myvh_portal | Portal modal booking load path (phase 2). |
| myvh_portal_next_booking_slot | PortalBookingAjaxController::next_slot | tests/e2e/booking-lifecycle.spec.js | myvh_portal | Returns the next available room slot using AvailabilityService (7-day search, buffer-aware). |
| myvh_calendar_get_booking | CalendarAjaxController::get_booking | calendar/admin flows | myvh_calendar | Portal modal flows migrated in phase 2. |
| myvh_create_event | CalendarAjaxController::create_event | admin/calendar contexts | myvh_calendar | Retained for non-portal calendar flows. |
| myvh_update_event | CalendarAjaxController::update_event | admin/calendar contexts | myvh_calendar | Retained for non-portal calendar flows. |

## Migration Notes

- Added shared transport helper: assets/js/portal-ajax.js.
- Wired dedicated portal AJAX controllers in bootstrap/service provider (account, people, billing, organisation, admin config, booking).
- Kept action names stable for low-risk migration.
- Migrated portal modal get/create/update onto portal booking routes.

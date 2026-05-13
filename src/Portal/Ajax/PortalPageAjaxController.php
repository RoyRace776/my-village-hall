<?php
namespace MYVH\Portal\Ajax;

use MYVH\Bookings\BookingService;
use MYVH\Customers\CustomerService;
use MYVH\Portal\ClientAdminService;
use MYVH\Portal\Support\AjaxResponse;

class PortalPageAjaxController {
    public function __construct(
        private BookingService $booking_service,
        private CustomerService $customer_service,
        private ClientAdminService $client_admin_service,
        private PortalBillingPageRenderer $billing_page_renderer,
        private PortalPeoplePageRenderer $people_page_renderer,
        private PortalOrganisationPageRenderer $organisation_page_renderer,
        private PortalAdminConfigPageRenderer $admin_config_page_renderer
    ) {}

    public function register(): void {
        add_action('wp_ajax_myvh_portal_page', [$this, 'load_page']);
    }

    public function load_page(): void {
        if (!is_user_logged_in()) {
            AjaxResponse::auth_error(__('Not logged in', 'my-village-hall'));
        }

        check_ajax_referer('myvh_portal', 'nonce');

        $page = sanitize_text_field($_GET['page'] ?? 'dashboard');
        $customer = $this->customer_service->get_by_user_id(get_current_user_id());
        if ($customer == null) {
            $customer = [];
        }
        $is_client_admin = $this->current_user_is_client_admin();
        $has_customer = !empty($customer['Id']);

        switch ($page) {
            case 'bookings':
                $groups = $this->get_portal_booking_groups($customer, $is_client_admin);
                $can_delete_booking = function(array $booking): bool {
                    $delete_rules = $this->booking_service->can_delete($booking);
                    return !empty($delete_rules['can_delete']);
                };
                include MYVH_PLUGIN_DIR . 'templates/Portal/bookings.php';
                break;

            case 'calendar':
            case 'bookings-new':
            case 'booking-view':
            case 'booking-edit':
            case 'booking-delete':
                $can_create_booking = $is_client_admin || $has_customer;
                include MYVH_PLUGIN_DIR . 'templates/Portal/calendar.php';
                break;

            case 'invoices':
                $this->billing_page_renderer->render_invoices($customer, $is_client_admin, $has_customer);
                break;

            case 'invoice-view':
                $this->billing_page_renderer->render_invoice_view($customer, $is_client_admin);
                break;

            case 'payments':
                $this->billing_page_renderer->render_payments($is_client_admin);
                break;

            case 'invoice-generate':
                $this->billing_page_renderer->render_invoice_generate($is_client_admin);
                break;

            case 'account':
                $this->people_page_renderer->render_account();
                break;

            case 'client-admins':
                $this->people_page_renderer->render_client_admins($is_client_admin);
                break;

            case 'customers':
                $this->people_page_renderer->render_customers($is_client_admin);
                break;

            case 'customer-edit':
                $this->people_page_renderer->render_customer_edit($is_client_admin);
                break;

            case 'customer-add':
                $this->people_page_renderer->render_customer_add($is_client_admin);
                break;

            case 'organisation-add':
                $this->organisation_page_renderer->render_organisation_add($customer, $is_client_admin);
                break;

            case 'rooms':
                $this->admin_config_page_renderer->render_rooms($is_client_admin);
                break;

            case 'venues':
                $this->admin_config_page_renderer->render_venues($is_client_admin);
                break;

            case 'venue-add':
                $this->admin_config_page_renderer->render_venue_add($is_client_admin);
                break;

            case 'venue-edit':
                $this->admin_config_page_renderer->render_venue_edit($is_client_admin);
                break;

            case 'room-add':
                $this->admin_config_page_renderer->render_room_add($is_client_admin);
                break;

            case 'room-edit':
                $this->admin_config_page_renderer->render_room_edit($is_client_admin);
                break;

            case 'room-rates':
                $this->admin_config_page_renderer->render_room_rates($is_client_admin);
                break;

            case 'addons':
                $this->admin_config_page_renderer->render_addons($is_client_admin);
                break;

            case 'addon-add':
                $this->admin_config_page_renderer->render_addon_add($is_client_admin);
                break;

            case 'addon-edit':
                $this->admin_config_page_renderer->render_addon_edit($is_client_admin);
                break;

            case 'room-rate-add':
                $this->admin_config_page_renderer->render_room_rate_add($is_client_admin);
                break;

            case 'room-rate-edit':
                $this->admin_config_page_renderer->render_room_rate_edit($is_client_admin);
                break;

            case 'organisation-types':
                $this->organisation_page_renderer->render_organisation_types($is_client_admin);
                break;

            case 'organisation-type-add':
                $this->organisation_page_renderer->render_organisation_type_add($is_client_admin);
                break;

            case 'organisation-type-edit':
                $this->organisation_page_renderer->render_organisation_type_edit($is_client_admin);
                break;

            case 'settings':
                $this->admin_config_page_renderer->render_settings($is_client_admin);
                break;

            case 'single-booking-invoice-rules':
                $this->admin_config_page_renderer->render_single_booking_invoice_rules($is_client_admin);
                break;

            case 'recurring-booking-invoice-rules':
                $this->admin_config_page_renderer->render_recurring_booking_invoice_rules($is_client_admin);
                break;

            case 'email-templates':
                $this->admin_config_page_renderer->render_email_templates($is_client_admin);
                break;

            case 'email-template-edit':
                $this->admin_config_page_renderer->render_email_template_edit($is_client_admin);
                break;

            case 'audit-log':
                $this->admin_config_page_renderer->render_audit_log($is_client_admin);
                break;

            case 'organisations':
                $this->organisation_page_renderer->render_organisations($customer, $is_client_admin);
                break;

            default:
                $groups = $this->get_portal_booking_groups($customer, $is_client_admin);
                $can_delete_booking = function(array $booking): bool {
                    $delete_rules = $this->booking_service->can_delete($booking);
                    return !empty($delete_rules['can_delete']);
                };
                $member_organisations = [];
                $dashboard_unpaid_invoices = [];
                $admin_room_activity = [];
                $admin_invoice_action_bookings = [];
                $admin_overdue_invoices = [];
                $admin_dashboard_counts = [];
                if (!$is_client_admin) {
                    $member_organisations = $this->customer_service->get_organisations_for_user_id(get_current_user_id());
                    $dashboard_unpaid_invoices = $this->billing_page_renderer->get_unpaid_invoice_summary($customer);
                } else {
                    $admin_room_activity = $this->build_admin_room_activity($groups);
                    $admin_invoice_action_bookings = $this->get_admin_bookings_pending_invoice_or_draft();
                    $admin_overdue_invoices = $this->get_admin_overdue_invoices();
                    $admin_dashboard_counts = $this->get_admin_dashboard_counts();
                }
                include MYVH_PLUGIN_DIR . 'templates/Portal/dashboard.php';
        }

        wp_die();
    }

    private function get_portal_booking_groups(array $customer, bool $is_client_admin): array {
        if ($is_client_admin) {
            $result = $this->booking_service->get_booking_list();
            return $result['groups'];
        }

        if (empty($customer['Id'])) {
            return [];
        }

        $result = $this->booking_service->get_booking_list([
            'customer_id' => $customer['Id'],
        ]);

        return $result['groups'];
    }

    private function current_user_is_client_admin(): bool {
        return $this->client_admin_service->can_administer_blog(get_current_user_id(), get_current_blog_id());
    }

    private function build_admin_room_activity(array $groups): array {
        $today = date('Y-m-d');
        $last_month_start = date('Y-m-d', strtotime('-1 month'));
        $next_month_end = date('Y-m-d', strtotime('+1 month'));

        $room_stats = [];
        $seen_booking_ids = [];

        foreach ($groups as $group) {
            foreach (($group['bookings'] ?? []) as $booking) {
                $booking_id = intval($booking['Id'] ?? 0);
                if ($booking_id <= 0 || isset($seen_booking_ids[$booking_id])) {
                    continue;
                }
                $seen_booking_ids[$booking_id] = true;

                $status = strtolower((string) ($booking['Status'] ?? ''));
                if ($status === 'cancelled') {
                    continue;
                }

                $room_name = trim((string) ($booking['RoomName'] ?? ''));
                if ($room_name === '') {
                    $room_name = 'Room booking';
                }

                if (!isset($room_stats[$room_name])) {
                    $room_stats[$room_name] = [
                        'room_name' => $room_name,
                        'pending_bookings' => 0,
                        'last_month_bookings' => 0,
                        'last_month_hours' => 0.0,
                        'next_month_bookings' => 0,
                        'next_month_hours' => 0.0,
                    ];
                }

                $booking_date = (string) ($booking['StartDate'] ?? '');
                $chargeable_hours = $this->resolve_booking_hours($booking);

                if ($status === 'pending') {
                    $room_stats[$room_name]['pending_bookings']++;
                }

                if ($booking_date >= $last_month_start && $booking_date < $today) {
                    $room_stats[$room_name]['last_month_bookings']++;
                    $room_stats[$room_name]['last_month_hours'] += $chargeable_hours;
                }

                if ($booking_date >= $today && $booking_date <= $next_month_end) {
                    $room_stats[$room_name]['next_month_bookings']++;
                    $room_stats[$room_name]['next_month_hours'] += $chargeable_hours;
                }
            }
        }

        $rows = array_values($room_stats);
        usort($rows, static function (array $left, array $right): int {
            return strcmp((string) ($left['room_name'] ?? ''), (string) ($right['room_name'] ?? ''));
        });

        return $rows;
    }

    private function resolve_booking_hours(array $booking): float {
        if (isset($booking['ChargeableHours']) && is_numeric($booking['ChargeableHours'])) {
            return max(0.0, (float) $booking['ChargeableHours']);
        }

        $start_stamp = strtotime((string) ($booking['StartDate'] ?? '') . ' ' . (string) ($booking['StartTime'] ?? '00:00:00'));
        $end_stamp = strtotime((string) ($booking['EndDate'] ?? $booking['StartDate'] ?? '') . ' ' . (string) ($booking['EndTime'] ?? '00:00:00'));
        if ($start_stamp === false || $end_stamp === false || $end_stamp <= $start_stamp) {
            return 0.0;
        }

        return round(($end_stamp - $start_stamp) / 3600, 2);
    }

    private function get_admin_bookings_pending_invoice_or_draft(): array {
        global $wpdb;

        $bookings_table = $wpdb->prefix . 'myvh_bookings';
        $invoice_items_table = $wpdb->prefix . 'myvh_invoice_items';
        $invoices_table = $wpdb->prefix . 'myvh_invoices';
        $customers_table = $wpdb->prefix . 'myvh_customers';
        $organisations_table = $wpdb->prefix . 'myvh_organisations';
        $rooms_table = $wpdb->prefix . 'myvh_rooms';

        $sql = "SELECT
                    b.Id,
                    b.RecurringPatternId,
                    b.StartDate,
                    b.StartTime,
                    b.EndTime,
                    b.Description,
                    b.ChargeableHours,
                    c.Name AS CustomerName,
                    o.Name AS OrganisationName,
                    r.Name AS RoomName,
                    MAX(CASE WHEN i.Status = 'draft' THEN i.InvoiceNumber ELSE NULL END) AS DraftInvoiceNumber,
                    SUM(CASE WHEN i.Id IS NOT NULL THEN 1 ELSE 0 END) AS ActiveInvoiceCount,
                    SUM(CASE WHEN i.Status = 'draft' THEN 1 ELSE 0 END) AS DraftInvoiceCount,
                    SUM(CASE WHEN i.Status != 'draft' THEN 1 ELSE 0 END) AS NonDraftInvoiceCount
                FROM {$bookings_table} b
                LEFT JOIN {$customers_table} c ON b.CustomerId = c.Id
                LEFT JOIN {$organisations_table} o ON b.OrganisationId = o.Id
                LEFT JOIN {$rooms_table} r ON b.RoomId = r.Id
                LEFT JOIN {$invoice_items_table} ii ON b.Id = ii.BookingId
                LEFT JOIN {$invoices_table} i ON ii.InvoiceId = i.Id AND i.Status != 'cancelled'
                WHERE b.Status IN ('confirmed', 'completed')
                  AND b.NoInvoiceRequired = 0
                GROUP BY b.Id
                HAVING ActiveInvoiceCount = 0
                   OR (DraftInvoiceCount > 0 AND NonDraftInvoiceCount = 0)
                ORDER BY b.StartDate ASC, b.StartTime ASC
                LIMIT 250";

        $rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
        if (empty($rows)) {
            return [];
        }

        $result = [];
        $group_index_by_key = [];

        foreach ($rows as $row) {
            $pattern_id = intval($row['RecurringPatternId'] ?? 0);
            $is_groupable_recurring = $pattern_id > 0;
            if (!$is_groupable_recurring) {
                $row['IsRecurringGroup'] = 0;
                $row['RecurringBookingCount'] = 1;
                $result[] = $row;
                continue;
            }

            $group_key = 'recurring-' . (string) $pattern_id;
            if (!isset($group_index_by_key[$group_key])) {
                $row['IsRecurringGroup'] = 1;
                $row['RecurringBookingCount'] = 1;
                $row['RecurringLastDate'] = (string) ($row['StartDate'] ?? '');
                $row['RecurringDraftInvoiceNumbers'] = [];
                if (!empty($row['DraftInvoiceNumber'])) {
                    $row['RecurringDraftInvoiceNumbers'][] = (string) $row['DraftInvoiceNumber'];
                }

                $result[] = $row;
                $group_index_by_key[$group_key] = count($result) - 1;
                continue;
            }

            $result_index = $group_index_by_key[$group_key];
            $result[$result_index]['RecurringBookingCount'] = intval($result[$result_index]['RecurringBookingCount'] ?? 1) + 1;

            $existing_last_date = (string) ($result[$result_index]['RecurringLastDate'] ?? '');
            $current_date = (string) ($row['StartDate'] ?? '');
            if ($current_date !== '' && ($existing_last_date === '' || strcmp($current_date, $existing_last_date) > 0)) {
                $result[$result_index]['RecurringLastDate'] = $current_date;
            }

            if (!empty($row['DraftInvoiceNumber'])) {
                $draft_numbers = $result[$result_index]['RecurringDraftInvoiceNumbers'] ?? [];
                $draft_numbers[] = (string) $row['DraftInvoiceNumber'];
                $result[$result_index]['RecurringDraftInvoiceNumbers'] = array_values(array_unique($draft_numbers));
            }
        }

        foreach ($result as &$entry) {
            $draft_numbers = $entry['RecurringDraftInvoiceNumbers'] ?? [];
            if (count($draft_numbers) > 1) {
                $entry['DraftInvoiceNumber'] = '';
                $entry['HasMultipleDraftInvoices'] = 1;
            } elseif (count($draft_numbers) === 1) {
                $entry['DraftInvoiceNumber'] = $draft_numbers[0];
                $entry['HasMultipleDraftInvoices'] = 0;
            } else {
                $entry['HasMultipleDraftInvoices'] = 0;
            }

            unset($entry['RecurringDraftInvoiceNumbers']);
        }
        unset($entry);

        return array_slice($result, 0, 12);
    }

    private function get_admin_overdue_invoices(): array {
        global $wpdb;

        $invoices_table = $wpdb->prefix . 'myvh_invoices';
        $customers_table = $wpdb->prefix . 'myvh_customers';
        $invoice_items_table = $wpdb->prefix . 'myvh_invoice_items';
        $bookings_table = $wpdb->prefix . 'myvh_bookings';
        $organisations_table = $wpdb->prefix . 'myvh_organisations';

        $sql = "SELECT
                    i.Id,
                    i.InvoiceNumber,
                    i.DueDate,
                    i.AmountDue,
                    i.Status,
                    COALESCE(c.Name, i.BillingName, i.BillingOrganisationName, '') AS CustomerName,
                    COALESCE(MAX(o.Name), MAX(i.BillingOrganisationName), '') AS OrganisationName
                FROM {$invoices_table} i
                LEFT JOIN {$customers_table} c ON i.CustomerId = c.Id
                LEFT JOIN {$invoice_items_table} ii ON i.Id = ii.InvoiceId
                LEFT JOIN {$bookings_table} b ON ii.BookingId = b.Id
                LEFT JOIN {$organisations_table} o ON b.OrganisationId = o.Id
                WHERE i.Status IN ('sent', 'part-paid', 'overdue')
                  AND i.AmountDue > 0
                  AND i.DueDate < CURDATE()
                GROUP BY i.Id
                ORDER BY i.DueDate ASC
                LIMIT 12";

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    private function get_admin_dashboard_counts(): array {
        global $wpdb;

        $organisations_table = $wpdb->prefix . 'myvh_organisations';
        $member_requests_table = $wpdb->prefix . 'myvh_organisation_member_requests';
        $customers_table = $wpdb->prefix . 'myvh_customers';

        $organisations_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$organisations_table} WHERE IsSystem = 0");
        $pending_member_requests_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$member_requests_table} WHERE Status = %s",
            'pending'
        ));
        $customers_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$customers_table} WHERE IsSystem = 0");
        $customers_last_month_count = (int) $wpdb->get_var(
            "SELECT COUNT(*)
             FROM {$customers_table}
             WHERE IsSystem = 0
               AND Created >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"
        );
        return [
            'organisations_count' => $organisations_count,
            'pending_member_requests_count' => $pending_member_requests_count,
            'customers_count' => $customers_count,
            'customers_last_month_count' => $customers_last_month_count,
        ];
    }
}
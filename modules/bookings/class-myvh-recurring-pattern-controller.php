<?php
if (!defined('ABSPATH')) exit;

class MYVH_Recurring_Pattern_Controller {

    private $service;

    public function __construct(MYVH_Recurring_Pattern_Service $service) {
        $this->service = $service;
    }

    public function save(): void {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_save_recurring_pattern');

        $result = $this->service->save($_POST);

        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=myvh-recurring&error=' . urlencode($result->get_error_message())));
            exit;
        }

        $message = !empty($_POST['pattern_id'])
            ? __('Recurring pattern updated and bookings regenerated.', 'my-village-hall')
            : __('Recurring pattern created and bookings scheduled.', 'my-village-hall');

        $results = $this->service->get_last_booking_results();
        if (is_array($results) && !empty($results['conflicts'])) {
            $message .= ' ' . sprintf(
                __('Conflicting child bookings skipped: %s', 'my-village-hall'),
                implode(', ', $results['conflicts'])
            );
        }

        wp_redirect(admin_url('admin.php?page=myvh-recurring&updated=1&message=' . urlencode($message)));
        exit;
    }

    public function delete(): void {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_delete_recurring_pattern');

        $id = intval($_GET['id']);
        $this->service->delete_future_bookings($id);
        $this->service->delete($id);

        wp_redirect(admin_url('admin.php?page=myvh-recurring&deleted=1'));
        exit;
    }

    public function deactivate(): void {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_deactivate_recurring_pattern');

        $id = intval($_GET['id']);
        $this->service->deactivate($id);

        if (!empty($_GET['cancel_future'])) {
            $this->service->cancel_future_bookings($id);
        }

        wp_redirect(admin_url('admin.php?page=myvh-recurring&updated=1&message=' . urlencode(
            __('Pattern deactivated.', 'my-village-hall')
        )));
        exit;
    }

    public function delete_future_bookings(): void {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_delete_future_bookings');

        $id = intval($_GET['id']);
        $this->service->delete_future_bookings($id);

        wp_redirect(admin_url('admin.php?page=myvh-recurring&updated=1&message=' . urlencode(
            __('Future bookings deleted.', 'my-village-hall')
        )));
        exit;
    }

    public function process_patterns(): void {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }
        check_admin_referer('myvh_process_patterns');
        wp_redirect(admin_url('admin.php?page=myvh-recurring'));
        exit;
    }
}

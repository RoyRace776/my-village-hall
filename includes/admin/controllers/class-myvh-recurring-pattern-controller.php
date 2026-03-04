<?php
if (!defined('ABSPATH')) exit;

class MYVH_Recurring_Pattern_Controller {

    private $service;

    public function __construct($service) {
        $this->service = $service;
    }

    public function save() {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_save_recurring_pattern');

        $this->service->save($_POST);

        wp_redirect(admin_url('admin.php?page=myvh-recurring&updated=1'));
        exit;
    }

    public function delete() {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_delete_recurring_pattern');

        $id = intval($_GET['id']);
        $this->service->delete($id);

        wp_redirect(admin_url('admin.php?page=myvh-recurring&deleted=1'));
        exit;
    }

    public function deactivate() {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_deactivate_recurring_pattern');

        $id = intval($_GET['id']);
        $this->service->deactivate($id);

        wp_redirect(admin_url('admin.php?page=myvh-recurring&updated=1'));
        exit;
    }

    public function process_patterns() {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_process_patterns');

        $results = $this->service->process_patterns();

        $message = sprintf(
            __('Processed %d patterns, created %d bookings', 'my-village-hall'),
            $results['processed'],
            $results['created']
        );

        if (!empty($results['errors'])) {
            $message .= '. ' . __('Errors: ', 'my-village-hall') . implode(', ', $results['errors']);
        }

        wp_redirect(admin_url('admin.php?page=myvh-recurring&message=' . urlencode($message)));
        exit;
    }
}

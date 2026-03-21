<?php

if (!defined('ABSPATH')) exit;

class MYVH_Venue_Controller {

    private $service;

    public function __construct(MYVH_Venue_Service $service) {
        $this->service = $service;
    }

    public function save(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_save_venue');

        $result = $this->service->save($_POST);

        wp_redirect(admin_url('admin.php?page=myvh-venues&updated=1'));
        exit;
    }

    public function delete(): void {

        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_delete_venue');

        $id = intval($_GET['id']);
        $this->service->delete($id);

        wp_redirect(admin_url('admin.php?page=myvh-venues&deleted=1'));
        exit;
    }
}
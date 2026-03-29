<?php
namespace MYVH\Organisations;

if (!defined('ABSPATH')) exit;

class OrganisationTypeController {
    private $service;
    private $request_validator;
    public function __construct(
        OrganisationTypeService $service,
        OrganisationTypeRequestValidator $request_validator
    ) {
        $this->service = $service;
        $this->request_validator = $request_validator;
    }
    public function save(): void {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }
        check_admin_referer('myvh_save_org_type');
        $data = SaveOrganisationTypeRequest::from_post(wp_unslash($_POST));
        $validation_result = $this->request_validator->validate($data);
        if (is_wp_error($validation_result)) {
            wp_redirect(admin_url('admin.php?page=myvh-org-types&error=' . urlencode($validation_result->get_error_message())));
            exit;
        }
        $result = $this->service->save($data);
        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=myvh-org-types&error=' . urlencode($result->get_error_message())));
            exit;
        }
        wp_redirect(admin_url('admin.php?page=myvh-org-types&updated=1'));
        exit;
    }
    public function delete(): void {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }
        check_admin_referer('myvh_delete_org_type');
        $data = DeleteOrganisationTypeRequest::from_query($_GET);
        $validation_result = $this->request_validator->validate_delete($data);
        if (is_wp_error($validation_result)) {
            wp_redirect(admin_url('admin.php?page=myvh-org-types&error=' . urlencode($validation_result->get_error_message())));
            exit;
        }
        $this->service->delete($data['id']);
        wp_redirect(admin_url('admin.php?page=myvh-org-types&deleted=1'));
        exit;
    }
}

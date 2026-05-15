<?php
namespace MYVH\Organisations;

if (!defined('ABSPATH')) exit;

class OrganisationController {
    private $service;
    private $request_validator;

    public function __construct(
        OrganisationService $service,
        OrganisationRequestValidator $request_validator
    ) {
        $this->service = $service;
        $this->request_validator = $request_validator;
    }

    public function save(): void {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }
        check_admin_referer('myvh_save_organisation');
        $data = SaveOrganisationRequest::from_post(wp_unslash($_POST), true);
        $is_edit = !empty($data['organisation_id']);
        $error_redirect = $is_edit
            ? admin_url('admin.php?page=myvh-organisations&edit=' . \intval($data['organisation_id']))
            : admin_url('admin.php?page=myvh-organisation-add');

        $validation_result = $this->request_validator->validate($data);
        if (is_wp_error($validation_result)) {
            wp_redirect($error_redirect . '&error=' . urlencode($validation_result->get_error_message()));
            exit;
        }
        $result = $this->service->save($data, true);
        if (is_wp_error($result)) {
            wp_redirect($error_redirect . '&error=' . urlencode($result->get_error_message()));
            exit;
        }
        wp_redirect(admin_url('admin.php?page=myvh-organisations&updated=1'));
        exit;
    }

    public function delete(): void {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }
        check_admin_referer('myvh_delete_organisation');
        $data = DeleteOrganisationRequest::from_query($_GET);
        $validation_result = $this->request_validator->validate_delete($data);
        if (is_wp_error($validation_result)) {
            wp_redirect(admin_url('admin.php?page=myvh-organisations&error=' . urlencode($validation_result->get_error_message())));
            exit;
        }
        $result = $this->service->delete($data['id']);
        if (is_wp_error($result) || !$result) {
            $message = is_wp_error($result)
                ? $result->get_error_message()
                : __('Failed to delete organisation', 'my-village-hall');
            wp_redirect(admin_url('admin.php?page=myvh-organisations&error=' . urlencode($message)));
            exit;
        }

        wp_redirect(admin_url('admin.php?page=myvh-organisations&deleted=1'));
        exit;
    }

    public function add_member(): void {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }
        check_admin_referer('myvh_add_org_member');
        $data = AddOrgMemberRequest::from_post(wp_unslash($_POST));
        $validation_result = $this->request_validator->validate_add_member($data);
        if (is_wp_error($validation_result)) {
            wp_redirect(admin_url('admin.php?page=myvh-org-members&organisation_id=' . \intval($data['organisation_id']) . '&error=' . urlencode($validation_result->get_error_message())));
            exit;
        }
        $org_id  = $data['organisation_id'];
        $user_id = $data['user_id'];
        $result = $this->service->add_member($org_id, $user_id);
        if ($data['redirect'] === 'customer' && !empty($data['customer_id'])) {
            $back = admin_url('admin.php?page=myvh-customers&edit=' . \intval($data['customer_id']));
            if (is_wp_error($result)) {
                $back .= '&error=' . urlencode($result->get_error_message());
            } else {
                $back .= '&updated=1';
            }
            wp_redirect($back);
            exit;
        }
        if (is_wp_error($result)) {
            wp_redirect(admin_url('admin.php?page=myvh-org-members&organisation_id=' . $org_id . '&error=' . urlencode($result->get_error_message())));
            exit;
        }
        wp_redirect(admin_url('admin.php?page=myvh-org-members&organisation_id=' . $org_id . '&updated=1'));
        exit;
    }

    public function remove_member(): void {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }
        check_admin_referer('myvh_remove_org_member');
        $data = RemoveOrgMemberRequest::from_query($_GET);
        $validation_result = $this->request_validator->validate_remove_member($data);
        if (is_wp_error($validation_result)) {
            wp_redirect(admin_url('admin.php?page=myvh-org-members&organisation_id=' . \intval($data['organisation_id']) . '&error=' . urlencode($validation_result->get_error_message())));
            exit;
        }
        $member_id = $data['id'];
        $org_id    = $data['organisation_id'];
        $result = $this->service->remove_member($member_id);
        if (is_wp_error($result)) {
            $error = '&error=' . urlencode($result->get_error_message());
            if ($data['redirect'] === 'customer' && !empty($data['customer_id'])) {
                wp_redirect(admin_url('admin.php?page=myvh-customers&edit=' . \intval($data['customer_id']) . $error));
                exit;
            }
            wp_redirect(admin_url('admin.php?page=myvh-org-members&organisation_id=' . $org_id . $error));
            exit;
        }
        if ($data['redirect'] === 'customer' && !empty($data['customer_id'])) {
            wp_redirect(admin_url('admin.php?page=myvh-customers&edit=' . \intval($data['customer_id']) . '&updated=1'));
            exit;
        }
        wp_redirect(admin_url('admin.php?page=myvh-org-members&organisation_id=' . $org_id . '&deleted=1'));
        exit;
    }
}

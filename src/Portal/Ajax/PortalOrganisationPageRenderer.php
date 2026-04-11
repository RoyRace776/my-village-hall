<?php
namespace MYVH\Portal\Ajax;

use MYVH\Organisations\OrganisationService;
use MYVH\Organisations\OrganisationTypeService;

class PortalOrganisationPageRenderer {
    public function __construct(
        private OrganisationService $organisation_service,
        private OrganisationTypeService $organisation_type_service
    ) {}

    public function render_organisation_add(array $customer, bool $is_client_admin): void {
        if (empty($customer['Id'])) {
            wp_send_json_error('Customer profile not found', 400);
        }

        $organisation_types = $is_client_admin ? $this->organisation_type_service->get_all() : [];
        $default_organisation_type_id = $this->get_default_organisation_type_id($organisation_types);

        include MYVH_PLUGIN_DIR . 'templates/Portal/organisation-add.php';
    }

    public function render_organisations(array $customer, bool $is_client_admin): void {
        $my_memberships = [];
        $pending_requests = [];
        $manageable_organisations = [];
        $organisation_members = [];
        $organisation_pending_requests = [];
        $organisation_types = $is_client_admin ? $this->organisation_type_service->get_all() : [];
        $requestable_organisations = $this->organisation_service->get_all(true);

        if (!empty($customer['Id'])) {
            $customer_id = (int) $customer['Id'];

            $my_memberships = $this->organisation_service->get_memberships_for_customer($customer_id);
            $pending_requests = $this->organisation_service->get_pending_requests_for_customer($customer_id);
            $manageable_organisations = $this->organisation_service->get_manageable_organisations_for_customer($customer_id);

            $joined_org_ids = array_map(static function($member) {
                return (int) ($member['OrganisationId'] ?? 0);
            }, $my_memberships);

            $pending_org_ids = array_map(static function($request) {
                return (int) ($request['OrganisationId'] ?? 0);
            }, $pending_requests);

            $blocked_org_ids = array_unique(array_merge($joined_org_ids, $pending_org_ids));

            $requestable_organisations = array_values(array_filter(
                $requestable_organisations,
                static function($org) use ($blocked_org_ids) {
                    return !in_array((int) ($org['Id'] ?? 0), $blocked_org_ids, true);
                }
            ));

            foreach ($manageable_organisations as $org) {
                $org_id = (int) $org['Id'];
                $organisation_members[$org_id] = $this->organisation_service->get_members($org_id);
                $organisation_pending_requests[$org_id] = $this->organisation_service->get_pending_requests_for_organisation($org_id, $customer_id);
            }
        }

        include MYVH_PLUGIN_DIR . 'templates/Portal/organisations.php';
    }

    public function render_organisation_types(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $organisation_types = $this->organisation_type_service->get_all();
        include MYVH_PLUGIN_DIR . 'templates/Portal/organisation-types.php';
    }

    public function render_organisation_type_add(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        include MYVH_PLUGIN_DIR . 'templates/Portal/organisation-type-add.php';
    }

    public function render_organisation_type_edit(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $org_type_id = intval($_GET['id'] ?? 0);
        if (!$org_type_id) {
            wp_send_json_error('Invalid organisation type ID', 400);
        }

        $organisation_type = $this->organisation_type_service->get($org_type_id);
        if (!$organisation_type) {
            wp_send_json_error('Organisation type not found', 404);
        }

        include MYVH_PLUGIN_DIR . 'templates/Portal/organisation-type-edit.php';
    }

    private function get_default_organisation_type_id(array $organisation_types): int {
        foreach ($organisation_types as $organisation_type) {
            if (!empty($organisation_type['IsDefault'])) {
                return (int) ($organisation_type['Id'] ?? 0);
            }
        }

        return 0;
    }
}
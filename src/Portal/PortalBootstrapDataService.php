<?php
namespace MYVH\Portal;

use MYVH\Availability\AvailabilityService;
use MYVH\Customers\CustomerService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Resolves all portal bootstrap data for the current request.
 *
 * Encapsulates every database lookup the portal needs at render time
 * (customer, organisations, client-admin status, visible calendar hours).
 * Results are cached for the lifetime of the request so calling get()
 * more than once costs nothing.
 */
class PortalBootstrapDataService {

    private AvailabilityService $availability_service;
    private CustomerService $customer_service;
    private ClientAdminService $client_admin_service;

    /** @var array|null */
    private ?array $cached = null;

    public function __construct(
        AvailabilityService $availability_service,
        CustomerService $customer_service,
        ClientAdminService $client_admin_service
    ) {
        $this->availability_service = $availability_service;
        $this->customer_service     = $customer_service;
        $this->client_admin_service = $client_admin_service;
    }

    /**
     * Resolve and cache all portal bootstrap data for the current request.
     *
     * @return array{
     *   current_customer_id: int,
     *   default_organisation_id: int,
     *   is_client_admin: bool,
     *   has_customer: bool,
     *   accessible_sites: array,
     *   visible_hours: array{start: int, end: int}
     * }
     */
    public function get(): array {

        if ( $this->cached !== null ) {
            return $this->cached;
        }

        $raw_hours = $this->availability_service->get_calendar_visible_hours();

        $current_customer_id     = 0;
        $default_organisation_id = 0;
        $is_client_admin         = false;
        $has_customer            = false;
        $accessible_sites        = [];

        $user_id = get_current_user_id();

        if ( $user_id > 0 ) {
            $customer      = $this->customer_service->get_by_user_id( $user_id );
            $organisations = $this->customer_service->get_organisations_for_user_id( $user_id );
            $is_client_admin  = $this->client_admin_service->can_administer_blog( $user_id, get_current_blog_id() );
            $accessible_sites = $this->client_admin_service->get_accessible_sites_for_user( $user_id );

            $current_customer_id     = ! empty( $customer['Id'] ) ? (int) $customer['Id'] : 0;
            $default_organisation_id = ! empty( $organisations[0]['Id'] ) ? (int) $organisations[0]['Id'] : 0;
            $has_customer            = ! empty( $customer['Id'] );
        }

        $this->cached = [
            'current_customer_id'     => $current_customer_id,
            'default_organisation_id' => $default_organisation_id,
            'is_client_admin'         => $is_client_admin,
            'has_customer'            => $has_customer,
            'accessible_sites'        => $accessible_sites,
            'visible_hours'           => [
                'start' => isset( $raw_hours['start'] ) ? (int) $raw_hours['start'] : 8,
                'end'   => isset( $raw_hours['end'] )   ? (int) $raw_hours['end']   : 22,
            ],
        ];

        return $this->cached;
    }
}

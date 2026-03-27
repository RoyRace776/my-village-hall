<?php
/**
 * Service class for Organisations
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}

class Organisation_Service {

    private $repo;
    private $member_repo;
    private $request_repo;

    public function __construct(
        Organisation_Repository        $repo,
        Organisation_Member_Repository $member_repo,
        Organisation_Member_Request_Repository $request_repo
    ) {
        $this->repo        = $repo;
        $this->member_repo = $member_repo;
        $this->request_repo = $request_repo;
    }

    // ── Organisations ─────────────────────────────────────────────────────────

    public function get_all( bool $active_only = false ): array {
        return $this->repo->get_all( [ 'active_only' => $active_only ] );
    }

    public function get_all_with_type( bool $active_only = false ): array {
        return $this->repo->get_all_with_type( [ 'active_only' => $active_only ] );
    }

    public function get( int $id ): ?array {
        return $this->repo->get_by_id( $id );
    }

    public function get_default(): ?array {
        return $this->repo->get_default();
    }

    public function save( array $data ): int|bool|WP_Error {
        if ( empty( $data['name'] ) ) {
            return new WP_Error( 'validation', __( 'Organisation name is required', 'my-village-hall' ) );
        }

        if ( empty( $data['contact_email'] ) ) {
            return new WP_Error( 'validation', __( 'Contact email is required', 'my-village-hall' ) );
        }

        if ( ! is_email( $data['contact_email'] ) ) {
            return new WP_Error( 'validation', __( 'Contact email must be a valid email address', 'my-village-hall' ) );
        }

        if ( empty( $data['contact_phone'] ) ) {
            return new WP_Error( 'validation', __( 'Contact phone is required', 'my-village-hall' ) );
        }

        $is_new = empty( $data['organisation_id'] );
        $requested_default = isset( $data['is_default'] ) ? 1 : 0;

        if ( $is_new && ( $this->repo->count_all() === 0 || !$this->repo->has_default() ) ) {
            $requested_default = 1;
        }

        $invoice_organisation_bookings = !empty( $data['invoice_organisation_bookings'] ) ? 1 : 0;

        $record = [
            'Name'               => sanitize_text_field( $data['name'] ),
            'OrganisationTypeId' => !empty( $data['organisation_type_id'] ) ? intval( $data['organisation_type_id'] ) : null,
            'ContactEmail'       => sanitize_email( $data['contact_email'] ),
            'ContactPhone'       => sanitize_text_field( $data['contact_phone'] ),
            'WebsiteUrl'         => !empty( $data['website_url'] ) ? esc_url_raw( $data['website_url'] ) : null,
            'InvoiceOrganisationBookings' => $invoice_organisation_bookings,
            'BillingContactName' => $invoice_organisation_bookings && !empty( $data['billing_contact_name'] ) ? sanitize_text_field( $data['billing_contact_name'] ) : null,
            'BillingEmail'       => $invoice_organisation_bookings && !empty( $data['billing_email'] ) ? sanitize_email( $data['billing_email'] ) : null,
            'BillingAddressLine1'=> $invoice_organisation_bookings && !empty( $data['billing_address_line1'] ) ? sanitize_text_field( $data['billing_address_line1'] ) : null,
            'BillingAddressLine2'=> $invoice_organisation_bookings && !empty( $data['billing_address_line2'] ) ? sanitize_text_field( $data['billing_address_line2'] ) : null,
            'BillingTownCity'    => $invoice_organisation_bookings && !empty( $data['billing_town_city'] ) ? sanitize_text_field( $data['billing_town_city'] ) : null,
            'BillingPostcode'    => $invoice_organisation_bookings && !empty( $data['billing_postcode'] ) ? sanitize_text_field( $data['billing_postcode'] ) : null,
            'BillingReference'   => $invoice_organisation_bookings && !empty( $data['billing_reference'] ) ? sanitize_text_field( $data['billing_reference'] ) : null,
            'IsActive'           => isset( $data['is_active'] ) ? 1 : 0,
            'IsDefault'          => $requested_default,
            'DefaultPublic'      => ( isset( $data['default_public'] ) && intval( $data['default_public'] ) === 1 ) ? 1 : 0,
        ];

        if ( !empty( $data['organisation_id'] ) ) {
            $organisation_id = intval( $data['organisation_id'] );

            $updated = $this->repo->update( $record, [ 'Id' => $organisation_id ] );

            if ( !$updated ) {
                return false;
            }

            if ( $requested_default ) {
                $this->repo->clear_default_except( $organisation_id );
            } elseif ( !$this->repo->has_default() ) {
                $this->repo->update( [ 'IsDefault' => 1 ], [ 'Id' => $organisation_id ] );
            }

            return true;
        }

        $organisation_id = $this->repo->create( $record );

        if ( !$organisation_id ) {
            return false;
        }

        if ( $requested_default ) {
            $this->repo->clear_default_except( intval( $organisation_id ) );
        }

        return $organisation_id;
    }

    public function delete( int $id ) {
        return $this->repo->delete( $id );
    }

    // ── Members ───────────────────────────────────────────────────────────────

    public function get_members( int $org_id ): array {
        return $this->member_repo->get_by_organisation( $org_id );
    }

    public function get_memberships_for_customer( int $customer_id ): array {
        return $this->member_repo->get_by_user( $customer_id );
    }

    public function get_manageable_organisations_for_customer( int $customer_id ): array {
        return $this->member_repo->get_admin_organisations_for_customer( $customer_id );
    }

    public function get_pending_requests_for_admin_customer( int $customer_id ): array {
        return $this->request_repo->get_pending_for_admin_customer( $customer_id );
    }

    public function get_pending_requests_for_organisation( int $org_id, int $acting_customer_id ): array {
        if ( !$this->member_repo->is_customer_admin( $org_id, $acting_customer_id ) ) {
            return [];
        }

        return $this->request_repo->get_pending_by_organisation( $org_id );
    }

    public function get_pending_requests_for_customer( int $customer_id ): array {
        return $this->request_repo->get_pending_by_customer( $customer_id );
    }

    public function is_customer_admin_for_organisation( int $org_id, int $customer_id ): bool {
        return $this->member_repo->is_customer_admin( $org_id, $customer_id );
    }

    public function add_member( int $org_id, int $customer_id, bool $is_admin = false ) {
        if ( $this->member_repo->exists( $org_id, $customer_id ) ) {
            return new WP_Error( 'duplicate', __( 'User is already a member of this organisation', 'my-village-hall' ) );
        }

        $admin_count = $this->member_repo->count_admins_for_organisation( $org_id );
        if ( $admin_count === 0 ) {
            $is_admin = true;
        }

        return $this->member_repo->create( [
            'OrganisationId' => $org_id,
            'CustomerId' => $customer_id,
            'IsOrganisationAdmin' => $is_admin ? 1 : 0,
        ] );
    }

    public function remove_member( int $member_id ) {
        $member = $this->member_repo->get_by_id( $member_id );

        if ( empty( $member['Id'] ) ) {
            return new WP_Error( 'not_found', __( 'Organisation member not found', 'my-village-hall' ) );
        }

        $is_admin = !empty( $member['IsOrganisationAdmin'] );

        if ( $is_admin ) {
            $admin_count = $this->member_repo->count_admins_for_organisation( (int) $member['OrganisationId'] );
            if ( $admin_count <= 1 ) {
                return new WP_Error( 'last_admin', __( 'Each organisation must have at least one admin', 'my-village-hall' ) );
            }
        }

        return $this->member_repo->delete( $member_id );
    }

    public function update_member_admin_status( int $member_id, bool $is_admin ) {
        $member = $this->member_repo->get_by_id( $member_id );

        if ( empty( $member['Id'] ) ) {
            return new WP_Error( 'not_found', __( 'Organisation member not found', 'my-village-hall' ) );
        }

        $currently_admin = !empty( $member['IsOrganisationAdmin'] );

        if ( !$is_admin && $currently_admin ) {
            $admin_count = $this->member_repo->count_admins_for_organisation( (int) $member['OrganisationId'] );
            if ( $admin_count <= 1 ) {
                return new WP_Error( 'last_admin', __( 'Each organisation must have at least one admin', 'my-village-hall' ) );
            }
        }

        return $this->member_repo->update_admin_status( $member_id, $is_admin );
    }

    public function create_membership_request( int $org_id, int $customer_id, string $request_message = '' ) {
        if ( !$this->repo->get_by_id( $org_id ) ) {
            return new WP_Error( 'not_found', __( 'Organisation not found', 'my-village-hall' ) );
        }

        if ( $this->member_repo->exists( $org_id, $customer_id ) ) {
            return new WP_Error( 'duplicate', __( 'You are already a member of this organisation', 'my-village-hall' ) );
        }

        if ( $this->request_repo->get_pending_request( $org_id, $customer_id ) ) {
            return new WP_Error( 'duplicate_request', __( 'A pending request already exists for this organisation', 'my-village-hall' ) );
        }

        return $this->request_repo->create( [
            'OrganisationId' => $org_id,
            'CustomerId' => $customer_id,
            'Status' => 'pending',
            'RequestMessage' => sanitize_text_field( $request_message ),
        ] );
    }

    public function approve_membership_request( int $request_id, int $acting_customer_id ) {
        $request = $this->request_repo->get_by_id( $request_id );

        if ( empty( $request['Id'] ) || $request['Status'] !== 'pending' ) {
            return new WP_Error( 'not_found', __( 'Pending request not found', 'my-village-hall' ) );
        }

        $org_id = (int) $request['OrganisationId'];

        if ( !$this->member_repo->is_customer_admin( $org_id, $acting_customer_id ) ) {
            return new WP_Error( 'forbidden', __( 'Only organisation admins can approve requests', 'my-village-hall' ) );
        }

        if ( !$this->member_repo->exists( $org_id, (int) $request['CustomerId'] ) ) {
            $add_result = $this->add_member( $org_id, (int) $request['CustomerId'], false );
            if ( is_wp_error( $add_result ) ) {
                return $add_result;
            }
        }

        $updated = $this->request_repo->update( $request_id, [
            'Status' => 'approved',
            'ReviewedByCustomerId' => $acting_customer_id,
            'ReviewedAt' => current_time( 'mysql' ),
        ] );

        if ( !$updated ) {
            return new WP_Error( 'database', __( 'Failed to approve membership request', 'my-village-hall' ) );
        }

        return true;
    }

    public function reject_membership_request( int $request_id, int $acting_customer_id ) {
        $request = $this->request_repo->get_by_id( $request_id );

        if ( empty( $request['Id'] ) || $request['Status'] !== 'pending' ) {
            return new WP_Error( 'not_found', __( 'Pending request not found', 'my-village-hall' ) );
        }

        $org_id = (int) $request['OrganisationId'];

        if ( !$this->member_repo->is_customer_admin( $org_id, $acting_customer_id ) ) {
            return new WP_Error( 'forbidden', __( 'Only organisation admins can reject requests', 'my-village-hall' ) );
        }

        $updated = $this->request_repo->update( $request_id, [
            'Status' => 'rejected',
            'ReviewedByCustomerId' => $acting_customer_id,
            'ReviewedAt' => current_time( 'mysql' ),
        ] );

        if ( !$updated ) {
            return new WP_Error( 'database', __( 'Failed to reject membership request', 'my-village-hall' ) );
        }

        return true;
    }

    public function add_member_by_admin( int $org_id, int $acting_customer_id, int $customer_id, bool $is_admin = false ) {
        if ( !$this->member_repo->is_customer_admin( $org_id, $acting_customer_id ) ) {
            return new WP_Error( 'forbidden', __( 'Only organisation admins can add members', 'my-village-hall' ) );
        }

        return $this->add_member( $org_id, $customer_id, $is_admin );
    }

    public function remove_member_by_admin( int $member_id, int $acting_customer_id ) {
        $member = $this->member_repo->get_by_id( $member_id );

        if ( empty( $member['Id'] ) ) {
            return new WP_Error( 'not_found', __( 'Organisation member not found', 'my-village-hall' ) );
        }

        if ( !$this->member_repo->is_customer_admin( (int) $member['OrganisationId'], $acting_customer_id ) ) {
            return new WP_Error( 'forbidden', __( 'Only organisation admins can remove members', 'my-village-hall' ) );
        }

        return $this->remove_member( $member_id );
    }

    public function set_member_admin_status_by_admin( int $member_id, int $acting_customer_id, bool $is_admin ) {
        $member = $this->member_repo->get_by_id( $member_id );

        if ( empty( $member['Id'] ) ) {
            return new WP_Error( 'not_found', __( 'Organisation member not found', 'my-village-hall' ) );
        }

        if ( !$this->member_repo->is_customer_admin( (int) $member['OrganisationId'], $acting_customer_id ) ) {
            return new WP_Error( 'forbidden', __( 'Only organisation admins can change member status', 'my-village-hall' ) );
        }

        return $this->update_member_admin_status( $member_id, $is_admin );
    }

    public function update_billing_details_by_admin( int $org_id, int $acting_customer_id, array $data ) {
        if ( !$this->member_repo->is_customer_admin( $org_id, $acting_customer_id ) ) {
            return new WP_Error( 'forbidden', __( 'Only organisation admins can update billing details', 'my-village-hall' ) );
        }

        $organisation = $this->repo->get_by_id( $org_id );

        if ( empty( $organisation['Id'] ) ) {
            return new WP_Error( 'not_found', __( 'Organisation not found', 'my-village-hall' ) );
        }

        $invoice_organisation_bookings = !empty( $data['invoice_organisation_bookings'] ) ? 1 : 0;
        $billing_email = sanitize_email( $data['billing_email'] ?? '' );
        if ( $invoice_organisation_bookings && !empty( $data['billing_email'] ) && !is_email( $billing_email ) ) {
            return new WP_Error( 'validation', __( 'Billing email must be a valid email address', 'my-village-hall' ) );
        }

        $updated = $this->repo->update( [
            'InvoiceOrganisationBookings' => $invoice_organisation_bookings,
            'BillingContactName'  => $invoice_organisation_bookings && !empty( $data['billing_contact_name'] ) ? sanitize_text_field( $data['billing_contact_name'] ) : null,
            'BillingEmail'        => $invoice_organisation_bookings && !empty( $data['billing_email'] ) ? $billing_email : null,
            'BillingAddressLine1' => $invoice_organisation_bookings && !empty( $data['billing_address_line1'] ) ? sanitize_text_field( $data['billing_address_line1'] ) : null,
            'BillingAddressLine2' => $invoice_organisation_bookings && !empty( $data['billing_address_line2'] ) ? sanitize_text_field( $data['billing_address_line2'] ) : null,
            'BillingTownCity'     => $invoice_organisation_bookings && !empty( $data['billing_town_city'] ) ? sanitize_text_field( $data['billing_town_city'] ) : null,
            'BillingPostcode'     => $invoice_organisation_bookings && !empty( $data['billing_postcode'] ) ? sanitize_text_field( $data['billing_postcode'] ) : null,
            'BillingReference'    => $invoice_organisation_bookings && !empty( $data['billing_reference'] ) ? sanitize_text_field( $data['billing_reference'] ) : null,
        ], [ 'Id' => $org_id ] );

        if ( !$updated ) {
            return new WP_Error( 'database', __( 'Failed to update billing details', 'my-village-hall' ) );
        }

        return true;
    }
}

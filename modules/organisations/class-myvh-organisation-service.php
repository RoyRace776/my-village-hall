<?php
/**
 * Service class for Organisations
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}

class MYVH_Organisation_Service {

    private $repo;
    private $member_repo;

    public function __construct(
        MYVH_Organisation_Repository        $repo,
        MYVH_Organisation_Member_Repository $member_repo
    ) {
        $this->repo        = $repo;
        $this->member_repo = $member_repo;
    }

    // ── Organisations ─────────────────────────────────────────────────────────

    public function get_all( bool $active_only = false ): array {
        return $this->repo->get_all( [ 'active_only' => $active_only ] );
    }

    public function get_all_with_type( bool $active_only = false ): array {
        return $this->repo->get_all_with_type( [ 'active_only' => $active_only ] );
    }

    public function get( int $id ) {
        return $this->repo->get_by_id( $id );
    }

    public function get_default() {
        return $this->repo->get_default();
    }

    public function save( array $data ) {
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

        $record = [
            'Name'               => sanitize_text_field( $data['name'] ),
            'OrganisationTypeId' => !empty( $data['organisation_type_id'] ) ? intval( $data['organisation_type_id'] ) : null,
            'ContactEmail'       => sanitize_email( $data['contact_email'] ),
            'ContactPhone'       => sanitize_text_field( $data['contact_phone'] ),
            'WebsiteUrl'         => !empty( $data['website_url'] ) ? esc_url_raw( $data['website_url'] ) : null,
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

    public function add_member( int $org_id, int $user_id ) {
        if ( $this->member_repo->exists( $org_id, $user_id ) ) {
            return new WP_Error( 'duplicate', __( 'User is already a member of this organisation', 'my-village-hall' ) );
        }

        return $this->member_repo->create( [
            'OrganisationId' => $org_id,
            'CustomerId'         => $user_id,
        ] );
    }

    public function remove_member( int $member_id ) {
        return $this->member_repo->delete( $member_id );
    }
}

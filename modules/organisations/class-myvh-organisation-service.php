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

    public function save( array $data ) {
        if ( empty( $data['name'] ) ) {
            return new WP_Error( 'validation', __( 'Organisation name is required', 'my-village-hall' ) );
        }

        $record = [
            'Name'               => sanitize_text_field( $data['name'] ),
            'OrganisationTypeId' => !empty( $data['organisation_type_id'] ) ? intval( $data['organisation_type_id'] ) : null,
            'ContactEmail'       => sanitize_email( $data['contact_email'] ?? '' ) ?: null,
            'ContactPhone'       => sanitize_text_field( $data['contact_phone'] ?? '' ) ?: null,
            'IsActive'           => isset( $data['is_active'] ) ? 1 : 0,
        ];

        if ( !empty( $data['organisation_id'] ) ) {
            return $this->repo->update( $record, [ 'Id' => intval( $data['organisation_id'] ) ] );
        }

        return $this->repo->create( $record );
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

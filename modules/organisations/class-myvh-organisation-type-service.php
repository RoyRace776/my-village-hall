<?php
/**
 * Service class for Organisation Types
 *
 * @package MYVH
 */

if (!defined('ABSPATH')) {
    exit;
}

class Organisation_Type_Service {

    private $repo;

    public function __construct( Organisation_Type_Repository $repo ) {
        $this->repo = $repo;
    }

    public function get_all(): array {
        return $this->repo->get_all();
    }

    public function get( int $id ): ?array {
        return $this->repo->get_by_id( $id );
    }

    public function save( array $data ): int|bool|WP_Error {
        if ( empty( $data['name'] ) ) {
            return new WP_Error( 'validation', __( 'Organisation type name is required', 'my-village-hall' ) );
        }

        $record = [
            'Name'        => sanitize_text_field( $data['name'] ),
            'Description' => sanitize_textarea_field( $data['description'] ?? '' ),
        ];

        if ( !empty( $data['org_type_id'] ) ) {
            return $this->repo->update( $record, [ 'Id' => intval( $data['org_type_id'] ) ] );
        }

        return $this->repo->create( $record );
    }

    public function delete( int $id ): bool|WP_Error {
        return $this->repo->delete( $id );
    }
}

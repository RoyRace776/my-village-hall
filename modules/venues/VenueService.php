<?php
if (!defined('ABSPATH')) exit;

class VenueService {

    private $repo;

    public function __construct(VenueRepository $repo) {
        $this->repo = $repo;
    }

    public function get_all(): array {
        return $this->repo->get_all();
    }

    public function get($id): ?array {
        return $this->repo->get_by_id($id);
    }

    public function save($data): int|WP_Error {

        if (empty($data['name'])) {
            return new WP_Error('validation', __('Venue name is required', 'my-village-hall'));
        }

        $record = [
            'Name'         => sanitize_text_field($data['name']),
            'ShortName'    => sanitize_text_field($data['short_name']),
            'PostCode'     => sanitize_text_field($data['post_code']),
            'AddressLine1' => sanitize_text_field($data['address_line1']),
            'OpeningTime'  => sanitize_text_field($data['opening_time']),
            'ClosingTime'  => sanitize_text_field($data['closing_time']),
        ];

        if (!empty($data['venue_id'])) {
            return $this->repo->update($record, ['Id' => intval($data['venue_id'])]);
        }

        return $this->repo->create($record);
    }

    public function delete($id): bool|WP_Error {
        return $this->repo->delete_by_id($id);
    }
}
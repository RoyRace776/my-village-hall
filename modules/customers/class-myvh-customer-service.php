<?php
if (!defined('ABSPATH')) exit;

class MYVH_Customer_Service {

    private $repo;
    private $booking_repo;

    public function __construct(MYVH_Customer_Repository $repo,
                                MYVH_Booking_Repository $booking_repo) {
        $this->repo         = $repo;
        $this->booking_repo = $booking_repo;
    }

    public function get_all($args = []) {
        return $this->repo->get_all($args);
    }

    public function get($id) {
        return $this->repo->get_by_id($id);
    }

    public function get_with_organisations() {
        return $this->repo->get_all_with_organisations();
    }

    public function get_organisations_for_customer( int $customer_id ): array {
        return $this->repo->get_organisations_for_customer( $customer_id );
    }

    public function get_by_email($email) {
        return $this->repo->get_by_email($email);
    }

    public function get_by_user_id($user_id) {
        return $this->repo->get_by_user_id($user_id);
    }

    public function search($search_term) {
        return $this->repo->search($search_term);
    }

    public function save($data) {

        if (empty($data['name'])) {
            return new WP_Error('validation', __('Customer name is required', 'my-village-hall'));
        }

        if (empty($data['email'])) {
            return new WP_Error('validation', __('Email is required', 'my-village-hall'));
        }

        if (!is_email($data['email'])) {
            return new WP_Error('validation', __('Valid email address is required', 'my-village-hall'));
        }

        // Check for duplicate email (excluding current customer if editing)
        $existing = $this->repo->get_by_email($data['email']);
        if ($existing && (empty($data['customer_id']) || $existing['Id'] != $data['customer_id'])) {
            return new WP_Error('validation', __('A customer with this email already exists', 'my-village-hall'));
        }

        $record = [
            'Name'           => sanitize_text_field($data['name']),
            'Email'          => sanitize_email($data['email']),
            'PhoneNumber'    => sanitize_text_field($data['phone_number'] ?? ''),
            'PostCode'       => sanitize_text_field($data['post_code'] ?? ''),
            'AddressLine1'   => sanitize_text_field($data['address_line1'] ?? ''),
            'EmailVerified'  => isset($data['email_verified']) ? 1 : 0,
        ];

        // Link to WordPress user if CustomerId is provided
        if (!empty($data['user_id'])) {
            $record['CustomerId'] = intval($data['user_id']);
        }

        if (!empty($data['customer_id'])) {
            $result = $this->repo->update($record, ['Id' => intval($data['customer_id'])]);
            if ($result === false) {
                return new WP_Error('database', __('Failed to update customer', 'my-village-hall'));
            }
            return intval($data['customer_id']);
        }

        $result = $this->repo->create($record);
        if ($result === false) {
            return new WP_Error('database', __('Failed to create customer', 'my-village-hall'));
        }

        return $result;
    }

    public function delete($id) {
        if ($this->booking_repo->count_by_customer($id) > 0) {
            return new WP_Error('validation', __('Cannot delete customer with existing bookings', 'my-village-hall'));
        }

        return $this->repo->delete($id);
    }
}

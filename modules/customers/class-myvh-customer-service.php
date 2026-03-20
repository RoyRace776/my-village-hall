<?php
if (!defined('ABSPATH')) exit;

class MYVH_Customer_Service {

    private $repo;
    private $booking_repo;
    private $organisation_repo;
    private $organisation_member_repo;

    public function __construct(MYVH_Customer_Repository $repo,
                                MYVH_Booking_Repository $booking_repo,
                                MYVH_Organisation_Repository $organisation_repo,
                                MYVH_Organisation_Member_Repository $organisation_member_repo) {
        $this->repo         = $repo;
        $this->booking_repo = $booking_repo;
        $this->organisation_repo = $organisation_repo;
        $this->organisation_member_repo = $organisation_member_repo;
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

    public function get_organisations_for_user_id( int $user_id ): array {
        return $this->repo->get_organisations_for_user_id( $user_id );
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

        // Link to WordPress user when provided
        if (!empty($data['user_id'])) {
            $record['WPUserId'] = intval($data['user_id']);
        }

        if (!empty($data['customer_id'])) {
            $result = $this->repo->update($record, ['Id' => intval($data['customer_id'])]);
            if ($result === false) {
                return new WP_Error('database', __('Failed to update customer', 'my-village-hall'));
            }

            if (!empty($record['WPUserId'])) {
                wp_update_user([
                    'ID' => (int) $record['WPUserId'],
                    'display_name' => $record['Name'],
                    'user_email' => $record['Email'],
                ]);
            }

            return intval($data['customer_id']);
        }

        $result = $this->create_customer($record);

        // $result = $this->repo->create($record);
        // if ($result === false) {
        //     return new WP_Error('database', __('Failed to create customer', 'my-village-hall'));
        // }

        return $result;
    }

    public function create_customer(array $data): int
    {

        $email = $data['Email'];
        $user = get_user_by('Email', $email);

        if (!$user) {

            $user_id = wp_insert_user([
                'user_login' => $email,
                'user_email' => $email,
                'user_pass'  => wp_generate_password(),
                'role'       => 'myvh_customer'
            ]);

            if (is_wp_error($user_id)) {
                throw new Exception($user_id->get_error_message());
            }

            $created_user = true;

        } else {

            $user_id = $user->ID;
            $created_user = false;

        }

        try {

            if (is_multisite()) {
                add_user_to_blog(get_current_blog_id(), $user_id, 'myvh_customer');
            }

            $data['WPUserId'] = $user_id;

            $customer_id = $this->repo->create($data);

            if (!$customer_id) {
                throw new Exception(__('Failed to create customer', 'my-village-hall'));
            }

            $this->add_customer_to_default_organisation($customer_id);

            return $customer_id;

        } catch (Exception $e) {

            if ($created_user) {
                wp_delete_user($user_id);
            }

            throw $e;
        }
    }

    private function add_customer_to_default_organisation(int $customer_id): void
    {
        $default_organisation = $this->organisation_repo->get_default();

        if (empty($default_organisation['Id'])) {
            return;
        }

        $organisation_id = intval($default_organisation['Id']);

        if ($this->organisation_member_repo->exists($organisation_id, $customer_id)) {
            return;
        }

        $result = $this->organisation_member_repo->create([
            'OrganisationId' => $organisation_id,
            'CustomerId' => $customer_id,
        ]);

        if ($result === false) {
            throw new Exception(__('Failed to add customer to the default organisation', 'my-village-hall'));
        }
    }


    public function delete($id) {
        if ($this->booking_repo->count_by_customer($id) > 0) {
            return new WP_Error('validation', __('Cannot delete customer with existing bookings', 'my-village-hall'));
        }

        return $this->repo->delete($id);
    }
}

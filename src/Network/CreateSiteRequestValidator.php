<?php
namespace MYVH\Network;

use MYVH\Core\Support\RequestValidatorBase;
use MYVH\Login\PasswordValidator;

if (!defined('ABSPATH')) {
    exit;
}

class CreateSiteRequestValidator extends RequestValidatorBase {
    private const SUBDOMAIN_PATTERN = '/^[a-z0-9-]{3,30}$/';

    public function __construct(
        private PasswordValidator $password_validator
    ) {}

    public function validate(array $data): bool|\WP_Error {
        $required = $this->require_field($data, 'site_name', __('Site name is required', 'my-village-hall'));
        if (is_wp_error($required)) {
            return $required;
        }

        $required = $this->require_field($data, 'subdomain', __('Site path is required', 'my-village-hall'));
        if (is_wp_error($required)) {
            return $required;
        }

        $email = $this->require_email(
            $data,
            'admin_email',
            __('Admin email is required', 'my-village-hall'),
            __('A valid admin email is required', 'my-village-hall')
        );
        if (is_wp_error($email)) {
            return $email;
        }

        $required = $this->require_field($data, 'admin_first_name', __('Admin first name is required', 'my-village-hall'));
        if (is_wp_error($required)) {
            return $required;
        }

        $required = $this->require_field($data, 'admin_last_name', __('Admin last name is required', 'my-village-hall'));
        if (is_wp_error($required)) {
            return $required;
        }

        $required = $this->require_field($data, 'admin_password', __('Admin password is required', 'my-village-hall'));
        if (is_wp_error($required)) {
            return $required;
        }

        $required = $this->require_field($data, 'admin_password_confirm', __('Please confirm the admin password', 'my-village-hall'));
        if (is_wp_error($required)) {
            return $required;
        }

        if ((string) $data['admin_password'] !== (string) $data['admin_password_confirm']) {
            return $this->validation_error(__('Admin password and confirmation do not match.', 'my-village-hall'));
        }

        $password_error = $this->password_validator->validate((string) $data['admin_password']);
        if ($password_error !== null) {
            return $this->validation_error(__($password_error, 'my-village-hall'));
        }

        $subdomain = (string) $data['subdomain'];
        if (preg_match(self::SUBDOMAIN_PATTERN, $subdomain) !== 1) {
            return $this->validation_error(__('Site path must be 3-30 characters and contain only lowercase letters, numbers, or hyphens.', 'my-village-hall'));
        }

        if (str_starts_with($subdomain, '-') || str_ends_with($subdomain, '-')) {
            return $this->validation_error(__('Site path cannot begin or end with a hyphen.', 'my-village-hall'));
        }

        if (!is_multisite()) {
            return $this->validation_error(__('This feature requires WordPress multisite.', 'my-village-hall'));
        }

        $setup_validation = $this->validate_setup_payload($data['setup_payload'] ?? []);
        if (is_wp_error($setup_validation)) {
            return $setup_validation;
        }

        $network = get_network();
        if (!$network) {
            return $this->validation_error(__('Unable to resolve network settings.', 'my-village-hall'));
        }

        if (is_subdomain_install()) {
            $domain = $subdomain . '.' . preg_replace('/^www\./', '', (string) $network->domain);
            if (domain_exists($domain, '/', (int) $network->id)) {
                return $this->validation_error(__('That subdomain is already in use.', 'my-village-hall'));
            }
        } else {
            $path = '/' . $subdomain . '/';
            if (domain_exists((string) $network->domain, $path, (int) $network->id)) {
                return $this->validation_error(__('That path is already in use.', 'my-village-hall'));
            }
        }

        return true;
    }

    private function validate_setup_payload(mixed $setup): bool|\WP_Error {
        if ($setup === null || $setup === [] || $setup === '') {
            return true;
        }

        if (!is_array($setup)) {
            return $this->validation_error(__('Setup payload is invalid.', 'my-village-hall'));
        }

        $venue = is_array($setup['venue'] ?? null) ? $setup['venue'] : [];
        if (trim((string) ($venue['name'] ?? '')) === '') {
            return $this->validation_error(__('Venue name is required.', 'my-village-hall'));
        }

        $venue_email = trim((string) ($venue['email'] ?? $venue['contact_email'] ?? ''));
        if ($venue_email === '') {
            return $this->validation_error(__('Venue email is required.', 'my-village-hall'));
        }

        if (!is_email($venue_email)) {
            return $this->validation_error(__('A valid venue email is required.', 'my-village-hall'));
        }

        $rooms = is_array($setup['rooms'] ?? null) ? array_values($setup['rooms']) : [];
        if ($rooms === [] || count($rooms) < 1) {
            return $this->validation_error(__('At least one room is required.', 'my-village-hall'));
        }

        if (count($rooms) > 3) {
            return $this->validation_error(__('A maximum of three rooms is allowed.', 'my-village-hall'));
        }

        $room_refs = [];
        foreach ($rooms as $index => $room) {
            if (!is_array($room) || trim((string) ($room['name'] ?? '')) === '') {
                return $this->validation_error(__('Each room must have a name.', 'my-village-hall'));
            }

            if (isset($room['capacity']) && $room['capacity'] !== '' && !is_numeric($room['capacity'])) {
                return $this->validation_error(__('Room capacity must be numeric when provided.', 'my-village-hall'));
            }

            $room_refs[] = $room['key'] ?? $room['id'] ?? $index;
        }

        $pricing_rows = is_array($setup['pricing'] ?? null) ? $setup['pricing'] : [];
        if ($pricing_rows === []) {
            return $this->validation_error(__('Hourly pricing is required for every room.', 'my-village-hall'));
        }

        $pricing_refs = [];
        foreach ($pricing_rows as $index => $pricing) {
            if (!is_array($pricing)) {
                continue;
            }

            $room_ref = $pricing['room_key'] ?? $pricing['room_id'] ?? $pricing['room_index'] ?? $index;
            $hourly_rate = isset($pricing['hourly_rate']) ? $pricing['hourly_rate'] : ($pricing['rate'] ?? null);

            if ($hourly_rate === null || !is_numeric($hourly_rate) || (float) $hourly_rate <= 0) {
                return $this->validation_error(__('Hourly rate must be greater than zero for every room.', 'my-village-hall'));
            }

            $pricing_refs[$room_ref] = true;
        }

        foreach ($room_refs as $room_ref) {
            if (!isset($pricing_refs[$room_ref])) {
                return $this->validation_error(__('Hourly pricing is required for every room.', 'my-village-hall'));
            }
        }

        $addons = is_array($setup['addons'] ?? null) ? $setup['addons'] : [];
        foreach ($addons as $addon) {
            if (!is_array($addon)) {
                continue;
            }

            $has_content = trim((string) ($addon['name'] ?? '')) !== ''
                || ($addon['price'] ?? '') !== ''
                || trim((string) ($addon['description'] ?? '')) !== '';

            if (!$has_content) {
                continue;
            }

            if (trim((string) ($addon['name'] ?? '')) === '') {
                return $this->validation_error(__('Add-on name is required when an add-on is provided.', 'my-village-hall'));
            }

            if (($addon['price'] ?? '') !== '' && !is_numeric($addon['price'])) {
                return $this->validation_error(__('Add-on price must be numeric.', 'my-village-hall'));
            }
        }

        return true;
    }
}

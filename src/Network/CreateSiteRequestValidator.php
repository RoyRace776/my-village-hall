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

    public function validate(array $data): true|\WP_Error {
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
}

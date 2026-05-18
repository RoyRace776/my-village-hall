<?php
namespace MYVH\Email;

use MYVH\Login\PasswordResetHandler;

/**
 * PasswordSetupEmailService: Helper to send password setup emails for new customers
 */
class PasswordSetupEmailService {
    protected $email_service;
    protected $password_reset_handler;

    public function __construct( mixed $email_service = null, mixed $password_reset_handler = null) {
        $this->email_service = $email_service ?: new EmailService();
        $this->password_reset_handler = $password_reset_handler ?: new PasswordResetHandler();
    }

    /**
     * Send a password setup email to a customer
     *
     * @param int $customer_id The customer ID
     * @param int $user_id The WordPress user ID associated with the customer
     * @return bool True if email sent successfully, false otherwise
     */
    public function send_password_setup_email( mixed $customer_id, mixed $user_id) {
        if (!$user_id || !get_userdata($user_id)) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user || !$user->user_email) {
            return false;
        }

        // Generate reset token
        $token = $this->generate_token($user_id);

        // Build reset URL
        $reset_base = $this->password_reset_handler->get_reset_page_url();
        $reset_url = add_query_arg([
            'myvh_reset' => 1,
            'uid' => $user_id,
            'token' => $token
        ], $reset_base);

        // Get branding
        $branding = $this->email_service->get_branding();

        // Send email
        return $this->email_service->send([
            'to' => $user->user_email,
            'subject' => 'Set up your password',
            'template' => 'password-setup',
            'template_vars' => [
                'reset_url' => $reset_url,
                'logo_url' => $branding['logo_url'],
                'site_name' => $branding['site_name'],
                'site_url' => $branding['site_url'],
            ],
        ]);
    }

    /**
     * Generate a secure, single-use token for password reset (1 hour TTL).
     * Uses hex encoding so the value is always URL-safe and survives
     * sanitize_text_field without modification.
     */
    protected function generate_token($user_id) {
        $token = bin2hex(random_bytes(16)); // 32-char lowercase hex string
        set_transient('myvh_reset_token_' . $user_id, $token, 60 * 60); // 1 hour
        return $token;
    }
}

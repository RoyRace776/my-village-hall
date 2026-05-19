<?php
namespace MYVH\Login;

use MYVH\Customers\CustomerRepository;
use MYVH\Email\EmailService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class CustomerEmailVerificationService {
    private const TOKEN_PREFIX = 'myvh_email_verify_token_';
    private const DEFAULT_TOKEN_TTL = 86400;
    private const HOUR_IN_SECONDS = 3600;

    private EmailService $email_service;
    private CustomerRepository $customer_repository;
    private int $token_ttl;
    private LoggerInterface $logger;

    public function __construct(
        ?EmailService $email_service = null,
        ?CustomerRepository $customer_repository = null,
        ?int $token_ttl = null,
        ?LoggerInterface $logger = null
    ) {
        $this->email_service = $email_service ?: new EmailService();
        $this->customer_repository = $customer_repository ?: new CustomerRepository($GLOBALS['wpdb'], new NullLogger());
        $this->token_ttl = $token_ttl ?? self::DEFAULT_TOKEN_TTL;
        $this->logger = $logger ?? new NullLogger();
    }

    public function send_verification_email(int $customer_id, int $user_id, string $email, string $name): bool {
        if ($customer_id <= 0 || $user_id <= 0 || $email === '' || !is_email($email)) {
            return false;
        }

        $token = $this->generate_token($user_id);
        $verification_url = $this->build_verification_url($user_id, $token);
        $branding = $this->email_service->get_branding();

        return (bool) $this->email_service->send([
            'to' => $email,
            'subject' => 'Verify your email address',
            'template' => 'customer-email-verification',
            'template_vars' => [
                'customer_name' => $name,
                'verification_url' => $verification_url,
                'verification_ttl_hours' => (string) floor($this->token_ttl / self::HOUR_IN_SECONDS),
                'logo_url' => (string) ($branding['logo_url'] ?? ''),
                'site_name' => (string) ($branding['site_name'] ?? ''),
                'site_url' => (string) ($branding['site_url'] ?? ''),
            ],
        ]);
    }

    public function verify_token(int $user_id, string $token): bool {
        if ($user_id <= 0) {
            return false;
        }

        $token = preg_replace('/[^a-zA-Z0-9]/', '', $token);
        if ($token === null || $token === '') {
            return false;
        }

        $saved_token = get_transient(self::TOKEN_PREFIX . $user_id);
        if (!is_string($saved_token) || $saved_token === '' || !hash_equals($saved_token, $token)) {
            return false;
        }

        $customer = $this->customer_repository->get_by_user_id($user_id);
        if (empty($customer['Id'])) {
            return false;
        }

        $updated = $this->customer_repository->update([
            'EmailVerified' => 1,
        ], [
            'Id' => (int) $customer['Id'],
        ]);

        if (!$updated) {
            return false;
        }

        delete_transient(self::TOKEN_PREFIX . $user_id);

        return true;
    }

    protected function generate_token(int $user_id): string {
        $token = bin2hex(random_bytes(16));
        set_transient(self::TOKEN_PREFIX . $user_id, $token, $this->token_ttl);

        return $token;
    }

    protected function build_verification_url(int $user_id, string $token): string {
        $base_url = $this->resolve_login_page_url();

        return add_query_arg([
            'myvh_verify_email' => 1,
            'uid' => $user_id,
            'token' => $token,
        ], $base_url);
    }

    protected function resolve_login_page_url(): string {
        $filtered = apply_filters('myvh_login_page_url', '');
        if (is_string($filtered) && $filtered !== '') {
            return esc_url_raw($filtered);
        }

        $template_pages = get_pages([
            'post_type' => 'page',
            'post_status' => 'publish',
            'meta_key' => '_wp_page_template',
            'meta_value' => 'templates/page-login.php',
            'number' => 1,
        ]);

        if (!empty($template_pages) && !empty($template_pages[0]->ID)) {
            return (string) get_permalink((int) $template_pages[0]->ID);
        }

        foreach (['login', 'sign-in', 'signin', 'account-login'] as $slug) {
            $candidate = get_page_by_path($slug);
            if ($candidate && !empty($candidate->ID)) {
                return (string) get_permalink((int) $candidate->ID);
            }
        }

        return home_url('/login/');
    }
}
<?php

namespace MYVH\Network;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class SiteProvisioningService {

    private const VERIFY_PREFIX = 'myvh_site_request_';

    public function __construct(
        private CreateSiteRequestValidator $validator,
        private WpSiteCloner $cloner,
        private SiteProvisioningRepository $repo
    ) {}

    /**
     * Step 1: Submit request
     */
    public function submit(array $raw_data): array {

        $data = $this->sanitize($raw_data);

        $validated = $this->validator->validate($data);
        if (is_wp_error($validated)) {
            return ['ok' => false, 'message' => $validated->get_error_message()];
        }

        $token = bin2hex(random_bytes(16));

        $provision_id = $this->repo->create([
            'token' => $token,
            'subdomain' => $data['subdomain'],
            'site_name' => $data['site_name'],
            'admin_email' => $data['admin_email'],
            'admin_first_name' => $data['admin_first_name'],
            'admin_last_name' => $data['admin_last_name'],
            'admin_password' => wp_hash_password($data['admin_password']),
            'user_id' => 0, // resolved at provisioning step
            'blog_id' => 0, // resolved at provisioning step
            'status' => 'pending',
            'error' => '',
            'logo_url' => $data['logo_url'] ?? '',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        set_transient(self::VERIFY_PREFIX . $token, true, HOUR_IN_SECONDS);

        $request_page_url = $this->buildRequestPageUrl((string) ($data['request_page_url'] ?? ''));

        $verify_url = add_query_arg([
            'myvh_site_verify' => 1,
            'token' => rawurlencode($token),
        ], $request_page_url);

        $cancel_url = add_query_arg([
            'myvh_site_cancel' => 1,
            'token' => rawurlencode($token),
        ], $request_page_url);

        $this->sendVerificationEmail($data, $verify_url, $cancel_url);

        return [
            'ok' => true,
            'message' => __('Check your email to continue.', 'my-village-hall'),
        ];
    }

    /**
     * Step 2: Verify + trigger provisioning
     */
    public function verify_and_provision(string $token): array {

        if (!get_transient(self::VERIFY_PREFIX . $token)) {
            return [
                'ok' => false,
                'message' => __('Invalid or expired link.', 'my-village-hall'),
                'details' => [],
            ];
        }

        delete_transient(self::VERIFY_PREFIX . $token);

        $row = $this->repo->find_by_token($token);

        if (!$row) {
            return [
                'ok' => false,
                'message' => __('Provisioning record not found.', 'my-village-hall'),
                'details' => [],
            ];
        }

        $this->repo->update_status($row['id'], 'verified');

        return $this->provision($row['id'], $row);
    }

    public function cancel_request(string $token): array {
        if (!get_transient(self::VERIFY_PREFIX . $token)) {
            return [
                'ok' => false,
                'message' => __('This request is already processed or the cancellation link has expired.', 'my-village-hall'),
                'details' => [],
            ];
        }

        $row = $this->repo->find_by_token($token);
        if (!$row) {
            delete_transient(self::VERIFY_PREFIX . $token);

            return [
                'ok' => false,
                'message' => __('Provisioning record not found.', 'my-village-hall'),
                'details' => [],
            ];
        }

        $details = $this->build_details($row);

        $this->repo->delete((int) $row['id']);
        delete_transient(self::VERIFY_PREFIX . $token);

        return [
            'ok' => true,
            'message' => __('Your site request has been cancelled.', 'my-village-hall'),
            'details' => $details,
        ];
    }

    /**
     * Step 3: Provision
     */
    private function provision(int $provision_id, array $payload): array {

        $source_site_id = NetworkProvisioningSettings::template_site_id();

        if ($source_site_id <= 0) {
            $details = $this->build_details($payload);
            $this->sendProvisioningOutcomeEmail($payload, false, [
                'details' => $details,
                'reason' => __('Template site not configured.', 'my-village-hall'),
            ]);

            return [
                'ok' => false,
                'message' => __('Template site not configured.', 'my-village-hall'),
                'details' => $details,
            ];
        }

        $user_id = $this->resolve_admin_user($payload);

        if (is_wp_error($user_id)) {
            $details = $this->build_details($payload);
            $this->sendProvisioningOutcomeEmail($payload, false, [
                'details' => $details,
                'reason' => $user_id->get_error_message(),
            ]);

            return [
                'ok' => false,
                'message' => $user_id->get_error_message(),
                'details' => $details,
            ];
        }

        $this->repo->update_status($provision_id, 'cloning');

        $result = $this->cloner->clone(
            $source_site_id,
            [
                'name'  => $payload['subdomain'],
                'title' => $payload['site_name'],
                'email' => $payload['admin_email'],
            ],
            [
                'provision_id' => $provision_id,
                'user_id'      => $user_id,
                'logo_url'     => $payload['logo_url'] ?? '',
            ]
        );

        if (is_wp_error($result)) {
            $details = $this->build_details($payload);
            $this->sendProvisioningOutcomeEmail($payload, false, [
                'details' => $details,
                'reason' => $result->get_error_message(),
            ]);

            $this->repo->update_status($provision_id, 'failed', [
                'error' => $result->get_error_message(),
            ]);

            do_action('myvh_event_site.clone_failed', [
                'provision_id' => $provision_id,
                'reason' => $result->get_error_message(),
            ]);

            return [
                'ok' => false,
                'message' => __('Site creation failed.', 'my-village-hall'),
                'details' => $details,
            ];
        }

        $blog_id = (int) $result;
        $site_url = get_site_url($blog_id);

        $this->repo->update_status($provision_id, 'site cloned', [
            'blog_id' => $blog_id,
            'user_id' => $user_id,
        ]);

        $details = $this->build_details($payload, [
            'blog_id' => $blog_id,
            'site_url' => $site_url,
        ]);

        $this->sendProvisioningOutcomeEmail($payload, true, [
            'details' => $details,
            'site_url' => $site_url,
        ]);

        return [
            'ok' => true,
            'message' => __('Site has been cloned.', 'my-village-hall'),
            'site_url' => $site_url,
            'details' => $details,
        ];
    }

    private function sendVerificationEmail(array $data, string $verify_url, string $cancel_url): void {
        $to = sanitize_email((string) ($data['admin_email'] ?? ''));
        if ($to === '') {
            return;
        }

        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $requested_url = $this->buildRequestedSiteUrl((string) ($data['subdomain'] ?? ''));
        $admin_name = trim(((string) ($data['admin_first_name'] ?? '')) . ' ' . ((string) ($data['admin_last_name'] ?? '')));
        $admin_name = $admin_name !== '' ? $admin_name : __('Administrator', 'my-village-hall');

        $subject = sprintf(
            __('[%s] Confirm your new site request', 'my-village-hall'),
            $site_name
        );

        $text_message = implode("\n", [
            sprintf(__('Hello %s,', 'my-village-hall'), $admin_name),
            '',
            __('Thanks for requesting a new site. Please verify your request to continue setup.', 'my-village-hall'),
            '',
            __('Request summary:', 'my-village-hall'),
            sprintf(__('Site name: %s', 'my-village-hall'), (string) ($data['site_name'] ?? '')),
            sprintf(__('Requested URL: %s', 'my-village-hall'), $requested_url),
            sprintf(__('Administrator email: %s', 'my-village-hall'), $to),
            '',
            __('Verify your request using this secure link:', 'my-village-hall'),
            esc_url_raw($verify_url),
            '',
            __('This link expires in 1 hour.', 'my-village-hall'),
            __('Need to cancel this request instead?', 'my-village-hall'),
            esc_url_raw($cancel_url),
            '',
            __('If you did not make this request, you can ignore this email.', 'my-village-hall'),
        ]);

        $html_message = $this->buildEmailHtml(
            sprintf(__('Hello %s,', 'my-village-hall'), $admin_name),
            [
                __('Thanks for requesting a new site. Please verify your request to continue setup.', 'my-village-hall'),
            ],
            [
                __('Site name', 'my-village-hall') => (string) ($data['site_name'] ?? ''),
                __('Requested URL', 'my-village-hall') => $requested_url,
                __('Administrator email', 'my-village-hall') => $to,
            ],
            [
                __('This link expires in 1 hour.', 'my-village-hall'),
                __('If you did not make this request, you can ignore this email.', 'my-village-hall'),
            ],
            __('Verify request', 'my-village-hall'),
            esc_url_raw($verify_url),
            __('Cancel this request', 'my-village-hall'),
            esc_url_raw($cancel_url)
        );

        $this->sendEmailWithFallback(
            $to,
            $subject,
            $text_message,
            $html_message
        );
    }

    private function sendProvisioningOutcomeEmail(array $payload, bool $is_success, array $context = []): void {
        $to = sanitize_email((string) ($payload['admin_email'] ?? ''));
        if ($to === '') {
            return;
        }

        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $details = is_array($context['details'] ?? null) ? $context['details'] : $this->build_details($payload);
        $admin_name = trim(((string) ($details['admin_first_name'] ?? '')) . ' ' . ((string) ($details['admin_last_name'] ?? '')));
        $admin_name = $admin_name !== '' ? $admin_name : __('Administrator', 'my-village-hall');
        $requested_url = $this->buildRequestedSiteUrl((string) ($details['subdomain'] ?? ''));

        $subject = $is_success
            ? sprintf(__('[%s] Your new site is ready', 'my-village-hall'), $site_name)
            : sprintf(__('[%s] We could not complete your site setup', 'my-village-hall'), $site_name);

        $lines = [
            sprintf(__('Hello %s,', 'my-village-hall'), $admin_name),
            '',
            $is_success
                ? __('Your site request has been verified and setup is complete.', 'my-village-hall')
                : __('Your site request was verified, but we were unable to complete setup.', 'my-village-hall'),
            '',
            __('Provisioning summary:', 'my-village-hall'),
            sprintf(__('Site name: %s', 'my-village-hall'), (string) ($details['site_name'] ?? '')),
            sprintf(__('Requested URL: %s', 'my-village-hall'), $requested_url),
            sprintf(__('Administrator email: %s', 'my-village-hall'), (string) ($details['admin_email'] ?? '')),
        ];

        if ($is_success && !empty($context['site_url'])) {
            $lines[] = sprintf(__('New site URL: %s', 'my-village-hall'), esc_url_raw((string) $context['site_url']));
            $lines[] = '';
            $lines[] = __('You can now access and manage your site using the administrator account details you provided.', 'my-village-hall');
        }

        if (!$is_success && !empty($context['reason'])) {
            $lines[] = sprintf(__('Reason: %s', 'my-village-hall'), wp_strip_all_tags((string) $context['reason']));
            $lines[] = '';
            $lines[] = __('Please submit a new request or contact support with the details above.', 'my-village-hall');
        }

        $html_notes = [];
        if ($is_success) {
            $html_notes[] = __('You can now access and manage your site using the administrator account details you provided.', 'my-village-hall');
        } elseif (!empty($context['reason'])) {
            $html_notes[] = sprintf(__('Reason: %s', 'my-village-hall'), wp_strip_all_tags((string) $context['reason']));
            $html_notes[] = __('Please submit a new request or contact support with the details above.', 'my-village-hall');
        }

        $html_message = $this->buildEmailHtml(
            sprintf(__('Hello %s,', 'my-village-hall'), $admin_name),
            [
                $is_success
                    ? __('Your site request has been verified and setup is complete.', 'my-village-hall')
                    : __('Your site request was verified, but we were unable to complete setup.', 'my-village-hall'),
            ],
            [
                __('Site name', 'my-village-hall') => (string) ($details['site_name'] ?? ''),
                __('Requested URL', 'my-village-hall') => $requested_url,
                __('Administrator email', 'my-village-hall') => (string) ($details['admin_email'] ?? ''),
                __('New site URL', 'my-village-hall') => $is_success && !empty($context['site_url']) ? (string) $context['site_url'] : '',
            ],
            $html_notes,
            $is_success && !empty($context['site_url']) ? __('Open your new site', 'my-village-hall') : '',
            $is_success && !empty($context['site_url']) ? esc_url_raw((string) $context['site_url']) : ''
        );

        $this->sendEmailWithFallback($to, $subject, implode("\n", $lines), $html_message);
    }

    private function sendEmailWithFallback(string $to, string $subject, string $text_message, string $html_message): void {
        $body = $html_message !== '' ? $html_message : $text_message;
        $headers = [];

        if ($html_message !== '') {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }

        wp_mail($to, $subject, $body, $headers);
    }

    private function buildEmailHtml(
        string $heading,
        array $paragraphs,
        array $summary,
        array $notes = [],
        string $cta_label = '',
        string $cta_url = '',
        string $secondary_cta_label = '',
        string $secondary_cta_url = ''
    ): string {
        $summary_rows = '';
        foreach ($summary as $label => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $summary_rows .= sprintf(
                '<tr><td style="padding:8px 0;color:#5b7286;font-size:13px;vertical-align:top;width:170px;">%s</td><td style="padding:8px 0;color:#102a43;font-size:14px;font-weight:600;word-break:break-word;">%s</td></tr>',
                esc_html((string) $label),
                esc_html($value)
            );
        }

        $paragraph_html = '';
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string) $paragraph);
            if ($paragraph === '') {
                continue;
            }

            $paragraph_html .= sprintf(
                '<p style="margin:0 0 14px;color:#334e68;font-size:14px;line-height:1.6;">%s</p>',
                esc_html($paragraph)
            );
        }

        $notes_html = '';
        foreach ($notes as $note) {
            $note = trim((string) $note);
            if ($note === '') {
                continue;
            }

            $notes_html .= sprintf(
                '<p style="margin:0 0 8px;color:#5b7286;font-size:13px;line-height:1.5;">%s</p>',
                esc_html($note)
            );
        }

        $cta_html = '';
        if ($cta_label !== '' && $cta_url !== '') {
            $cta_html = sprintf(
                '<p style="margin:22px 0 0;"><a href="%s" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:11px 18px;border-radius:999px;">%s</a></p>',
                esc_url($cta_url),
                esc_html($cta_label)
            );
        }

        $secondary_cta_html = '';
        if ($secondary_cta_label !== '' && $secondary_cta_url !== '') {
            $secondary_cta_html = sprintf(
                '<p style="margin:10px 0 0;"><a href="%s" style="display:inline-block;color:#164e63;text-decoration:none;font-weight:600;font-size:13px;">%s</a></p>',
                esc_url($secondary_cta_url),
                esc_html($secondary_cta_label)
            );
        }

        return sprintf(
            '<!doctype html><html><body style="margin:0;padding:20px;background:#f3f7fb;font-family:Segoe UI,Arial,sans-serif;"><table role="presentation" cellspacing="0" cellpadding="0" width="100%%" style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #d7e1ea;border-radius:14px;"><tr><td style="padding:24px 24px 16px;background:linear-gradient(135deg,#0f766e,#164e63);border-radius:14px 14px 0 0;"><h1 style="margin:0;color:#ffffff;font-size:20px;line-height:1.3;">%s</h1></td></tr><tr><td style="padding:20px 24px;">%s<table role="presentation" cellspacing="0" cellpadding="0" width="100%%" style="border-top:1px solid #d7e1ea;border-bottom:1px solid #d7e1ea;margin:8px 0 0;">%s</table>%s%s</td></tr><tr><td style="padding:14px 24px 22px;background:#f8fbfe;border-radius:0 0 14px 14px;">%s</td></tr></table></body></html>',
            esc_html($heading),
            $paragraph_html,
            $summary_rows,
            $cta_html,
            $secondary_cta_html,
            $notes_html
        );
    }

    private function buildRequestedSiteUrl(string $subdomain): string {
        $subdomain = sanitize_title($subdomain);
        $network = get_network();

        if (!$network) {
            return $subdomain;
        }

        $domain = preg_replace('/^www\./', '', (string) $network->domain);

        if (is_subdomain_install()) {
            return $subdomain . '.' . $domain;
        }

        $path = trailingslashit((string) $network->path);

        return $domain . $path . $subdomain;
    }

    private function buildRequestPageUrl(string $candidate): string {
        $candidate = trim($candidate);
        if ($candidate !== '' && wp_parse_url($candidate, PHP_URL_SCHEME) === null) {
            $candidate = home_url('/' . ltrim($candidate, '/'));
        }

        $candidate = esc_url_raw($candidate);
        if ($candidate === '') {
            return home_url('/');
        }

        $validated = wp_validate_redirect($candidate, '');
        if ($validated === '') {
            return home_url('/');
        }

        return remove_query_arg(['myvh_site_verify', 'myvh_site_cancel', 'token'], $validated);
    }

    private function build_details(array $payload, array $extra = []): array {
        return array_merge([
            'site_name' => sanitize_text_field((string) ($payload['site_name'] ?? '')),
            'subdomain' => sanitize_title((string) ($payload['subdomain'] ?? '')),
            'admin_email' => sanitize_email((string) ($payload['admin_email'] ?? '')),
            'admin_first_name' => sanitize_text_field((string) ($payload['admin_first_name'] ?? '')),
            'admin_last_name' => sanitize_text_field((string) ($payload['admin_last_name'] ?? '')),
        ], $extra);
    }

    /**
     * Resolve or create admin user
     */
    private function resolve_admin_user(array $payload): int|WP_Error {

        $email = $payload['admin_email'];

        $user = get_user_by('email', $email);
        if ($user) {
            return (int) $user->ID;
        }

        $base = sanitize_user(strtok($email, '@'), true) ?: 'siteadmin';
        $username = $base;
        $i = 1;

        while (username_exists($username)) {
            $username = $base . $i++;
        }

        $user_id = wp_create_user($username, $payload['admin_password'], $email);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        wp_update_user([
            'ID' => $user_id,
            'first_name' => $payload['admin_first_name'] ?? '',
            'last_name'  => $payload['admin_last_name'] ?? '',
        ]);

        return (int) $user_id;
    }

    /**
     * Sanitisation
     */
    private function sanitize(array $raw): array {
        return [
            'site_name'        => sanitize_text_field($raw['site_name'] ?? ''),
            'subdomain'        => sanitize_title($raw['subdomain'] ?? ''),
            'admin_email'      => sanitize_email($raw['admin_email'] ?? ''),
            'admin_first_name' => sanitize_text_field($raw['admin_first_name'] ?? ''),
            'admin_last_name'  => sanitize_text_field($raw['admin_last_name'] ?? ''),
            'admin_password'   => (string) ($raw['admin_password'] ?? ''),
            'logo_url'         => esc_url_raw($raw['logo_url'] ?? ''),
            'request_page_url' => esc_url_raw($raw['myvh_request_page_url'] ?? ''),
        ];
    }
}
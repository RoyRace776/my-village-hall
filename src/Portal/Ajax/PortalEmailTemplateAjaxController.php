<?php
namespace MYVH\Portal\Ajax;

use MYVH\Email\EmailService;
use MYVH\Email\EmailTemplateRegistry;
use MYVH\Portal\ClientAdminService;
use MYVH\Portal\Support\PortalAuth;
use MYVH\Portal\Support\AjaxResponse;
use MYVH\Settings\EmailTemplateSettings;

class PortalEmailTemplateAjaxController {

    public function __construct(
        private ClientAdminService $client_admin_service,
        private EmailTemplateSettings $email_template_settings
    ) {}

    public function register(): void {
        add_action('wp_ajax_myvh_save_email_template', [$this, 'save_template']);
        add_action('wp_ajax_myvh_reset_email_template', [$this, 'reset_template']);
        add_action('wp_ajax_myvh_preview_email_template', [$this, 'preview_template']);
        add_action('wp_ajax_myvh_send_test_email_template', [$this, 'send_test_email']);
    }

    public function save_template(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $slug = sanitize_key((string) ($_POST['template'] ?? ''));

        if (!EmailTemplateRegistry::has($slug)) {
            AjaxResponse::error(__('Unknown email template', 'my-village-hall'));
        }

        $subject = sanitize_text_field((string) ($_POST['subject'] ?? ''));
        $html_body = $this->sanitize_email_html((string) ($_POST['html_body'] ?? ''));

        if ($subject === '' || $html_body === '') {
            AjaxResponse::validation_error(['template' => __('Subject and body are required', 'my-village-hall')]);
        }

        $this->email_template_settings->save_template($slug, $subject, $html_body);

        AjaxResponse::success([
            'template' => $slug,
        ], __('Email template saved', 'my-village-hall'));
    }

    public function reset_template(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $slug = sanitize_key((string) ($_POST['template'] ?? ''));

        if (!EmailTemplateRegistry::has($slug)) {
            AjaxResponse::error(__('Unknown email template', 'my-village-hall'));
        }

        $this->email_template_settings->reset_template($slug);

        AjaxResponse::success([
            'template' => $slug,
        ], __('Email template reset to default', 'my-village-hall'));
    }

    public function preview_template(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $slug = sanitize_key((string) ($_POST['template'] ?? ''));

        if (!EmailTemplateRegistry::has($slug)) {
            AjaxResponse::error(__('Unknown email template', 'my-village-hall'));
        }

        $subject = sanitize_text_field((string) ($_POST['subject'] ?? ''));
        $html_body = $this->sanitize_email_html((string) ($_POST['html_body'] ?? ''));

        $template_config = $this->email_template_settings->get_template($slug);
        if ($subject === '') {
            $subject = (string) ($template_config['subject'] ?? EmailTemplateRegistry::default_subject($slug));
        }

        if ($html_body === '') {
            $html_body = (string) ($template_config['html_body'] ?? EmailTemplateRegistry::default_html($slug));
        }

        $email_service = new EmailService();
        $sample_vars = EmailTemplateRegistry::sample_vars($slug, $email_service->get_branding());
        $replacements = EmailTemplateRegistry::replacement_map($slug, $sample_vars);
        $html = strtr($html_body, $replacements);
        $subject = strtr($subject, $replacements);

        AjaxResponse::success([
            'subject' => $subject,
            'html' => $html,
        ]);
    }

    public function send_test_email(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $slug = sanitize_key((string) ($_POST['template'] ?? ''));

        if (!EmailTemplateRegistry::has($slug)) {
            AjaxResponse::error(__('Unknown email template', 'my-village-hall'));
        }

        $subject = sanitize_text_field((string) ($_POST['subject'] ?? ''));
        $html_body = $this->sanitize_email_html((string) ($_POST['html_body'] ?? ''));

        $template_config = $this->email_template_settings->get_template($slug);
        if ($subject === '') {
            $subject = (string) ($template_config['subject'] ?? EmailTemplateRegistry::default_subject($slug));
        }

        if ($html_body === '') {
            $html_body = (string) ($template_config['html_body'] ?? EmailTemplateRegistry::default_html($slug));
        }

        $email_service = new EmailService();
        $sample_vars   = EmailTemplateRegistry::sample_vars($slug, $email_service->get_branding());
        $replacements  = EmailTemplateRegistry::replacement_map($slug, $sample_vars);
        $html          = strtr($html_body, $replacements);
        $subject       = strtr($subject, $replacements);

        $recipient = wp_get_current_user()->user_email;
        if (!$recipient || !is_email($recipient)) {
            AjaxResponse::error(__('Could not determine admin email address', 'my-village-hall'));
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $sent    = wp_mail($recipient, $subject, $html, $headers);

        if (!$sent) {
            AjaxResponse::server_error(__('Failed to send test email — check your site mail configuration', 'my-village-hall'));
        }

        AjaxResponse::success([
            'recipient' => $recipient,
        ], __('Test email sent to ' . $recipient, 'my-village-hall'));
    }

    private function sanitize_email_html(string $html): string {
        $allowed = wp_kses_allowed_html('post');

        $tags_with_style = ['a', 'p', 'div', 'span', 'table', 'tbody', 'thead', 'tr', 'td', 'th', 'img', 'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'strong', 'em', 'br'];
        foreach ($tags_with_style as $tag) {
            if (!isset($allowed[$tag])) {
                $allowed[$tag] = [];
            }

            $allowed[$tag]['style'] = true;
            $allowed[$tag]['class'] = true;
        }

        $allowed['a']['href'] = true;
        $allowed['a']['target'] = true;
        $allowed['a']['rel'] = true;
        $allowed['img']['src'] = true;
        $allowed['img']['alt'] = true;
        $allowed['img']['width'] = true;
        $allowed['img']['height'] = true;

        return wp_kses($html, $allowed);
    }
}

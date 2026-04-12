<?php
namespace MYVH\Email;

use MYVH\Settings\EmailTemplateSettings;

/**
 * Email_Service: Modular, multisite-aware email sending for My Village Hall
 */
class EmailService {
    protected $site_id;
    protected $log_enabled;

    public function __construct($site_id = null, $log_enabled = true) {
        $this->site_id = $site_id ?: get_current_blog_id();
        $this->log_enabled = $log_enabled;
    }

    /**
     * Send an email using wp_mail, with HTML and text template support.
     * $args: [to, subject, template, template_vars, headers, attachments]
     */
    public function send($args) {
        $to = $args['to'] ?? '';
        $subject = $args['subject'] ?? '';
        $template = $args['template'] ?? '';
        $template_vars = $args['template_vars'] ?? [];
        $headers = $args['headers'] ?? [];
        $attachments = $args['attachments'] ?? [];

        $settings = new EmailTemplateSettings();
        $template_config = $settings->get_template($template);
        $is_customized = !empty($template_config['is_customized']);

        if ($template && EmailTemplateRegistry::has((string) $template)) {
            $configured_subject = (string) ($template_config['subject'] ?? '');

            if ($is_customized && $configured_subject !== '') {
                // Keep existing behavior: customized template subject wins.
                $subject = $configured_subject;
            } elseif ($subject === '') {
                $subject = $configured_subject !== ''
                    ? $configured_subject
                    : EmailTemplateRegistry::default_subject((string) $template, '');
            }

            if ($subject !== '') {
                $subject = $this->replace_placeholders($subject, (string) $template, $template_vars);
            }
        }

        // Load HTML/text template with custom-template override.
        $html = $this->render($template, $template_vars, 'html');
        $text = $this->render($template, $template_vars, 'txt');

        if ($html) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }

        $result = wp_mail($to, $subject, $html ?: $text, $headers, $attachments);

        if ($this->log_enabled) {
            $this->log_email($to, $subject, $result, $template, $template_vars);
        }

        return $result;
    }

    public function render($template, $vars = [], $type = 'html'): string {
        $custom_html = $this->render_custom_template($template, $vars);

        if ($custom_html !== '') {
            if ($type === 'txt') {
                return trim(wp_strip_all_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $custom_html)));
            }

            return $custom_html;
        }

        return $this->render_file_template($template, $vars, $type);
    }

    /**
     * Render an email template (HTML or text)
     */
    protected function render_template($template, $vars, $type = 'html') {
        return $this->render($template, $vars, $type);
    }

    protected function render_file_template($template, $vars, $type = 'html') {
        $dir = plugin_dir_path(__FILE__) . 'templates/';
        $file = $dir . $template . '.' . $type . '.php';
        if (!file_exists($file)) return '';
        ob_start();
        extract($vars, EXTR_SKIP);
        include $file;
        return ob_get_clean();
    }

    protected function render_custom_template($template, $vars): string {
        if (!$template || !EmailTemplateRegistry::has((string) $template)) {
            return '';
        }

        $settings = new EmailTemplateSettings();
        $template_config = $settings->get_template((string) $template);

        if (empty($template_config['is_customized'])) {
            return '';
        }

        $html_body = (string) ($template_config['html_body'] ?? '');
        if ($html_body === '') {
            return '';
        }

        return $this->replace_placeholders($html_body, (string) $template, $vars);
    }

    protected function replace_placeholders(string $content, string $template, array $vars): string {
        if ($content === '') {
            return '';
        }

        $branding = $this->get_branding();
        $merged_vars = array_merge(
            [
                'site_name' => $branding['site_name'] ?? '',
                'site_url' => $branding['site_url'] ?? '',
                'logo_url' => $branding['logo_url'] ?? '',
            ],
            $vars
        );

        $replacements = EmailTemplateRegistry::replacement_map($template, $merged_vars);
        return strtr($content, $replacements);
    }

    /**
     * Log email send attempts (basic file log for now)
     */
    protected function log_email($to, $subject, $result, $template, $vars) {
        $log_dir = WP_CONTENT_DIR . '/uploads/myvh-email-logs/';
        if (!is_dir($log_dir)) @mkdir($log_dir, 0755, true);
        $log_file = $log_dir . 'email-log-' . date('Y-m-d') . '.log';
        $entry = sprintf("[%s] To: %s | Subject: %s | Template: %s | Result: %s\n", date('c'), $to, $subject, $template, $result ? 'SENT' : 'FAILED');
        file_put_contents($log_file, $entry, FILE_APPEND);
    }

    /**
     * Get per-site branding (logo, colors, sender, etc.)
     */
    public function get_branding() {
        // Placeholder: fetch from options or use defaults
        return [
            'logo_url' => get_site_icon_url(128),
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url('/'),
            // Add more as needed
        ];
    }
}
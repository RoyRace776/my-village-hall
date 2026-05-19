<?php
namespace MYVH\Email;

use MYVH\Settings\EmailTemplateSettings;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Email_Service: Modular, multisite-aware email sending for My Village Hall
 */
class EmailService {
    protected int $site_id;
    protected bool $log_enabled;
    protected LoggerInterface $logger;

    public function __construct(?int $site_id = null, bool $log_enabled = true, ?LoggerInterface $logger = null) {
        $this->site_id = $site_id ?: get_current_blog_id();
        $this->log_enabled = $log_enabled;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Send an email using wp_mail, with HTML and text template support.
     * $args: [to, subject, template, template_vars, headers, attachments]
     */
    public function send(array $args): bool {
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

    public function render(string $template, array $vars = [], string $type = 'html'): string {
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
    protected function render_template(string $template, array $vars, string $type = 'html'): string {
        return $this->render($template, $vars, $type);
    }

    protected function render_file_template(string $template, array $vars, string $type = 'html'): string {
        $dir = plugin_dir_path(__FILE__) . 'templates/';
        $file = $dir . $template . '.' . $type . '.php';
        if (!file_exists($file)) return '';
        ob_start();
        extract($vars, EXTR_SKIP);
        include $file;
        return ob_get_clean();
    }

    protected function render_custom_template(string $template, array $vars): string {
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
     * Log email send attempts via the central logger.
     */
    protected function log_email(string $to, string $subject, bool $result, string $template, array $vars): void {
        $level = $result ? 'info' : 'warning';
        $this->logger->{$level}('Email send attempted', [
            'to' => $to,
            'subject' => $subject,
            'template' => $template,
            'result' => $result ? 'sent' : 'failed',
        ]);
    }

    /**
     * Get per-site branding (logo, colors, sender, etc.)
     */
    public function get_branding(): array {
        // Placeholder: fetch from options or use defaults
        return [
            'logo_url' => get_site_icon_url(128),
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url('/'),
            // Add more as needed
        ];
    }
}
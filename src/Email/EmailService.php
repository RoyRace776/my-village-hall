<?php
namespace MYVH\Email;

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

        // Load HTML template
        $html = $this->render_template($template, $template_vars, 'html');
        $text = $this->render_template($template, $template_vars, 'txt');

        if ($html) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }

        $result = wp_mail($to, $subject, $html ?: $text, $headers, $attachments);

        if ($this->log_enabled) {
            $this->log_email($to, $subject, $result, $template, $template_vars);
        }

        return $result;
    }

    /**
     * Render an email template (HTML or text)
     */
    protected function render_template($template, $vars, $type = 'html') {
        $dir = plugin_dir_path(__FILE__) . 'templates/';
        $file = $dir . $template . '.' . $type . '.php';
        if (!file_exists($file)) return '';
        ob_start();
        extract($vars, EXTR_SKIP);
        include $file;
        return ob_get_clean();
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
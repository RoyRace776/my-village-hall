<?php

declare(strict_types=1);

namespace MYVH\Email\Mailer;

use MYVH\Settings\EmailServiceSettings;

if (!defined('ABSPATH')) {
    exit;
}

class SmtpTransport implements MailTransport {
    private static bool $configured = false;

    public function __construct(private EmailServiceSettings $settings) {
    }

    public function send(string $to, string $subject, string $message, array $headers = []): bool {
        $this->registerHookOnce();
        [$mail_headers, $attachments] = MailPayload::split($headers);

        return wp_mail($to, $subject, $message, $mail_headers, $attachments);
    }

    public function configureMailer($phpmailer): void {
        $phpmailer->isSMTP();
        $phpmailer->Host = (string) $this->settings->get('smtp_host');
        $phpmailer->Port = max(1, (int) $this->settings->get('smtp_port'));

        $username = (string) $this->settings->get('smtp_username');
        $password = (string) $this->settings->get('smtp_password');

        $phpmailer->SMTPAuth = ($username !== '' || $password !== '');
        $phpmailer->Username = $username;
        $phpmailer->Password = $password;

        $encryption = strtolower((string) $this->settings->get('smtp_encryption'));
        if (in_array($encryption, ['tls', 'ssl'], true)) {
            $phpmailer->SMTPSecure = $encryption;
        }
    }

    private function registerHookOnce(): void {
        if (self::$configured) {
            return;
        }

        add_action('phpmailer_init', [$this, 'configureMailer']);
        self::$configured = true;
    }
}

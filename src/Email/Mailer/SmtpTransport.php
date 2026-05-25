<?php

declare(strict_types=1);

namespace MYVH\Email\Mailer;

use MYVH\Subscriptions\Repositories\SettingsRepository;

if (!defined('ABSPATH')) {
    exit;
}

class SmtpTransport implements MailTransport {
    private static bool $configured = false;

    public function __construct(private SettingsRepository $settings_repository) {
    }

    public function send(string $to, string $subject, string $message, array $headers = []): bool {
        $this->registerHookOnce();
        [$mail_headers, $attachments] = MailPayload::split($headers);

        return wp_mail($to, $subject, $message, $mail_headers, $attachments);
    }

    public function configureMailer($phpmailer): void {
        $phpmailer->isSMTP();
        $phpmailer->Host = (string) $this->settings_repository->get_setting_value('smtp.host', '');
        $phpmailer->Port = max(1, (int) $this->settings_repository->get_setting_value('smtp.port', 587));

        $username = (string) $this->settings_repository->get_setting_value('smtp.username', '');
        $password = (string) $this->settings_repository->get_setting_value('smtp.password', '');

        $phpmailer->SMTPAuth = ($username !== '' || $password !== '');
        $phpmailer->Username = $username;
        $phpmailer->Password = $password;

        $encryption = strtolower((string) $this->settings_repository->get_setting_value('smtp.encryption', 'tls'));
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

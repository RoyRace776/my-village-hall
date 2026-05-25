<?php

declare(strict_types=1);

namespace MYVH\Email\Mailer;

use MYVH\Subscriptions\Repositories\SettingsRepository;

if (!defined('ABSPATH')) {
    exit;
}

class MailTransportFactory {
    public function __construct(
        private SettingsRepository $settings_repository,
        private WpMailTransport $wp_mail_transport,
        private SmtpTransport $smtp_transport,
        private ApiTransport $api_transport
    ) {
    }

    public function create(): MailTransport {
        $transport = strtolower((string) $this->settings_repository->get_setting_value('mail.transport', 'wp_mail'));

        return match ($transport) {
            'smtp' => $this->smtp_transport,
            'api' => $this->api_transport,
            default => $this->wp_mail_transport,
        };
    }
}

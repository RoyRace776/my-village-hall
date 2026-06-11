<?php

declare(strict_types=1);

namespace MYVH\Email\Mailer;

use MYVH\Settings\EmailServiceSettings;

if (!defined('ABSPATH')) {
    exit;
}

class MailTransportFactory {
    public function __construct(
        private EmailServiceSettings $settings,
        private WpMailTransport $wp_mail_transport,
        private SmtpTransport $smtp_transport,
        private ApiTransport $api_transport
    ) {
    }

    public function create(): MailTransport {
        $transport = strtolower((string) $this->settings->get('mail_transport'));

        return match ($transport) {
            'smtp' => $this->smtp_transport,
            'api' => $this->api_transport,
            default => $this->wp_mail_transport,
        };
    }
}

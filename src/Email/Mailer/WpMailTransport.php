<?php

declare(strict_types=1);

namespace MYVH\Email\Mailer;

if (!defined('ABSPATH')) {
    exit;
}

class WpMailTransport implements MailTransport {
    public function send(string $to, string $subject, string $message, array $headers = []): bool {
        [$mail_headers, $attachments] = MailPayload::split($headers);

        return wp_mail($to, $subject, $message, $mail_headers, $attachments);
    }
}

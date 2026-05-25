<?php

declare(strict_types=1);

namespace MYVH\Email\Mailer;

interface MailTransport {
    public function send(string $to, string $subject, string $message, array $headers = []): bool;
}

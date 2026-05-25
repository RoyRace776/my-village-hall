<?php

declare(strict_types=1);

namespace MYVH\Email\Mailer;

use MYVH\Subscriptions\Repositories\SettingsRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

if (!defined('ABSPATH')) {
    exit;
}

class MailerService {
    private LoggerInterface $logger;

    public function __construct(
        private SettingsRepository $settings_repository,
        private MailTransport $transport,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function send(string $to, string $subject, string $message, array $headers = []): bool {
        $normalized_headers = $this->normalizeHeaders($headers);

        if (!MailPayload::hasHeader($normalized_headers, 'From')) {
            $from_header = $this->buildFromHeader();
            if ($from_header !== null) {
                $normalized_headers[] = $from_header;
            }
        }

        if (!MailPayload::hasHeader($normalized_headers, 'Reply-To')) {
            $reply_to = sanitize_email((string) $this->settings_repository->get_setting_value('email.reply_to', ''));
            if ($reply_to !== '' && is_email($reply_to)) {
                $normalized_headers[] = sprintf('Reply-To: %s', $reply_to);
            }
        }

        $content_type = MailPayload::extractHeaderValue($normalized_headers, 'Content-Type');
        $has_html_content_type = is_string($content_type)
            && str_contains(strtolower($content_type), 'text/html');

        if ($has_html_content_type && empty($normalized_headers['text_body'])) {
            $normalized_headers['text_body'] = trim(
                wp_strip_all_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message))
            );
        }

        $sent = $this->transport->send($to, $subject, $message, $normalized_headers);

        if (!$sent) {
            $transport_class = $this->transport::class;
            $transport_name = str_contains($transport_class, '\\')
                ? (string) substr($transport_class, (int) strrpos($transport_class, '\\') + 1)
                : $transport_class;

            $provider = $transport_name === 'ApiTransport'
                ? (string) $this->settings_repository->get_setting_value('api.provider', 'mailgun')
                : strtolower(str_replace('Transport', '', $transport_name));

            $this->logger->warning('Email send failed', [
                'to' => $to,
                'subject' => $subject,
                'transport' => strtolower($transport_name),
                'provider' => strtolower($provider),
                'response_body' => '',
            ]);
        }

        return $sent;
    }

    private function normalizeHeaders(array $headers): array {
        $normalized = [];

        foreach ($headers as $key => $value) {
            if (is_string($key) && strtolower($key) === 'attachments') {
                $normalized[$key] = $value;
                continue;
            }

            if (is_string($key) && strtolower($key) === 'text_body') {
                if (is_string($value) && $value !== '') {
                    $normalized[$key] = $value;
                }
                continue;
            }

            if (is_int($key)) {
                if (is_string($value) && $value !== '') {
                    $normalized[] = $value;
                }
                continue;
            }

            if (is_string($value) && $value !== '') {
                $normalized[] = sprintf('%s: %s', $key, $value);
            }
        }

        return $normalized;
    }

    private function buildFromHeader(): ?string {
        $from_address = sanitize_email((string) $this->settings_repository->get_setting_value('email.from_address', ''));
        if ($from_address === '' || !is_email($from_address)) {
            return null;
        }

        $from_name = sanitize_text_field((string) $this->settings_repository->get_setting_value('email.from_name', ''));
        if ($from_name === '') {
            $from_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        }

        return sprintf('From: %s <%s>', $from_name, $from_address);
    }
}

<?php

declare(strict_types=1);

namespace MYVH\Email\Mailer;

use MYVH\Subscriptions\Repositories\SettingsRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

if (!defined('ABSPATH')) {
    exit;
}

class ApiTransport implements MailTransport {
    private LoggerInterface $logger;

    public function __construct(
        private SettingsRepository $settings_repository,
        ?LoggerInterface $logger = null,
        private ?MailTransport $fallback_transport = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function send(string $to, string $subject, string $message, array $headers = []): bool {
        $provider = strtolower((string) $this->settings_repository->get_setting_value('api.provider', 'mailgun'));
        $transport_type = 'api';

        [$url, $args] = $this->buildRequest($provider, $to, $subject, $message, $headers);
        if ($url === '' || $args === []) {
            return $this->failWithFallback('Missing API transport configuration', $transport_type, $provider, $to, $subject, $message, $headers);
        }

        $last_reason = '';
        $last_response_body = '';

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $response = wp_remote_post($url, $args);
            if (is_wp_error($response)) {
                $last_reason = (string) $response->get_error_message();
                $this->logger->warning('API email send attempt failed', [
                    'transport' => $transport_type,
                    'provider' => $provider,
                    'attempt' => $attempt,
                    'reason' => $last_reason,
                    'response_body' => '',
                    'to' => $to,
                    'subject' => $subject,
                ]);
                continue;
            }

            $status_code = (int) wp_remote_retrieve_response_code($response);
            $response_body = (string) wp_remote_retrieve_body($response);

            if ($status_code >= 200 && $status_code < 300) {
                return true;
            }

            $last_reason = 'API transport returned HTTP ' . $status_code;
            $last_response_body = $response_body;

            $this->logger->warning('API email send attempt failed', [
                'transport' => $transport_type,
                'provider' => $provider,
                'attempt' => $attempt,
                'reason' => $last_reason,
                'response_body' => $response_body,
                'to' => $to,
                'subject' => $subject,
            ]);
        }

        return $this->failWithFallback($last_reason, $transport_type, $provider, $to, $subject, $message, $headers, $last_response_body);
    }

    private function buildRequest(string $provider, string $to, string $subject, string $message, array $headers): array {
        $api_key = (string) $this->settings_repository->get_setting_value('api.key', '');
        if ($api_key === '') {
            return ['', []];
        }

        [$mail_headers, , $text_body] = MailPayload::split($headers);
        $from_header = $this->extractHeader($mail_headers, 'From');
        $reply_to_header = $this->extractHeader($mail_headers, 'Reply-To');

        if ($from_header === null) {
            return ['', []];
        }

        $is_html = MailPayload::hasHeader($mail_headers, 'Content-Type')
            && str_contains(strtolower((string) $this->extractHeader($mail_headers, 'Content-Type')), 'text/html');

        $html_body = $is_html ? $message : '';
        $plain_body = $text_body;

        if ($plain_body === null || $plain_body === '') {
            $plain_body = $is_html
                ? trim(wp_strip_all_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message)))
                : $message;
        }

        if ($provider === 'mailgun') {
            $domain = (string) $this->settings_repository->get_setting_value('api.domain', '');
            if ($domain === '') {
                return ['', []];
            }

            $body = [
                'from' => $from_header,
                'to' => $to,
                'subject' => $subject,
                'text' => $plain_body,
            ];

            if ($html_body !== '') {
                $body['html'] = $html_body;
            }

            if ($reply_to_header !== null) {
                $body['h:Reply-To'] = $reply_to_header;
            }

            return [
                sprintf('https://api.mailgun.net/v3/%s/messages', rawurlencode($domain)),
                [
                    'timeout' => 20,
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode('api:' . $api_key),
                    ],
                    'body' => $body,
                ],
            ];
        }

        if ($provider === 'sendgrid') {
            [$from_email, $from_name] = $this->parseFromHeader($from_header);

            $content = [
                ['type' => 'text/plain', 'value' => $plain_body],
            ];

            if ($html_body !== '') {
                $content[] = ['type' => 'text/html', 'value' => $html_body];
            }

            $payload = [
                'personalizations' => [
                    ['to' => [['email' => $to]]],
                ],
                'from' => ['email' => $from_email, 'name' => $from_name],
                'subject' => $subject,
                'content' => $content,
            ];

            if ($reply_to_header !== null) {
                $payload['reply_to'] = ['email' => $reply_to_header];
            }

            return [
                'https://api.sendgrid.com/v3/mail/send',
                [
                    'timeout' => 20,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json',
                    ],
                    'body' => wp_json_encode($payload),
                ],
            ];
        }

        // NOTE: custom_api is a generic JSON webhook transport, not native AWS SES signing/API support.
        if ($provider === 'custom_api' || $provider === 'ses') {
            $endpoint = (string) $this->settings_repository->get_setting_value('api.endpoint', '');
            if ($endpoint === '') {
                return ['', []];
            }

            return [
                $endpoint,
                [
                    'timeout' => 20,
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json',
                    ],
                    'body' => wp_json_encode([
                        'provider' => 'custom_api',
                        'from' => $from_header,
                        'reply_to' => $reply_to_header,
                        'to' => $to,
                        'subject' => $subject,
                        'html' => $html_body,
                        'text' => $plain_body,
                        'headers' => $mail_headers,
                    ]),
                ],
            ];
        }

        return ['', []];
    }

    private function failWithFallback(
        string $reason,
        string $transport_type,
        string $provider,
        string $to,
        string $subject,
        string $message,
        array $headers,
        string $response_body = ''
    ): bool {
        $this->logger->warning('API email transport failed', [
            'transport' => $transport_type,
            'provider' => $provider,
            'reason' => $reason,
            'response_body' => $response_body,
            'to' => $to,
            'subject' => $subject,
        ]);

        if ($this->shouldFallback() && $this->fallback_transport instanceof MailTransport) {
            return $this->fallback_transport->send($to, $subject, $message, $headers);
        }

        return false;
    }

    private function shouldFallback(): bool {
        $fallback = $this->settings_repository->get_setting_value('api.fallback_to_wp_mail', '1');
        return in_array($fallback, [true, 1, '1', 'true', 'yes', 'on'], true);
    }

    private function extractHeader(array $headers, string $name): ?string {
        return MailPayload::extractHeaderValue($headers, $name);
    }

    private function parseFromHeader(string $from_header): array {
        if (preg_match('/^(.+?)\s*<([^>]+)>$/', $from_header, $matches) === 1) {
            $name = trim(str_replace(['"', "'"], '', $matches[1]));
            $email = trim($matches[2]);
            if ($email !== '') {
                return [$email, $name];
            }
        }

        return [trim($from_header), ''];
    }
}

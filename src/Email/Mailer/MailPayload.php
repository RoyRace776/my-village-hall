<?php

declare(strict_types=1);

namespace MYVH\Email\Mailer;

if (!defined('ABSPATH')) {
    exit;
}

class MailPayload {
    public static function split(array $headers): array {
        $mail_headers = [];
        $attachments = [];
        $text_body = null;

        foreach ($headers as $key => $value) {
            if (!is_string($key)) {
                if (is_string($value) && $value !== '') {
                    $mail_headers[] = $value;
                }
                continue;
            }

            $normalized_key = strtolower($key);

            if ($normalized_key === 'attachments') {
                if (is_array($value)) {
                    $attachments = array_values(array_filter($value, static fn($item): bool => is_string($item) && $item !== ''));
                } elseif (is_string($value) && $value !== '') {
                    $attachments = [$value];
                }
                continue;
            }

            if ($normalized_key === 'text_body') {
                if (is_string($value) && $value !== '') {
                    $text_body = $value;
                }
                continue;
            }

            if (is_string($value) && $value !== '') {
                $mail_headers[] = sprintf('%s: %s', $key, $value);
            }
        }

        return [$mail_headers, $attachments, $text_body];
    }

    public static function hasHeader(array $headers, string $name): bool {
        return self::extractHeaderValue($headers, $name) !== null;
    }

    public static function extractHeaderValue(array $headers, string $name): ?string {
        $prefix = strtolower($name) . ':';

        foreach ($headers as $header) {
            if (!is_string($header)) {
                continue;
            }

            $trimmed = trim($header);
            if (!str_starts_with(strtolower($trimmed), $prefix)) {
                continue;
            }

            $value = trim(substr($trimmed, strlen($prefix)));
            return $value !== '' ? $value : null;
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace MYVH\Core\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class LoggerFactory
{
    private static ?LoggerInterface $logger = null;

    public static function get(): LoggerInterface
    {
        if (self::$logger === null) {
            self::$logger = self::build_logger();
        }

        return self::$logger;
    }

    public static function refresh(): LoggerInterface
    {
        self::$logger = self::build_logger();

        return self::$logger;
    }

    private static function build_logger(): LoggerInterface
    {
        $log_dir = self::resolve_log_directory();
        self::ensure_log_directory($log_dir);

        $logger = new Logger('myvh');
        $logger->pushHandler(self::build_handler($log_dir));

        $logger->pushProcessor(static function (LogRecord $record): LogRecord {
            $context = [];

            if (\function_exists('get_current_user_id')) {
                $context['user_id'] = (int) get_current_user_id();
            }

            $request_uri = $_SERVER['REQUEST_URI'] ?? null;
            if (is_string($request_uri)) {
                if (\function_exists('wp_unslash')) {
                    $request_uri = (string) wp_unslash($request_uri);
                }

                if (\function_exists('sanitize_text_field')) {
                    $request_uri = (string) sanitize_text_field($request_uri);
                }

                $context['request_uri'] = $request_uri;
            }

            return $record->with(context: array_merge($record->context, $context));
        });

        return $logger;
    }

    private static function build_handler(string $log_dir): RotatingFileHandler
    {
        $handler = new RotatingFileHandler(
            $log_dir . '/app.log',
            7,
            self::resolve_level(),
            true,
            0664
        );

        $handler->setFormatter(new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            null,
            true,
            true
        ));

        return $handler;
    }

    private static function resolve_log_directory(): string
    {
        if (\defined('WP_CONTENT_DIR')) {
            return rtrim((string) \constant('WP_CONTENT_DIR'), '/\\') . '/uploads/myvh-logs';
        }

        $plugin_root = dirname(__DIR__, 3);

        return dirname($plugin_root, 2) . '/uploads/myvh-logs';
    }

    private static function ensure_log_directory(string $log_dir): void
    {
        if (\function_exists('wp_mkdir_p')) {
            wp_mkdir_p($log_dir);
        } elseif (!is_dir($log_dir)) {
            mkdir($log_dir, 0775, true);
        }

        $htaccess_path = $log_dir . '/.htaccess';
        if (!is_file($htaccess_path)) {
            file_put_contents($htaccess_path, "Deny from all\n", LOCK_EX);
        }
    }

    private static function resolve_level(?string $level_name = null): Level
    {
        if ($level_name === null) {
            $configured_level = myvh_setting('admin.logger_level', 'debug');
            $level_name = is_string($configured_level) ? $configured_level : null;
        }

        $normalized = strtolower(trim((string) $level_name));
        if ($normalized === '') {
            return Level::Debug;
        }

        try {
            return Level::fromName($normalized);
        } catch (\Throwable $e) {
            return Level::Debug;
        }
    }
}

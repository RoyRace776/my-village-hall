<?php

declare(strict_types=1);

namespace MYVH\Infrastructure\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class LoggerFactory
{
    public static function create(): LoggerInterface
    {
        $content_dir = \defined('WP_CONTENT_DIR')
            ? (string) \constant('WP_CONTENT_DIR')
            : dirname(__DIR__, 3) . '/wp-content';
        $log_dir = $content_dir . '/uploads/myvh-logs';
        wp_mkdir_p($log_dir);

        $htaccess_path = $log_dir . '/.htaccess';
        if (!file_exists($htaccess_path)) {
            file_put_contents($htaccess_path, "Deny from all\n");
        }

        $level = (\defined('WP_DEBUG') && \constant('WP_DEBUG'))
            ? Level::Debug
            : Level::Info;

        $handler = new RotatingFileHandler($log_dir . '/app.log', 7, $level, true);
        $handler->setFormatter(new LineFormatter('[%datetime%] %level_name%: %message% %context%' . "\n"));

        $logger = new Logger('myvh');
        $logger->pushHandler($handler);

        $logger->pushProcessor(static function ($record) {
            $context = [
                'user_id' => get_current_user_id(),
                'request_uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_URI'])) : '',
            ];

            if ($record instanceof \Monolog\LogRecord) {
                return $record->with(context: array_merge($record->context, $context));
            }

            if (is_array($record)) {
                $record['context'] = array_merge($record['context'] ?? [], $context);
            }

            return $record;
        });

        return $logger;
    }
}

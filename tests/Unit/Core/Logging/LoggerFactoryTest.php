<?php

declare(strict_types=1);

namespace MYVH\Tests\Unit\Core\Logging;

use Brain\Monkey\Functions;
use Monolog\Handler\TestHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use MYVH\Core\Logging\LoggerFactory;
use MYVH\Tests\Unit\UnitTestCase;

class LoggerFactoryTest extends UnitTestCase
{
    private array $server_snapshot = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!defined('WP_CONTENT_DIR')) {
            define('WP_CONTENT_DIR', sys_get_temp_dir() . '/myvh-test-content');
        }

        $this->server_snapshot = $_SERVER;
        $this->reset_factory_logger();

        Functions\stubs([
            'wp_mkdir_p' => static fn(string $dir): bool => is_dir($dir) || mkdir($dir, 0775, true),
            'myvh_setting' => static fn(string $key, $default = null) => $default,
            'get_current_user_id' => static fn(): int => 42,
            'wp_unslash' => static fn($value) => $value,
            'sanitize_text_field' => static fn($value): string => (string) $value,
        ]);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server_snapshot;
        $this->reset_factory_logger();

        parent::tearDown();
    }

    /** @test */
    public function get_returns_the_same_shared_instance(): void
    {
        $first = LoggerFactory::get();
        $second = LoggerFactory::get();

        $this->assertSame($first, $second);
    }

    /** @test */
    public function refresh_rebuilds_the_shared_logger_instance(): void
    {
        $first = LoggerFactory::get();
        $second = LoggerFactory::refresh();

        $this->assertNotSame($first, $second);
        $this->assertSame($second, LoggerFactory::get());
    }

    /** @test */
    public function refresh_uses_debug_level_when_setting_is_invalid(): void
    {
        Functions\when('myvh_setting')->alias(static fn(string $key, $default = null): string => 'not-a-level');

        $logger = LoggerFactory::refresh();
        $this->assertInstanceOf(Logger::class, $logger);

        /** @var Logger $logger */
        $handlers = $logger->getHandlers();

        $this->assertNotEmpty($handlers);
        $this->assertInstanceOf(RotatingFileHandler::class, $handlers[0]);

        /** @var RotatingFileHandler $primary_handler */
        $primary_handler = $handlers[0];
        $this->assertSame(Level::Debug, $primary_handler->getLevel());
    }

    /** @test */
    public function processor_adds_user_id_and_request_uri_context(): void
    {
        $_SERVER['REQUEST_URI'] = '/portal/bookings?foo=bar';

        $logger = LoggerFactory::refresh();
        $this->assertInstanceOf(Logger::class, $logger);

        /** @var Logger $logger */
        $capture = new TestHandler(Level::Debug);
        $logger->pushHandler($capture);

        $logger->info('Processor context test');

        $records = $capture->getRecords();
        $this->assertNotEmpty($records);

        $record = end($records);
        $this->assertSame(42, $record['context']['user_id'] ?? null);
        $this->assertSame('/portal/bookings?foo=bar', $record['context']['request_uri'] ?? null);
    }

    private function reset_factory_logger(): void
    {
        $reflection = new \ReflectionClass(LoggerFactory::class);
        $property = $reflection->getProperty('logger');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }
}

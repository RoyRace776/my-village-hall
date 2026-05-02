<?php

namespace MYVH\Tests\Unit\AutoInvoicing;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\AutoInvoicing\AutoInvoice;
use MYVH\AutoInvoicing\AutoInvoicingAjaxController;
use MYVH\Portal\ClientAdminService;
use MYVH\Tests\Unit\UnitTestCase;

class AutoInvoicingAjaxControllerTest extends UnitTestCase
{
    /** @var AutoInvoice&\Mockery\MockInterface */
    private $auto_invoice;
    /** @var ClientAdminService&\Mockery\MockInterface */
    private $client_admin_service;
    private AutoInvoicingAjaxController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auto_invoice         = Mockery::mock(AutoInvoice::class);
        $this->client_admin_service = Mockery::mock(ClientAdminService::class);
        $this->controller           = new AutoInvoicingAjaxController($this->auto_invoice, $this->client_admin_service);

        Functions\stubs([
            'check_ajax_referer'  => true,
            'is_user_logged_in'   => true,
            'current_user_can'    => true,
            '_n'                  => fn($s, $p, $n) => $n === 1 ? $s : $p,
            'wp_send_json_error'  => function ($data, $code = 400) {
                throw new \RuntimeException("json_error:$code");
            },
            'wp_send_json_success' => function ($data) {
                throw new \RuntimeException('json_success:' . ($data['invoice_count'] ?? '?'));
            },
        ]);
    }

    // ── run_admin ─────────────────────────────────────────────────────────

    /** @test */
    public function run_admin_returns_error_when_user_lacks_permission(): void
    {
        Functions\when('current_user_can')->justReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('json_error:403');

        $this->controller->run_admin();
    }

    /** @test */
    public function run_admin_returns_error_when_not_logged_in(): void
    {
        Functions\when('is_user_logged_in')->justReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('json_error:403');

        $this->controller->run_admin();
    }

    /** @test */
    public function run_admin_sends_success_with_invoice_count(): void
    {
        $this->auto_invoice->shouldReceive('generate')->once()->andReturn(4);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('json_success:4');

        $this->controller->run_admin();
    }

    /** @test */
    public function run_admin_sends_error_when_generate_throws(): void
    {
        $this->auto_invoice->shouldReceive('generate')
            ->once()
            ->andThrow(new \Exception('DB failure'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('json_error:500');

        $this->controller->run_admin();
    }
}

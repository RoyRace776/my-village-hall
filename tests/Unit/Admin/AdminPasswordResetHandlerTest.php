<?php

namespace MYVH\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Admin\AdminPasswordResetHandler;
use MYVH\Customers\CustomerService;
use MYVH\Tests\Unit\UnitTestCase;

class AdminPasswordResetHandlerTest extends UnitTestCase
{
    /** @var CustomerService&\Mockery\MockInterface */
    private $customer_service;
    private AdminPasswordResetHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer_service = Mockery::mock(CustomerService::class);
        $this->handler = new AdminPasswordResetHandler($this->customer_service);

        Functions\stubs([
            'current_user_can'    => false,
            'check_ajax_referer'  => true,
            'wp_send_json_error'  => function ($msg, $code = 400) {
                throw new \RuntimeException("json_error:$code");
            },
            'wp_send_json_success' => function ($data) {
                throw new \RuntimeException('json_success');
            },
        ]);
    }

    // ── permission guard ─────────────────────────────────────────────────

    /** @test */
    public function it_rejects_when_user_lacks_manage_myvh_capability(): void
    {
        Functions\when('current_user_can')->justReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('json_error:403');

        $this->handler->send_password_reset_email();
    }

    // ── validation ───────────────────────────────────────────────────────

    /** @test */
    public function it_rejects_when_customer_id_is_zero(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        $_POST['customer_id'] = '0';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('json_error:400');

        $this->handler->send_password_reset_email();
    }

    /** @test */
    public function it_rejects_when_customer_not_found(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        $_POST['customer_id'] = '99';

        $this->customer_service->shouldReceive('get')->with(99)->andReturn([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('json_error:404');

        $this->handler->send_password_reset_email();
    }

    /** @test */
    public function it_rejects_when_customer_has_no_wp_user(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        $_POST['customer_id'] = '5';

        $this->customer_service->shouldReceive('get')
            ->with(5)
            ->andReturn(['Id' => 5, 'Name' => 'Test']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('json_error:400');

        $this->handler->send_password_reset_email();
    }

    protected function tearDown(): void
    {
        unset($_POST['customer_id']);
        parent::tearDown();
    }
}

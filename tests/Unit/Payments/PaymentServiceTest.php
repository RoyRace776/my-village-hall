<?php

namespace MYVH\Tests\Unit\Payments;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Email\EmailService;
use MYVH\Invoices\InvoiceService;
use MYVH\Payments\PaymentRepository;
use MYVH\Payments\PaymentService;
use MYVH\Tests\Unit\UnitTestCase;

class PaymentServiceTest extends UnitTestCase
{
    /** @var PaymentRepository&\Mockery\MockInterface */
    private $repo;
    /** @var InvoiceService&\Mockery\MockInterface */
    private $invoice_service;
    /** @var EmailService&\Mockery\MockInterface */
    private $email_service;
    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo            = Mockery::mock(PaymentRepository::class);
        $this->invoice_service = Mockery::mock(InvoiceService::class);
        $this->email_service   = Mockery::mock(EmailService::class);
        $this->service         = new PaymentService($this->repo, $this->invoice_service, $this->email_service);

        Functions\stubs([
            'sanitize_key'           => fn($v) => (string) $v,
            'sanitize_text_field'    => fn($v) => (string) $v,
            'sanitize_textarea_field' => fn($v) => (string) $v,
            'sanitize_email'         => fn($v) => (string) $v,
            'is_email'               => fn($v) => is_string($v) && strpos($v, '@') !== false,
            'current_time'           => '2026-05-02',
            'is_wp_error'            => fn($v) => $v instanceof \WP_Error,
        ]);
    }

    // ── get_valid_methods ─────────────────────────────────────────────────

    /** @test */
    public function get_valid_methods_returns_all_method_keys(): void
    {
        $methods = $this->service->get_valid_methods();

        $this->assertContains('cash', $methods);
        $this->assertContains('card', $methods);
        $this->assertContains('cheque', $methods);
        $this->assertContains('transfer', $methods);
        $this->assertContains('other', $methods);
    }

    /** @test */
    public function get_method_label_capitalises_method_name(): void
    {
        $this->assertSame('Cash', $this->service->get_method_label('cash'));
        $this->assertSame('Card', $this->service->get_method_label('card'));
    }

    // ── create – validation ───────────────────────────────────────────────

    /** @test */
    public function create_returns_error_when_invoice_id_missing(): void
    {
        $result = $this->service->create(['payment_amount' => 50, 'payment_method' => 'cash', 'payment_date' => '2026-05-01']);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function create_returns_error_when_amount_is_zero(): void
    {
        $result = $this->service->create(['invoice_id' => 1, 'payment_amount' => 0, 'payment_method' => 'cash', 'payment_date' => '2026-05-01']);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function create_returns_error_for_invalid_payment_method(): void
    {
        $result = $this->service->create(['invoice_id' => 1, 'payment_amount' => 50, 'payment_method' => 'bitcoin', 'payment_date' => '2026-05-01']);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function create_returns_error_for_invalid_date(): void
    {
        $result = $this->service->create(['invoice_id' => 1, 'payment_amount' => 50, 'payment_method' => 'cash', 'payment_date' => 'not-a-date']);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function create_returns_error_when_invoice_not_found(): void
    {
        $this->invoice_service->shouldReceive('get')->with(1)->andReturn(null);

        $result = $this->service->create([
            'invoice_id'     => 1,
            'payment_amount' => 50,
            'payment_method' => 'cash',
            'payment_date'   => '2026-05-01',
        ]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('not_found', $result->get_error_code());
    }

    /** @test */
    public function create_returns_error_when_invoice_already_fully_paid(): void
    {
        $this->invoice_service->shouldReceive('get')->with(1)->andReturnUsing(static fn(): array => ['AmountDue' => 0]);

        $result = $this->service->create([
            'invoice_id'     => 1,
            'payment_amount' => 50,
            'payment_method' => 'cash',
            'payment_date'   => '2026-05-01',
        ]);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function create_returns_error_when_amount_exceeds_balance(): void
    {
        $this->invoice_service->shouldReceive('get')->with(1)->andReturnUsing(static fn(): array => ['AmountDue' => 30.00]);

        $result = $this->service->create([
            'invoice_id'     => 1,
            'payment_amount' => 50,
            'payment_method' => 'cash',
            'payment_date'   => '2026-05-01',
        ]);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function create_records_payment_when_valid(): void
    {
        $this->invoice_service->shouldReceive('get')
            ->with(1)
            ->andReturnUsing(static fn(): array => ['AmountDue' => 100.00]);

        $this->invoice_service->shouldReceive('record_payment')
            ->once()
            ->with(1, 50.0, 'cash', '2026-05-01', '', '')
            ->andReturn(11);

        $result = $this->service->create([
            'invoice_id'     => 1,
            'payment_amount' => 50,
            'payment_method' => 'cash',
            'payment_date'   => '2026-05-01',
        ]);

        $this->assertSame(11, $result);
    }

    // ── delete ───────────────────────────────────────────────────────────

    /** @test */
    public function delete_returns_error_when_payment_id_is_zero(): void
    {
        $result = $this->service->delete(0);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function delete_delegates_to_invoice_service(): void
    {
        $this->invoice_service->shouldReceive('delete_payment')->with(5)->andReturn(true);

        $result = $this->service->delete(5);

        $this->assertTrue($result);
    }

    /** @test */
    public function send_receipt_returns_error_when_payment_is_missing(): void
    {
        $this->repo->shouldReceive('get_by_id')->with(99)->andReturn(null);

        $result = $this->service->send_receipt(99);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('not_found', $result->get_error_code());
    }

    /** @test */
    public function send_receipt_returns_error_when_invoice_has_no_recipient(): void
    {
        $this->repo->shouldReceive('get_by_id')->with(7)->andReturnUsing(static fn(): array => [
            'Id' => 7,
            'InvoiceId' => 15,
            'Amount' => 20.00,
            'PaymentDate' => '2026-05-01',
            'PaymentMethod' => 'cash',
            'TransactionReference' => '',
            'Notes' => '',
        ]);

        $this->invoice_service->shouldReceive('get_detail')->with(15)->andReturnUsing(static fn(): array => [
            'Id' => 15,
            'InvoiceNumber' => 'INV-15',
            'BillingEmail' => '',
            'CustomerEmail' => '',
        ]);

        $result = $this->service->send_receipt(7);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function send_receipt_sends_payment_receipt_email_when_data_is_valid(): void
    {
        $this->repo->shouldReceive('get_by_id')->with(7)->andReturnUsing(static fn(): array => [
            'Id' => 7,
            'InvoiceId' => 15,
            'Amount' => 20.00,
            'PaymentDate' => '2026-05-01',
            'PaymentMethod' => 'card',
            'TransactionReference' => 'ABC123',
            'Notes' => 'Paid at desk',
        ]);

        $invoice = [
            'Id' => 15,
            'InvoiceNumber' => 'INV-15',
            'Status' => 'part-paid',
            'TotalAmount' => 100.00,
            'AmountDue' => 80.00,
            'DueDate' => '2026-06-01',
            'BillingEmail' => 'billing@example.com',
            'CustomerEmail' => 'customer@example.com',
            'BillingName' => 'Alex Customer',
            'BillingOrganisationName' => 'Test Org',
        ];

        $this->invoice_service->shouldReceive('get_detail')->with(15)->andReturnUsing(static fn() => $invoice);
        $this->invoice_service->shouldReceive('get_status_label')->with('part-paid', $invoice)->andReturn('Part Paid');

        $this->email_service->shouldReceive('get_branding')->once()->andReturnUsing(static fn(): array => [
            'site_name' => 'Test Site',
            'site_url' => 'https://example.test',
            'logo_url' => '',
        ]);

        $this->email_service->shouldReceive('send')->once()->withArgs(function (array $args): bool {
            $this->assertSame('billing@example.com', $args['to'] ?? '');
            $this->assertSame('payment-receipt', $args['template'] ?? '');
            $this->assertSame('20.00', $args['template_vars']['payment_amount'] ?? '');
            $this->assertSame('Card', $args['template_vars']['payment_method'] ?? '');
            return true;
        })->andReturn(true);

        $result = $this->service->send_receipt(7);

        $this->assertTrue($result);
    }
}

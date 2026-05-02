<?php

namespace MYVH\Tests\Unit\Customers;

use Brain\Monkey\Functions;
use MYVH\Customers\CustomerRequestValidator;
use MYVH\Tests\Unit\UnitTestCase;

class CustomerRequestValidatorTest extends UnitTestCase
{
    private CustomerRequestValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        Functions\stubs([
            'is_email'   => fn($v) => str_contains((string) $v, '@'),
            'is_wp_error' => fn($v) => $v instanceof \WP_Error,
        ]);

        $this->validator = new CustomerRequestValidator();
    }

    /** @test */
    public function it_passes_for_valid_data(): void
    {
        $result = $this->validator->validate(['name' => 'Alice', 'email' => 'alice@example.com']);

        $this->assertTrue($result);
    }

    /** @test */
    public function it_fails_when_name_missing(): void
    {
        $result = $this->validator->validate(['email' => 'alice@example.com']);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function it_fails_when_email_missing(): void
    {
        $result = $this->validator->validate(['name' => 'Alice']);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function it_fails_for_invalid_email(): void
    {
        $result = $this->validator->validate(['name' => 'Alice', 'email' => 'not-an-email']);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }
}

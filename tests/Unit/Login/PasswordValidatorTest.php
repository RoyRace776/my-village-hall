<?php

namespace MYVH\Tests\Unit\Login;

use MYVH\Login\PasswordValidator;
use MYVH\Tests\Unit\UnitTestCase;

class PasswordValidatorTest extends UnitTestCase {
    private PasswordValidator $validator;

    protected function setUp(): void {
        parent::setUp();

        $this->validator = new PasswordValidator();
    }

    /** @test */
    public function validate_returns_null_for_a_valid_password(): void {
        $this->assertNull($this->validator->validate('Valid1!23'));
    }

    /** @test */
    public function validate_returns_error_when_password_is_too_short(): void {
        $this->assertSame(
            'Password must be at least 9 characters.',
            $this->validator->validate('Aa1!abcd')
        );
    }

    /** @test */
    public function validate_returns_error_when_password_has_no_uppercase_letter(): void {
        $this->assertSame(
            'Password must include at least one uppercase letter.',
            $this->validator->validate('valid1!23')
        );
    }

    /** @test */
    public function validate_returns_error_when_password_has_no_lowercase_letter(): void {
        $this->assertSame(
            'Password must include at least one lowercase letter.',
            $this->validator->validate('VALID1!23')
        );
    }

    /** @test */
    public function validate_returns_error_when_password_has_no_number(): void {
        $this->assertSame(
            'Password must include at least one number.',
            $this->validator->validate('Valid!!!X')
        );
    }

    /** @test */
    public function validate_returns_error_when_password_has_no_symbol(): void {
        $this->assertSame(
            'Password must include at least one symbol.',
            $this->validator->validate('Valid1234')
        );
    }
}
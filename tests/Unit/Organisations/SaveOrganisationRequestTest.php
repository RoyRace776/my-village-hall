<?php

namespace MYVH\Tests\Unit\Organisations;

use MYVH\Organisations\SaveOrganisationRequest;
use MYVH\Tests\Unit\UnitTestCase;

class SaveOrganisationRequestTest extends UnitTestCase {
    protected function setUp(): void {
        parent::setUp();

        \Brain\Monkey\Functions\stubs([
            'sanitize_email' => fn($v) => (string) $v,
            'esc_url_raw' => fn($v) => (string) $v,
        ]);
    }

    /** @test */
    public function restricted_fields_are_not_mapped_for_portal_context(): void {
        $mapped = SaveOrganisationRequest::from_post([
            'organisation_type_id' => 9,
            'is_default' => 1,
            'is_active' => 1,
            'default_public' => 1,
            'name' => 'Portal org',
            'contact_email' => 'portal@example.org',
            'contact_phone' => '01999 000000',
            'send_booking_emails_to_organisation' => 1,
        ], false);

        $this->assertArrayNotHasKey('organisation_type_id', $mapped);
        $this->assertArrayNotHasKey('is_default', $mapped);
        $this->assertArrayNotHasKey('is_active', $mapped);
        $this->assertArrayNotHasKey('default_public', $mapped);
        $this->assertSame('Portal org', $mapped['name']);
        $this->assertSame(1, $mapped['send_booking_emails_to_organisation']);
    }
}

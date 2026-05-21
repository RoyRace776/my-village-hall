<?php

namespace MYVH\Tests\Unit\Venues;

use Brain\Monkey\Functions;
use MYVH\Rooms\RoomRepository;
use MYVH\Tests\Unit\UnitTestCase;
use MYVH\Venues\VenueHoursRepository;
use MYVH\Venues\VenueRepository;
use MYVH\Venues\VenueService;

class VenueServiceTest extends UnitTestCase {
    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            'sanitize_text_field' => static fn($value): string => (string) $value,
            'sanitize_email' => static fn($value): string => (string) $value,
            '__' => static fn($text): string => (string) $text,
        ]);
    }

    /** @test */
    public function save_persists_contact_email_when_provided(): void {
        $repo = \Mockery::mock(VenueRepository::class);
        $repo->shouldReceive('create')->once()->with(\Mockery::on(static function (array $record): bool {
            return ($record['Name'] ?? '') === 'Village Hall'
                && ($record['ContactEmail'] ?? '') === 'bookings@example.test'
                && ($record['OpeningTime'] ?? '') === '08:00'
                && ($record['ClosingTime'] ?? '') === '22:00';
        }))->andReturn(77);

        $venue_hours_repository = \Mockery::mock(VenueHoursRepository::class);
        $venue_hours_repository->shouldReceive('replace_for_venue')->never();

        $room_repository = \Mockery::mock(RoomRepository::class);

        $service = new VenueService($repo, $venue_hours_repository, $room_repository);

        $result = $service->save([
            'name' => 'Village Hall',
            'contact_email' => 'bookings@example.test',
            'opening_time' => '08:00',
            'closing_time' => '22:00',
        ]);

        $this->assertSame(77, $result);
    }
}
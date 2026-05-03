<?php

namespace MYVH\Tests\Unit\Customers;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Bookings\BookingRepository;
use MYVH\Customers\CustomerRepository;
use MYVH\Customers\CustomerService;
use MYVH\Organisations\OrganisationMemberRepository;
use MYVH\Organisations\OrganisationRepository;
use MYVH\Tests\Unit\UnitTestCase;

class CustomerServiceTest extends UnitTestCase
{
    /** @var CustomerRepository&\Mockery\MockInterface */
    private $repo;
    /** @var BookingRepository&\Mockery\MockInterface */
    private $booking_repo;
    /** @var OrganisationRepository&\Mockery\MockInterface */
    private $org_repo;
    /** @var OrganisationMemberRepository&\Mockery\MockInterface */
    private $org_member_repo;
    private CustomerService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo            = Mockery::mock(CustomerRepository::class);
        $this->booking_repo    = Mockery::mock(BookingRepository::class);
        $this->org_repo        = Mockery::mock(OrganisationRepository::class);
        $this->org_member_repo = Mockery::mock(OrganisationMemberRepository::class);

        $this->service = new CustomerService(
            $this->repo,
            $this->booking_repo,
            $this->org_repo,
            $this->org_member_repo
        );

        Functions\stubs([
            'is_email'                  => fn($v) => str_contains((string) $v, '@'),
            'sanitize_email'            => fn($v) => (string) $v,
            'current_user_can'          => false,
            'is_multisite'              => false,
            'get_current_blog_id'       => 1,
            'add_user_to_blog'          => true,
            'wp_insert_user'            => 10,
            'wp_generate_password'      => 'rand-pass',
            'wp_delete_user'            => true,
            'wp_update_user'            => 1,
            'is_wp_error'               => fn($v) => $v instanceof \WP_Error,
            'get_user_by'               => null,
        ]);
    }

    // ── simple delegation ────────────────────────────────────────────────

    /** @test */
    public function get_all_delegates_to_repository(): void
    {
        $this->repo->shouldReceive('get_all')->with([])->andReturn([['Id' => 1]]);

        $result = $this->service->get_all();

        $this->assertCount(1, $result);
    }

    /** @test */
    public function get_returns_customer_by_id(): void
    {
        $this->repo->shouldReceive('get_by_id')->with(5)->andReturn(['Id' => 5]);

        $result = $this->service->get(5);

        $this->assertSame(5, $result['Id']);
    }

    /** @test */
    public function search_delegates_to_repository(): void
    {
        $this->repo->shouldReceive('search')->with('ali')->andReturn([['Id' => 1]]);

        $result = $this->service->search('ali');

        $this->assertCount(1, $result);
    }

    // ── save validation ──────────────────────────────────────────────────

    /** @test */
    public function save_returns_error_when_name_missing(): void
    {
        $result = $this->service->save(['email' => 'a@b.com']);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function save_returns_error_when_email_missing(): void
    {
        $result = $this->service->save(['name' => 'Alice']);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function save_returns_error_for_invalid_email(): void
    {
        $result = $this->service->save(['name' => 'Alice', 'email' => 'not-an-email']);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function save_returns_error_when_email_already_exists_for_different_customer(): void
    {
        $this->repo->shouldReceive('get_by_email')
            ->with('alice@example.com')
            ->andReturn(['Id' => 99]);

        $result = $this->service->save([
            'name'        => 'Alice',
            'email'       => 'alice@example.com',
            'customer_id' => 1,
        ]);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function save_updates_existing_customer_and_returns_id(): void
    {
        $this->repo->shouldReceive('get_by_email')->andReturn(null);
        $this->repo->shouldReceive('update')->once()->andReturn(1);

        $result = $this->service->save([
            'name'        => 'Alice',
            'email'       => 'alice@example.com',
            'customer_id' => 5,
        ]);

        $this->assertSame(5, $result);
    }

    /** @test */
    public function save_persists_email_verified_as_zero_when_false(): void
    {
        $this->repo->shouldReceive('get_by_email')->andReturn(null);
        $this->repo->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn($record) => (int) ($record['EmailVerified'] ?? -1) === 0), ['Id' => 5])
            ->andReturn(1);

        $result = $this->service->save([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'customer_id' => 5,
            'email_verified' => false,
        ]);

        $this->assertSame(5, $result);
    }

    /** @test */
    public function save_returns_error_when_update_fails(): void
    {
        $this->repo->shouldReceive('get_by_email')->andReturn(null);
        $this->repo->shouldReceive('update')->once()->andReturn(false);

        $result = $this->service->save([
            'name'        => 'Alice',
            'email'       => 'alice@example.com',
            'customer_id' => 5,
        ]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('database', $result->get_error_code());
    }

    // ── create_customer ──────────────────────────────────────────────────

    /** @test */
    public function create_customer_creates_wp_user_and_inserts_customer(): void
    {
        Functions\when('get_user_by')->justReturn(null);
        Functions\when('wp_insert_user')->justReturn(10);
        Functions\when('is_wp_error')->justReturn(false);

        $this->repo->shouldReceive('create')->once()->andReturn(7);
        $this->org_repo->shouldReceive('get_default')->once()->andReturn([]);

        $id = $this->service->create_customer(['Name' => 'Bob', 'Email' => 'bob@example.com']);

        $this->assertSame(7, $id);
    }

    /** @test */
    public function create_customer_reuses_existing_wp_user(): void
    {
        $user = Mockery::mock('WP_User');
        $user->ID = 3;
        Functions\when('get_user_by')->justReturn($user);

        $this->repo->shouldReceive('create')->once()->andReturn(8);
        $this->org_repo->shouldReceive('get_default')->once()->andReturn([]);

        $id = $this->service->create_customer(['Name' => 'Carol', 'Email' => 'carol@example.com']);

        $this->assertSame(8, $id);
    }

    /** @test */
    public function create_customer_defaults_email_verified_to_zero_when_missing(): void
    {
        Functions\when('get_user_by')->justReturn(null);
        Functions\when('wp_insert_user')->justReturn(10);
        Functions\when('is_wp_error')->justReturn(false);

        $this->repo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($record) => (int) ($record['EmailVerified'] ?? -1) === 0))
            ->andReturn(11);
        $this->org_repo->shouldReceive('get_default')->once()->andReturn([]);

        $id = $this->service->create_customer([
            'Name' => 'Dana',
            'Email' => 'dana@example.com',
        ]);

        $this->assertSame(11, $id);
    }

    // ── delete ───────────────────────────────────────────────────────────

    /** @test */
    public function delete_returns_error_when_bookings_exist(): void
    {
        $this->booking_repo->shouldReceive('count_by_customer')->with(5)->andReturn(2);

        $result = $this->service->delete(5);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function delete_removes_customer_when_no_bookings(): void
    {
        $this->booking_repo->shouldReceive('count_by_customer')->with(5)->andReturn(0);
        $this->repo->shouldReceive('delete')->with(5)->andReturn(1);

        $result = $this->service->delete(5);

        $this->assertSame(1, $result);
    }
}

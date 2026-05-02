<?php

namespace MYVH\Tests\Unit\Customers;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Customers\CustomerRepository;
use MYVH\Customers\CustomerUserSync;
use MYVH\Tests\Unit\UnitTestCase;

class CustomerUserSyncTest extends UnitTestCase
{
    /** @var CustomerRepository&\Mockery\MockInterface */
    private $repo;
    private CustomerUserSync $sync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = Mockery::mock(CustomerRepository::class);
        $this->sync = new CustomerUserSync($this->repo);

        Functions\stubs([
            'sanitize_email' => fn($v) => (string) $v,
        ]);
    }

    // ── register ─────────────────────────────────────────────────────────

    /** @test */
    public function register_hooks_into_profile_update_and_user_register(): void
    {
        Functions\expect('add_action')
            ->with('profile_update', Mockery::type('array'), 10, 2)
            ->once();

        Functions\expect('add_action')
            ->with('user_register', Mockery::type('array'), 10, 1)
            ->once();

        $this->sync->register();
        $this->addToAssertionCount(1);
    }

    // ── sync_customer_from_user ──────────────────────────────────────────

    /** @test */
    public function sync_does_nothing_when_user_not_found(): void
    {
        Functions\when('get_userdata')->justReturn(null);

        $this->repo->shouldNotReceive('get_by_user_id');

        $this->sync->sync_customer_from_user(99);
        $this->assertTrue(true);
    }

    /** @test */
    public function sync_does_nothing_when_no_customer_linked(): void
    {
        $user = $this->make_wp_user(1, 'Alice', 'alice@example.com');
        Functions\when('get_userdata')->justReturn($user);

        $this->repo->shouldReceive('get_by_user_id')->with(1)->andReturn(null);
        $this->repo->shouldNotReceive('update');

        $this->sync->sync_customer_from_user(1);
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function sync_updates_customer_name_and_email(): void
    {
        $user = $this->make_wp_user(1, 'Alice Updated', 'alice2@example.com');
        Functions\when('get_userdata')->justReturn($user);

        $this->repo->shouldReceive('get_by_user_id')
            ->with(1)
            ->andReturn(['Id' => 5, 'Name' => 'Alice', 'Email' => 'alice@example.com']);

        $this->repo->shouldReceive('update')
            ->once()
            ->with(
                Mockery::on(fn($data) =>
                    $data['Name'] === 'Alice Updated' &&
                    $data['Email'] === 'alice2@example.com'
                ),
                ['Id' => 5]
            );

        $this->sync->sync_customer_from_user(1);
        $this->addToAssertionCount(1);
    }

    // ── link_or_sync_customer_from_user ──────────────────────────────────

    /** @test */
    public function link_does_nothing_when_user_not_found(): void
    {
        Functions\when('get_userdata')->justReturn(null);

        $this->repo->shouldNotReceive('get_by_user_id');

        $this->sync->link_or_sync_customer_from_user(99);
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function link_does_nothing_when_customer_already_linked(): void
    {
        $user = $this->make_wp_user(2, 'Bob', 'bob@example.com');
        Functions\when('get_userdata')->justReturn($user);

        $this->repo->shouldReceive('get_by_user_id')
            ->with(2)
            ->andReturn(['Id' => 10]);

        $this->repo->shouldNotReceive('update');

        $this->sync->link_or_sync_customer_from_user(2);
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function link_links_existing_customer_by_email(): void
    {
        $user = $this->make_wp_user(3, 'Carol', 'carol@example.com');
        Functions\when('get_userdata')->justReturn($user);

        $this->repo->shouldReceive('get_by_user_id')->with(3)->andReturn(null);
        $this->repo->shouldReceive('get_by_email')
            ->with('carol@example.com')
            ->andReturn(['Id' => 20]);

        $this->repo->shouldReceive('update')
            ->once()
            ->with(
                Mockery::on(fn($data) => $data['WPUserId'] === 3),
                ['Id' => 20]
            );

        $this->sync->link_or_sync_customer_from_user(3);
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function link_does_nothing_when_no_customer_with_that_email(): void
    {
        $user = $this->make_wp_user(4, 'Dave', 'dave@example.com');
        Functions\when('get_userdata')->justReturn($user);

        $this->repo->shouldReceive('get_by_user_id')->with(4)->andReturn(null);
        $this->repo->shouldReceive('get_by_email')
            ->with('dave@example.com')
            ->andReturn(null);

        $this->repo->shouldNotReceive('update');

        $this->sync->link_or_sync_customer_from_user(4);
        $this->addToAssertionCount(1);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function make_wp_user(int $id, string $display_name, string $email): \WP_User
    {
        $user = Mockery::mock('WP_User');
        $user->ID           = $id;
        $user->display_name = $display_name;
        $user->user_email   = $email;
        return $user;
    }
}

<?php

namespace {
    if (!class_exists('wpdb')) {
        class wpdb {}
    }

    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }
}

namespace MYVH\Tests\Unit\Pricing {

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Pricing\DiscountRepository;
use MYVH\Tests\Unit\UnitTestCase;

/**
 * Security regression tests for DiscountRepository.
 *
 * The ORDER BY clause previously interpolated $args['orderby'] and
 * $args['order'] directly into SQL without any sanitisation, making it
 * trivially injectable. These tests verify that the allowlist fix prevents
 * known payloads from reaching the query string.
 */
class DiscountRepositorySecurityTest extends UnitTestCase
{
    /** @var \Mockery\MockInterface&\wpdb */
    private $wpdb;
    private DiscountRepository $repo;
    private string $last_query = '';

    protected function setUp(): void {
        parent::setUp();

        Functions\when('wp_parse_args')->alias(fn($args, $defaults) => array_merge($defaults, (array) $args));

        $this->wpdb = Mockery::mock('wpdb');
        $this->wpdb->prefix = 'wp_';

        $self = $this;
        $this->wpdb->shouldReceive('get_results')
            ->andReturnUsing(function ($query) use ($self): array {
                $self->last_query = $query;
                return [];
            })
            ->byDefault();

        $this->repo = new DiscountRepository($this->wpdb);
    }

    // -------------------------------------------------------------------------
    // Malicious orderby payloads must not appear in the generated SQL.
    // -------------------------------------------------------------------------

    /** @dataProvider injection_orderby_payloads */
    public function test_get_all_rejects_injected_orderby_and_falls_back_to_id(string $payload): void {
        $this->repo->get_all(['orderby' => $payload, 'order' => 'ASC']);

        $query = $this->last_query;

        // The payload itself must not appear in the query.
        $this->assertStringNotContainsStringIgnoringCase(
            $payload,
            $query,
            "Injection payload \"{$payload}\" must not appear in the generated SQL."
        );

        // A safe fallback column must be used instead.
        $this->assertMatchesRegularExpression(
            '/ORDER BY `Id`/i',
            $query,
            'Fallback to default column `Id` expected for invalid orderby.'
        );
    }

    public static function injection_orderby_payloads(): array {
        return [
            'DROP payload'         => ["Id; DROP TABLE myvh_discounts;--"],
            'SLEEP injection'      => ["Id, SLEEP(5)--"],
            "OR 1=1 injection"     => ["Name' OR '1'='1"],
            'UNION injection'      => ["1 UNION SELECT password FROM wp_users"],
            'subquery injection'   => ["(SELECT 1)"],
            'comment injection'    => ["Id/*comment*/"],
            'non-existent column'  => ["NonExistentColumn"],
            // Note: "`Id`" is intentionally excluded — sanitize_identifier rejects backtick-
            // wrapped input, but the safe fallback also renders as `Id`, making a
            // string-equality check a false positive. The rejection itself is verified
            // via sanitize_identifier tests in RepositorySecurityTest.
        ];
    }

    // -------------------------------------------------------------------------
    // Malicious order direction payloads must not appear in the query.
    // -------------------------------------------------------------------------

    /** @dataProvider injection_order_payloads */
    public function test_get_all_rejects_injected_order_direction_and_falls_back_to_asc(string $payload): void {
        $this->repo->get_all(['orderby' => 'Id', 'order' => $payload]);

        $query = $this->last_query;

        $this->assertStringNotContainsStringIgnoringCase(
            'DROP',
            $query,
            "SQL injection keyword must not appear in the generated query."
        );

        $this->assertMatchesRegularExpression(
            '/ORDER BY `Id` ASC/i',
            $query,
            'Fallback to ASC expected for invalid order direction.'
        );
    }

    public static function injection_order_payloads(): array {
        return [
            'DROP TABLE'           => ["ASC; DROP TABLE myvh_discounts;--"],
            'UNION injection'      => ["ASC UNION SELECT 1"],
            'random word'          => ['RANDOM'],
            'empty string'         => [''],
        ];
    }

    // -------------------------------------------------------------------------
    // Valid inputs must produce correct SQL.
    // -------------------------------------------------------------------------

    /** @dataProvider allowed_columns */
    public function test_get_all_includes_valid_column_in_order_by(string $column): void {
        $this->repo->get_all(['orderby' => $column, 'order' => 'DESC']);

        $this->assertMatchesRegularExpression(
            '/ORDER BY `' . preg_quote($column, '/') . '` DESC/i',
            $this->last_query,
            "Allowed column `{$column}` should appear verbatim in ORDER BY."
        );
    }

    public static function allowed_columns(): array {
        return [
            'Id'        => ['Id'],
            'Code'      => ['Code'],
            'Amount'    => ['Amount'],
            'Type'      => ['Type'],
            'IsActive'  => ['IsActive'],
            'CreatedAt' => ['CreatedAt'],
        ];
    }
}

} // end namespace MYVH\Tests\Unit\Pricing

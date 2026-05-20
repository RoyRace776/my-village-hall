<?php

declare(strict_types=1);

use MYVH\Bookings\Services\BookingChargeService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

$apply = in_array('--apply', $argv, true);
$currentSiteOnly = in_array('--current-site-only', $argv, true);

$scriptDir = __DIR__;

/**
 * Resolve LocalWP DB settings for the current site when running in CLI.
 * This avoids relying on localhost:3306 when Local maps MySQL to another port.
 */
$resolveLocalDbConfig = static function (string $toolsDir): array {
    $siteRootDir = dirname($toolsDir, 6);
    $siteFolder = basename($siteRootDir);

    $sitesJsonPath = getenv('APPDATA') . DIRECTORY_SEPARATOR . 'Local' . DIRECTORY_SEPARATOR . 'sites.json';
    if ($siteFolder === '' || !is_file($sitesJsonPath)) {
        return [];
    }

    $raw = file_get_contents($sitesJsonPath);
    if (!is_string($raw) || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    foreach ($decoded as $site) {
        if (!is_array($site)) {
            continue;
        }

        $path = (string) ($site['path'] ?? '');
        if ($path === '' || stripos($path, $siteFolder) === false) {
            continue;
        }

        $mysql = is_array($site['mysql'] ?? null) ? $site['mysql'] : [];
        $services = is_array($site['services'] ?? null) ? $site['services'] : [];
        $mysqlService = is_array($services['mysql'] ?? null) ? $services['mysql'] : [];
        $ports = is_array($mysqlService['ports'] ?? null) ? $mysqlService['ports'] : [];
        $mysqlPorts = is_array($ports['MYSQL'] ?? null) ? $ports['MYSQL'] : [];
        $port = isset($mysqlPorts[0]) ? (int) $mysqlPorts[0] : 0;

        if ($port <= 0) {
            continue;
        }

        return [
            'DB_HOST' => '127.0.0.1:' . $port,
            'DB_NAME' => (string) ($mysql['database'] ?? 'local'),
            'DB_USER' => (string) ($mysql['user'] ?? 'root'),
            'DB_PASSWORD' => (string) ($mysql['password'] ?? 'root'),
        ];
    }

    return [];
};

$localDb = $resolveLocalDbConfig($scriptDir);
$suppressDbConstantWarnings = false;
if (!empty($localDb)) {
    foreach ($localDb as $const => $value) {
        if (!defined($const)) {
            define($const, $value);
        }
    }

    $suppressDbConstantWarnings = true;
}

$candidates = [
    dirname($scriptDir, 4) . DIRECTORY_SEPARATOR . 'wp-load.php',
    dirname($scriptDir, 3) . DIRECTORY_SEPARATOR . 'wp-load.php',
    dirname($scriptDir, 5) . DIRECTORY_SEPARATOR . 'wp-load.php',
];

$wpLoad = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $wpLoad = $candidate;
        break;
    }
}

if ($wpLoad === null) {
    fwrite(STDERR, "Unable to locate wp-load.php.\n");
    exit(1);
}

if ($suppressDbConstantWarnings) {
    set_error_handler(static function (int $severity, string $message): bool {
        if ($severity === E_WARNING && strpos($message, 'Constant DB_') !== false && strpos($message, 'already defined') !== false) {
            return true;
        }

        return false;
    });
}

require_once $wpLoad;

if ($suppressDbConstantWarnings) {
    restore_error_handler();
}

if (!class_exists(BookingChargeService::class)) {
    fwrite(STDERR, "BookingChargeService is not available. Is the plugin active?\n");
    exit(1);
}

global $wpdb, $myvh_container;

if (!($wpdb instanceof wpdb)) {
    fwrite(STDERR, "WordPress database is not available.\n");
    exit(1);
}

if (!is_object($myvh_container) || !method_exists($myvh_container, 'get')) {
    fwrite(STDERR, "Plugin container is not available.\n");
    exit(1);
}

$runForCurrentSite = static function (BookingChargeService $bookingChargeService) use ($wpdb, $apply): array {
    $bookingsTable = $wpdb->prefix . 'myvh_bookings';
    $chargesTable = $wpdb->prefix . 'myvh_booking_charges';

    $sql = "
        SELECT b.Id
        FROM {$bookingsTable} b
        LEFT JOIN {$chargesTable} bc ON bc.BookingId = b.Id
        WHERE b.RecurringPatternId IS NOT NULL
          AND b.RecurringPatternId > 0
        GROUP BY b.Id
        HAVING COUNT(bc.Id) = 0
        ORDER BY b.Id ASC
    ";

    $bookingIds = array_map('intval', (array) $wpdb->get_col($sql));
    $total = count($bookingIds);

    $result = [
        'missing' => $total,
        'fixed' => 0,
        'failed' => 0,
        'failed_ids' => [],
        'example_ids' => array_slice($bookingIds, 0, 25),
    ];

    if (!$apply) {
        return $result;
    }

    foreach ($bookingIds as $bookingId) {
        $chargeResult = $bookingChargeService->recalculate($bookingId);

        if (is_wp_error($chargeResult) || $chargeResult === false) {
            $result['failed']++;
            $result['failed_ids'][] = $bookingId;
            $message = is_wp_error($chargeResult) ? $chargeResult->get_error_message() : 'Unknown error';
            fwrite(STDERR, "Failed booking {$bookingId}: {$message}\n");
            continue;
        }

        $result['fixed']++;
        fwrite(STDOUT, "Fixed booking {$bookingId}\n");
    }

    return $result;
};

$siteIds = [];
if (is_multisite() && !$currentSiteOnly) {
    $networkSites = get_sites(['fields' => 'ids', 'number' => 0]);
    $siteIds = array_map('intval', is_array($networkSites) ? $networkSites : []);
} else {
    $siteIds = [get_current_blog_id()];
}

$totalMissing = 0;
$totalFixed = 0;
$totalFailed = 0;
$failedRefs = [];

fwrite(STDOUT, 'Sites to process: ' . implode(', ', $siteIds) . "\n\n");

foreach ($siteIds as $siteId) {
    if (is_multisite()) {
        switch_to_blog($siteId);
    }

    try {
        /** @var \MYVH\Container\Container $siteContainer */
        $siteContainer = require MYVH_PLUGIN_DIR . 'src/Core/Support/myvh-container.php';
        /** @var BookingChargeService $siteBookingChargeService */
        $siteBookingChargeService = $siteContainer->get(BookingChargeService::class);
    } catch (Throwable $e) {
        if (is_multisite()) {
            restore_current_blog();
        }

        fwrite(STDERR, "Failed to initialize services for site {$siteId}: " . $e->getMessage() . "\n");
        exit(1);
    }

    $siteUrl = function_exists('get_site_url') ? (string) get_site_url($siteId) : '';
    fwrite(STDOUT, "Site {$siteId}" . ($siteUrl !== '' ? " ({$siteUrl})" : '') . "\n");

    $siteResult = $runForCurrentSite($siteBookingChargeService);

    fwrite(STDOUT, "Recurring bookings with missing charge rows: {$siteResult['missing']}\n");

    if (!$apply) {
        if ($siteResult['missing'] > 0 && !empty($siteResult['example_ids'])) {
            fwrite(STDOUT, 'Example booking IDs: ' . implode(', ', $siteResult['example_ids']) . "\n");
        }
    } else {
        fwrite(STDOUT, "Site fixed: {$siteResult['fixed']}\n");
        fwrite(STDOUT, "Site failed: {$siteResult['failed']}\n");
        if (!empty($siteResult['failed_ids'])) {
            foreach ($siteResult['failed_ids'] as $failedId) {
                $failedRefs[] = "{$siteId}:{$failedId}";
            }
        }
    }

    fwrite(STDOUT, "\n");

    $totalMissing += (int) $siteResult['missing'];
    $totalFixed += (int) $siteResult['fixed'];
    $totalFailed += (int) $siteResult['failed'];

    if (is_multisite()) {
        restore_current_blog();
    }
}

if (!$apply) {
    fwrite(STDOUT, "Dry run only. Re-run with --apply to perform fixes.\n");
    fwrite(STDOUT, "Network total missing: {$totalMissing}\n");
    exit(0);
}

fwrite(STDOUT, "Summary\n");
fwrite(STDOUT, "Network total missing (pre-fix): {$totalMissing}\n");
fwrite(STDOUT, "Network fixed: {$totalFixed}\n");
fwrite(STDOUT, "Network failed: {$totalFailed}\n");

if ($totalFailed > 0) {
    fwrite(STDOUT, 'Failed refs (site:booking): ' . implode(', ', $failedRefs) . "\n");
    exit(2);
}

exit(0);

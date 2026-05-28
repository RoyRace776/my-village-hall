<?php
namespace MYVH\Network;

use WP_Site;

if (!defined('ABSPATH')) {
    exit;
}

class IntegrityRunManager {
    public const CRON_HOOK = 'myvh_network_integrity_run';

    public function __construct(
        private ?IntegrityRepository $repository = null,
        private ?SiteIntegrityChecker $checker = null
    ) {
        if ($this->repository === null) {
            $this->repository = new IntegrityRepository();
        }

        if ($this->checker === null) {
            $this->checker = new SiteIntegrityChecker();
        }
    }

    public function register(): void {
        add_action(self::CRON_HOOK, [$this, 'process_run'], 10, 1);
    }

    public function queue_network_run(int $requested_by_user_id = 0): int|\WP_Error {
        $active_run = $this->repository->get_active_run();
        if (is_array($active_run)) {
            return new \WP_Error('integrity_run_in_progress', __('An integrity run is already queued or running.', 'my-village-hall'));
        }

        $sites = $this->get_integrity_sites();
        $total_sites = count($sites);

        $run_id = $this->repository->create_run(null, max(0, $requested_by_user_id), $total_sites);
        if ($run_id <= 0) {
            return new \WP_Error('integrity_run_create_failed', __('Could not create integrity run.', 'my-village-hall'));
        }

        wp_schedule_single_event(time() + 5, self::CRON_HOOK, [$run_id]);

        return $run_id;
    }

    public function run_single_site(int $blog_id, int $requested_by_user_id = 0): int|\WP_Error {
        if ($blog_id <= 0) {
            return new \WP_Error('invalid_blog_id', __('A valid site is required.', 'my-village-hall'));
        }

        $run_id = $this->repository->create_run($blog_id, max(0, $requested_by_user_id), 1);
        if ($run_id <= 0) {
            return new \WP_Error('integrity_run_create_failed', __('Could not create integrity run.', 'my-village-hall'));
        }

        $this->repository->mark_run_running($run_id);
        $result = $this->run_checks_for_blog($run_id, $blog_id);

        $summary = sprintf('Errors: %d, Warnings: %d', (int) $result['error_count'], (int) $result['warning_count']);
        $final_status = (int) $result['error_count'] > 0 ? 'failed' : 'completed';
        $this->repository->complete_run($run_id, $final_status, $summary);

        return $run_id;
    }

    public function process_run(int $run_id): void {
        $run = $this->repository->get_run($run_id);
        if (!is_array($run)) {
            return;
        }

        $status = (string) ($run['status'] ?? '');
        if ($status !== 'queued' && $status !== 'running') {
            return;
        }

        $this->repository->mark_run_running($run_id);

        $target_blog_id = isset($run['target_blog_id']) ? (int) $run['target_blog_id'] : 0;

        if ($target_blog_id > 0) {
            $this->run_checks_for_blog($run_id, $target_blog_id);
            $run_after = $this->repository->get_run($run_id);
            $summary = sprintf(
                'Processed 1 site. Error sites: %d, Warning sites: %d',
                (int) ($run_after['error_sites'] ?? 0),
                (int) ($run_after['warning_sites'] ?? 0)
            );
            $final_status = (int) ($run_after['error_sites'] ?? 0) > 0 ? 'failed' : 'completed';
            $this->repository->complete_run($run_id, $final_status, $summary);
            return;
        }

        $sites = $this->get_integrity_sites();

        foreach ($sites as $site) {
            $blog_id = (int) ($site->blog_id ?? 0);
            if ($blog_id <= 0) {
                continue;
            }

            $this->run_checks_for_blog($run_id, $blog_id);
        }

        $run_after = $this->repository->get_run($run_id);
        $summary = sprintf(
            'Processed %d of %d sites. Error sites: %d, Warning sites: %d',
            (int) ($run_after['processed_sites'] ?? 0),
            (int) ($run_after['total_sites'] ?? 0),
            (int) ($run_after['error_sites'] ?? 0),
            (int) ($run_after['warning_sites'] ?? 0)
        );
        $final_status = (int) ($run_after['error_sites'] ?? 0) > 0 ? 'failed' : 'completed';

        $this->repository->complete_run($run_id, $final_status, $summary);
    }

    /**
     * @return array<string, mixed>
     */
    private function run_checks_for_blog(int $run_id, int $blog_id): array {
        switch_to_blog($blog_id);

        try {
            $result = $this->checker->run_for_current_site();
        } catch (\Throwable $exception) {
            $result = [
                'status' => 'failed',
                'error_count' => 1,
                'warning_count' => 0,
                'summary' => $exception->getMessage(),
                'findings' => [
                    [
                        'check_key' => 'runtime.exception',
                        'severity' => 'error',
                        'message' => $exception->getMessage(),
                        'details' => [
                            'exception' => get_class($exception),
                        ],
                    ],
                ],
            ];
        }

        restore_current_blog();

        $error_count = max(0, (int) ($result['error_count'] ?? 0));
        $warning_count = max(0, (int) ($result['warning_count'] ?? 0));
        $status = (string) ($result['status'] ?? 'completed');
        $summary = (string) ($result['summary'] ?? '');
        $findings = isset($result['findings']) && is_array($result['findings']) ? $result['findings'] : [];

        $this->repository->add_findings($run_id, $blog_id, $findings);
        $this->repository->upsert_site_status($blog_id, $run_id, $status, $error_count, $warning_count, $summary);
        $this->repository->increment_progress($run_id, $error_count > 0, $warning_count > 0);

        return [
            'status' => $status,
            'error_count' => $error_count,
            'warning_count' => $warning_count,
            'summary' => $summary,
        ];
    }

    /**
     * @return WP_Site[]
     */
    private function get_integrity_sites(): array {
        $sites = get_sites([
            'number' => 0,
            'count' => false,
            'fields' => '',
            'orderby' => 'domain',
            'order' => 'ASC',
        ]);

        if (!is_array($sites)) {
            return [];
        }

        $template_site_id = NetworkProvisioningSettings::template_site_id();
        $main_site_id = function_exists('get_main_site_id') ? (int) get_main_site_id() : 0;

        $filtered_sites = [];

        foreach ($sites as $site) {
            $blog_id = (int) ($site->blog_id ?? 0);

            if ($blog_id <= 0) {
                continue;
            }

            if ($template_site_id > 0 && $blog_id === $template_site_id) {
                continue;
            }

            if ($main_site_id > 0 && $blog_id === $main_site_id) {
                continue;
            }

            $filtered_sites[] = $site;
        }

        return $filtered_sites;
    }
}

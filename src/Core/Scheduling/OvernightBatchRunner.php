<?php
namespace MYVH\Core\Scheduling;

use MYVH\Email\EmailService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the myvh_overnight_batch WP-Cron event, reconciles its schedule
 * on every request, and orchestrates all registered OvernightJobInterface jobs
 * when the cron fires.
 */
class OvernightBatchRunner {

    const HOOK = 'myvh_overnight_batch';

    /** @var OvernightJobInterface[] */
    private array $jobs;

    private EmailService $email_service;
    private LoggerInterface $logger;

    /**
     * @param OvernightJobInterface[] $jobs
     */
    public function __construct( array $jobs, EmailService $email_service, ?LoggerInterface $logger = null ) {
        $this->jobs          = $jobs;
        $this->email_service = $email_service;
        $this->logger        = $logger ?? new NullLogger();
    }

    /**
     * Hook into WordPress:
     *   - reconcile_schedule() on init (keep cron in sync with settings)
     *   - run_batch() on the cron hook itself
     */
    public function register(): void {
        add_action( 'init', [ $this, 'reconcile_schedule' ] );
        add_action( self::HOOK, [ $this, 'run_batch' ] );
    }

    /**
     * Enable or disable the nightly cron event based on whether any job is
     * currently enabled. Called on every init so settings changes are picked up
     * without requiring manual intervention.
     */
    public function reconcile_schedule(): void {
        $any_enabled = false;
        foreach ( $this->jobs as $job ) {
            if ( $job->is_enabled() ) {
                $any_enabled = true;
                break;
            }
        }

        if ( $any_enabled ) {
            OvernightJobScheduler::schedule( self::HOOK );
        } else {
            OvernightJobScheduler::clear( self::HOOK );
        }
    }

    /**
     * Execute all enabled jobs in sequence, collect results, send summary email.
     */
    public function run_batch(): void {
        /** @var OvernightJobResult[] $results */
        $results = [];

        foreach ( $this->jobs as $job ) {
            if ( ! $job->is_enabled() ) {
                continue;
            }

            try {
                $results[] = $job->run();
            } catch ( \Throwable $e ) {
                $this->logger->error('Overnight job failed', [
                    'job_class' => get_class($job),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $results[] = new OvernightJobResult(
                    job_name: get_class( $job ),
                    count:    0,
                    success:  false,
                    summary:  $e->getMessage()
                );
            }
        }

        $this->send_summary_email( $results );
    }

    /**
     * Build and send the overnight batch summary email to the site admin.
     *
     * @param OvernightJobResult[] $results
     */
    public function send_summary_email( array $results ): void {
        if ( empty( $results ) ) {
            return;
        }

        $admin_email = get_option( 'admin_email' );
        if ( ! $admin_email ) {
            return;
        }

        $rows = '';
        foreach ( $results as $result ) {
            $status_label = $result->success ? 'Success' : 'Failed';
            $status_colour = $result->success ? '#2e7d32' : '#c62828';
            $detail = esc_html( $result->summary ?: ( $result->count . ' processed' ) );
            $rows .= '<tr>'
                . '<td style="padding:8px 12px;border-bottom:1px solid #eee;">' . esc_html( $result->job_name ) . '</td>'
                . '<td style="padding:8px 12px;border-bottom:1px solid #eee;text-align:center;">' . (int) $result->count . '</td>'
                . '<td style="padding:8px 12px;border-bottom:1px solid #eee;color:' . $status_colour . ';font-weight:bold;">' . $status_label . '</td>'
                . '<td style="padding:8px 12px;border-bottom:1px solid #eee;color:#666;">' . $detail . '</td>'
                . '</tr>';
        }

        $this->email_service->send( [
            'to'       => $admin_email,
            'template' => 'overnight-batch-summary',
            'template_vars' => [
                'run_date'     => current_time( 'd M Y H:i' ),
                'summary_rows' => $rows,
            ],
        ] );
    }
}

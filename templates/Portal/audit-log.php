<?php
if (!defined('ABSPATH')) {
    exit;
}

$rows = isset($rows) && is_array($rows) ? $rows : [];
?>

<div class="myvh-dashboard-section myvh-client-settings-page">
    <div class="myvh-account-header">
        <div>
            <h2>Audit Log</h2>
            <p>Recent create/delete activity across bookings, people, venues, billing, and related admin records.</p>
        </div>
    </div>

    <?php if (!\MYVH\Audit\AuditTrail::is_enabled()): ?>
        <div class="myvh-card myvh-card--danger">
            <p>Auditing is currently disabled by a site administrator.</p>
        </div>
    <?php endif; ?>

    <div class="myvh-invoices-table-wrap">
        <table class="myvh-customer-list-table myvh-invoices-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>Entity ID</th>
                    <th>Actor</th>
                    <th>Origin</th>
                    <th>Summary</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="7">No audit entries found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $actor_user_id = \intval($row['ActorUserId'] ?? 0);
                        $actor_display = $actor_user_id > 0 ? '#' . $actor_user_id : 'System';
                        $summary = !empty($row['SummaryArray']) ? wp_json_encode($row['SummaryArray']) : '';
                        ?>
                        <tr>
                            <td><?php echo esc_html((string) ($row['CreatedAt'] ?? '')); ?></td>
                            <td><?php echo esc_html(ucfirst((string) ($row['Action'] ?? ''))); ?></td>
                            <td><?php echo esc_html((string) ($row['EntityType'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($row['EntityId'] ?? '')); ?></td>
                            <td><?php echo esc_html($actor_display); ?></td>
                            <td><?php echo esc_html((string) ($row['Origin'] ?? '')); ?></td>
                            <td><?php echo esc_html($summary); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

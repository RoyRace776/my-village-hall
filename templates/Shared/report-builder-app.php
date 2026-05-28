<?php
if (!defined('ABSPATH')) {
    exit;
}

$view_model = isset($view_model) && is_array($view_model) ? $view_model : [];
$context = isset($view_model['context']) && is_array($view_model['context']) ? $view_model['context'] : [];
$bootstrap = isset($view_model['bootstrap']) && is_array($view_model['bootstrap']) ? $view_model['bootstrap'] : [];
$mode = (string) ($context['mode'] ?? 'portal');
$permissions = isset($context['permissions']) && is_array($context['permissions']) ? $context['permissions'] : [];
$reports = isset($bootstrap['reports']) && is_array($bootstrap['reports']) ? $bootstrap['reports'] : [];
$report_screen = isset($report_screen) ? (string) $report_screen : ((isset($view_model['screen']) && is_string($view_model['screen'])) ? $view_model['screen'] : 'admin');

$can_create = !empty($permissions['canCreateReports']);
$can_delete = !empty($permissions['canDeleteReports']);
$can_export = !empty($permissions['canExportCSV']);
$show_runner = !in_array($report_screen, ['builder'], true);
$show_builder = in_array($report_screen, ['admin', 'builder'], true) && ($mode === 'admin' || $can_create);

$heading = __('Report Builder', 'my-village-hall');
$intro = __('Admin is a privileged view of the same report system used in the portal.', 'my-village-hall');
if ($report_screen === 'reports') {
    $heading = __('Reports', 'my-village-hall');
    $intro = __('Run shared reports and export results to CSV.', 'my-village-hall');
}
if ($report_screen === 'builder') {
    $heading = __('Report Builder', 'my-village-hall');
    $intro = __('Build and save reports using shared schema and permissions.', 'my-village-hall');
}
if ($report_screen === 'view') {
    $heading = __('Report Viewer', 'my-village-hall');
    $intro = __('View a specific report result set.', 'my-village-hall');
}
?>

<div class="myvh-report-app myvh-dashboard-section myvh-reports-page" data-mode="<?php echo esc_attr($mode); ?>" data-report-screen="<?php echo esc_attr($report_screen); ?>">
    <div class="myvh-account-header">
        <div>
            <h2><?php echo esc_html($heading); ?></h2>
            <p><?php echo esc_html($intro); ?></p>
        </div>
    </div>

    <?php if ($show_runner): ?>
    <div class="myvh-card myvh-account-card myvh-settings-group myvh-report-runner" data-report-runner>
        <div class="myvh-account-card-head">
            <div>
                <h3><?php esc_html_e('Run Report', 'my-village-hall'); ?></h3>
                <span><?php esc_html_e('Run a report and view results in Tabulator. Export follows your visible table state.', 'my-village-hall'); ?></span>
            </div>
        </div>

        <div class="myvh-account-actions myvh-reports-controls">
            <label class="myvh-account-field myvh-reports-select-field" for="myvh-report-select">
                <span><?php esc_html_e('Report', 'my-village-hall'); ?></span>
                <select id="myvh-report-select" data-report-select>
                    <?php foreach ($reports as $report): ?>
                        <?php if (!is_array($report)) { continue; } ?>
                        <?php $report_id = (int) ($report['id'] ?? 0); ?>
                        <?php $report_type = (string) ($report['type'] ?? 'user'); ?>
                        <?php $report_type_label = $report_type === 'system' ? __('System', 'my-village-hall') : __('User', 'my-village-hall'); ?>
                        <?php $report_locked_label = $report_id <= 0 ? __(', locked', 'my-village-hall') : ''; ?>
                        <option
                            value="<?php echo esc_attr((string) $report_id); ?>"
                            data-source="<?php echo esc_attr((string) ($report['data_source'] ?? '')); ?>"
                            data-report-name="<?php echo esc_attr((string) ($report['name'] ?? '')); ?>"
                            data-report-type="<?php echo esc_attr($report_type); ?>"
                            data-report-deletable="<?php echo esc_attr($report_id > 0 ? '1' : '0'); ?>"
                        >
                            <?php echo esc_html(sprintf('%s (%s%s)', (string) ($report['name'] ?? ''), $report_type_label, $report_locked_label)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <button type="button" class="myvh-portal-add-btn" data-report-run>
                <span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
                <?php esc_html_e('Run Report', 'my-village-hall'); ?>
            </button>

            <?php if ($can_export): ?>
                <button type="button" class="myvh-portal-add-btn myvh-portal-nav-btn" data-report-export>
                    <span class="dashicons dashicons-download" aria-hidden="true"></span>
                    <?php esc_html_e('Export CSV', 'my-village-hall'); ?>
                </button>
            <?php endif; ?>

            <span class="myvh-report-runner-status" data-report-status aria-live="polite"></span>
        </div>

        <div class="myvh-reports-table-wrap">
            <div id="myvh-report-table"></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($show_builder): ?>
    <div class="myvh-card myvh-account-card myvh-settings-group myvh-report-builder" data-report-builder>
        <div class="myvh-account-card-head">
            <div>
                <h3><?php esc_html_e('Build Report', 'my-village-hall'); ?></h3>
                <span><?php esc_html_e('Use one shared builder for data source, fields, filters, sort, and save.', 'my-village-hall'); ?></span>
            </div>
        </div>

        <div class="myvh-account-actions myvh-reports-controls myvh-report-builder-section myvh-report-builder-section--chooser">
            <label class="myvh-account-field myvh-reports-select-field" for="myvh-builder-report-select">
                <span><?php esc_html_e('Existing Report', 'my-village-hall'); ?></span>
                <select id="myvh-builder-report-select" data-report-select>
                    <option value=""><?php esc_html_e('Create a new report', 'my-village-hall'); ?></option>
                    <?php foreach ($reports as $report): ?>
                        <?php if (!is_array($report)) { continue; } ?>
                        <?php $report_id = (int) ($report['id'] ?? 0); ?>
                        <?php $report_type = (string) ($report['type'] ?? 'user'); ?>
                        <?php $report_type_label = $report_type === 'system' ? __('System', 'my-village-hall') : __('User', 'my-village-hall'); ?>
                        <?php $report_locked_label = $report_id <= 0 ? __(', locked', 'my-village-hall') : ''; ?>
                        <option
                            value="<?php echo esc_attr((string) $report_id); ?>"
                            data-source="<?php echo esc_attr((string) ($report['data_source'] ?? '')); ?>"
                            data-report-name="<?php echo esc_attr((string) ($report['name'] ?? '')); ?>"
                            data-report-type="<?php echo esc_attr($report_type); ?>"
                            data-report-deletable="<?php echo esc_attr($report_id > 0 ? '1' : '0'); ?>"
                        >
                            <?php echo esc_html(sprintf('%s (%s%s)', (string) ($report['name'] ?? ''), $report_type_label, $report_locked_label)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <p class="myvh-report-builder-help-text"><?php esc_html_e('Select an existing report to load it into the editor, then change its filters or fields and save. Locked reports are built-in and cannot be deleted.', 'my-village-hall'); ?></p>
        </div>

        <div class="myvh-account-actions myvh-reports-controls myvh-report-builder-section myvh-report-builder-section--meta">
            <label class="myvh-account-field myvh-reports-select-field" for="myvh-builder-data-source">
                <span><?php esc_html_e('Data Source', 'my-village-hall'); ?></span>
                <select id="myvh-builder-data-source" data-builder-source></select>
            </label>

            <label class="myvh-account-field myvh-reports-select-field" for="myvh-builder-name">
                <span><?php esc_html_e('Report Name', 'my-village-hall'); ?></span>
                <input id="myvh-builder-name" type="text" data-builder-name />
            </label>
        </div>

        <div class="myvh-account-actions myvh-reports-controls myvh-report-builder-section myvh-report-builder-section--fields">
            <div class="myvh-account-field myvh-reports-select-field">
                <span><?php esc_html_e('Available Fields', 'my-village-hall'); ?></span>
                <div class="myvh-report-field-list" data-builder-available-fields></div>
            </div>
            <div class="myvh-account-field myvh-reports-select-field">
                <span><?php esc_html_e('Selected Fields', 'my-village-hall'); ?></span>
                <div class="myvh-report-field-list" data-builder-selected-fields></div>
            </div>
        </div>

        <div class="myvh-account-actions myvh-reports-controls myvh-report-builder-section myvh-report-builder-section--criteria">
            <div class="myvh-account-field myvh-reports-select-field">
                <span><?php esc_html_e('Filters', 'my-village-hall'); ?></span>
                <div data-builder-filters></div>
                <button type="button" class="button myvh-report-builder-add-btn" data-builder-add-filter><span class="dashicons dashicons-filter" aria-hidden="true"></span><?php esc_html_e('Add Filter', 'my-village-hall'); ?></button>
            </div>

            <div class="myvh-account-field myvh-reports-select-field">
                <span><?php esc_html_e('Sort', 'my-village-hall'); ?></span>
                <div data-builder-sort></div>
                <button type="button" class="button myvh-report-builder-add-btn" data-builder-add-sort><span class="dashicons dashicons-sort" aria-hidden="true"></span><?php esc_html_e('Add Sort', 'my-village-hall'); ?></button>
            </div>
        </div>

        <div class="myvh-account-actions myvh-reports-controls myvh-report-builder-section myvh-report-builder-section--footer">
            <button type="button" class="myvh-portal-add-btn myvh-portal-nav-btn" data-builder-new <?php echo $can_create ? '' : 'disabled'; ?>>
                <span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
                <?php esc_html_e('New Report', 'my-village-hall'); ?>
            </button>
            <button type="button" class="myvh-portal-add-btn myvh-portal-nav-btn" data-builder-cancel hidden>
                <span class="dashicons dashicons-undo" aria-hidden="true"></span>
                <?php esc_html_e('Cancel New', 'my-village-hall'); ?>
            </button>
            <button type="button" class="myvh-portal-add-btn" data-builder-save <?php echo $can_create ? '' : 'disabled'; ?>>
                <span class="dashicons dashicons-saved" aria-hidden="true"></span>
                <?php esc_html_e('Save Report', 'my-village-hall'); ?>
            </button>
            <button type="button" class="myvh-portal-add-btn myvh-portal-nav-btn" data-builder-delete <?php echo $can_delete ? '' : 'disabled'; ?>>
                <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                <?php esc_html_e('Delete Report', 'my-village-hall'); ?>
            </button>
            <span data-builder-status aria-live="polite"></span>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($report_screen === 'builder' && !$show_builder): ?>
    <div class="myvh-card myvh-account-card myvh-settings-group">
        <p><?php esc_html_e('You do not have permission to create reports in this portal.', 'my-village-hall'); ?></p>
    </div>
    <?php endif; ?>

    <script type="application/json" data-myvh-report-context><?php echo wp_json_encode($context); ?></script>
    <script type="application/json" data-myvh-report-bootstrap><?php echo wp_json_encode($bootstrap); ?></script>
</div>

<script>
window.MyVHReportContext = <?php echo wp_json_encode($context); ?>;
window.MyVHReportBootstrap = <?php echo wp_json_encode($bootstrap); ?>;
</script>

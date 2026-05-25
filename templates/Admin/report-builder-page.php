<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_myvh')) {
    wp_die(__('Permission denied', 'my-village-hall'));
}

global $myvh_container;

$view_model = [];
if ($myvh_container instanceof \MYVH\Container\Container) {
    $factory = $myvh_container->get(\MYVH\Reports\ReportViewModelFactory::class);
    if ($factory instanceof \MYVH\Reports\ReportViewModelFactory) {
        $view_model = $factory->build_for_mode(get_current_user_id(), 'admin');
    }
}

$report_screen = 'admin';
?>
<div class="wrap myvh-report-builder-admin-page">
    <?php include MYVH_PLUGIN_DIR . 'templates/Shared/report-builder-app.php'; ?>
</div>

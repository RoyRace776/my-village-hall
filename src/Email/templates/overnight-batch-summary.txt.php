Overnight Batch Summary — <?php echo esc_html($site_name ?? ''); ?>

Run completed: <?php echo esc_html($run_date ?? ''); ?>

<?php echo strip_tags(str_replace(['</tr>', '<td', '</td>'], ["\n", "\t", ''], $summary_rows ?? '')); ?>

--
<?php echo esc_html($site_name ?? ''); ?>
<?php echo esc_url($site_url ?? ''); ?>

<?php
/**
 * Progress Bar Component Template.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations
 *
 * @version 0.0.1
 *
 * @var StellarWP\Migrations\Contracts\Migration $migration Migration object.
 */

defined( 'ABSPATH' ) || exit;

use StellarWP\Migrations\Contracts\Migration;

if ( ! isset( $migration ) || ! $migration instanceof Migration ) {
	return;
}

$latest_execution = $migration->get_latest_execution();

$total_items     = $migration->get_total_items();
$items_processed = $latest_execution ? $latest_execution->get_items_processed() : 0;
$status_value    = $migration->get_status()->getValue();

// Calculate progress percentage.
$percent = $total_items > 0 ? min( 100, ( $items_processed / $total_items ) * 100 ) : 0;

$progress_label = sprintf(
	/* translators: %1$d: items processed, %2$d: total items, %3$d: percentage complete */
	__( 'Migration progress: %1$d of %2$d items processed (%3$d%% complete)', 'stellarwp-migrations' ),
	$items_processed,
	$total_items,
	(int) $percent
);
?>
<div
	class="stellarwp-migration-progress"
	role="progressbar"
	aria-valuenow="<?php echo esc_attr( (string) $items_processed ); ?>"
	aria-valuemin="0"
	aria-valuemax="<?php echo esc_attr( (string) $total_items ); ?>"
	aria-label="<?php echo esc_attr( $progress_label ); ?>"
>
	<div class="stellarwp-migration-progress__bar">
		<div
			class="stellarwp-migration-progress__fill stellarwp-migration-progress__fill--<?php echo esc_attr( $status_value ); ?>"
			style="width: <?php echo esc_attr( (string) $percent ); ?>%;"
		></div>
	</div>
</div>

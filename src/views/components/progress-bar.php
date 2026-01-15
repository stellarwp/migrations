<?php
/**
 * Progress Bar Component Template.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\views\components
 *
 * @var StellarWP\Migrations\Contracts\Migration $migration Migration object.
 */

defined( 'ABSPATH' ) || exit;

use StellarWP\Migrations\Contracts\Migration;

if ( ! isset( $migration ) || ! $migration instanceof Migration ) {
	return;
}

$total_items     = $migration->get_total_items();
$items_processed = $migration->get_items_processed();
$status_value    = $migration->get_status()->getValue();

// Calculate progress percentage.
$percent = $total_items > 0 ? min( 100, ( $items_processed / $total_items ) * 100 ) : 0;
?>
<div class="stellarwp-migration-progress">
	<div class="stellarwp-migration-progress__bar">
		<div
			class="stellarwp-migration-progress__fill stellarwp-migration-progress__fill--<?php echo esc_attr( $status_value ); ?>"
			style="width: <?php echo esc_attr( (string) $percent ); ?>%;"
		></div>
	</div>
</div>

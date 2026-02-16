<?php
/**
 * Migration Status Template — Not Applicable.
 *
 * @since 0.0.1
 * @version 0.0.1
 *
 * @package StellarWP\Migrations
 *
 * @var StellarWP\Migrations\Contracts\Migration    $migration    Migration object.
 * @var StellarWP\Migrations\Utilities\Migration_UI $migration_ui UI helper object.
 */

defined( 'ABSPATH' ) || exit;

use StellarWP\Migrations\Config;
use StellarWP\Migrations\Contracts\Migration;
use StellarWP\Migrations\Utilities\Migration_UI;

if (
	! isset( $migration )
	|| ! isset( $migration_ui )
	|| ! $migration instanceof Migration
	|| ! $migration_ui instanceof Migration_UI
) {
	return;
}

$status_value = $migration_ui->get_display_status()->getValue();
$status_label = $migration_ui->get_display_status_label();

$template = Config::get_template_engine();
?>
<div class="stellarwp-migration-card__status">
	<span class="stellarwp-migration-card__status-label stellarwp-migration-card__status-label--<?php echo esc_attr( $status_value ); ?>">
		<?php echo esc_html( $status_label ); ?>
	</span>
</div>

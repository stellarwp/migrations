<?php
/**
 * Migration Actions Component Template.
 *
 * Displays action buttons for migrations.
 *
 * @since 0.0.1
 * @version 0.0.1
 *
 * @package StellarWP\Migrations
 *
 * @var StellarWP\Migrations\Contracts\Migration       $migration    Migration object.
 * @var StellarWP\Migrations\Utilities\Migration_UI    $migration_ui UI helper (injected by migration-card).
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

$migration_label     = $migration->get_label();
$show_run            = $migration_ui->show_run();
$show_rollback       = $migration_ui->show_rollback();
$run_migration_label = $migration_ui->get_run_action_label();
$run_migration_icon  = $migration_ui->get_run_action_icon();

$run_aria_label = $migration_label
	? sprintf(
		/* translators: Migration action label and migration label. */
		__( '%1$s Migration %2$s', 'stellarwp-migrations' ),
		$run_migration_label,
		$migration_label,
	)
	: __( 'Run Migration', 'stellarwp-migrations' );

$rollback_aria_label = $migration_label
	? sprintf(
		/* translators: %s: migration label. */
		__( 'Rollback Migration %s', 'stellarwp-migrations' ),
		$migration_label
	)
	: __( 'Rollback Migration', 'stellarwp-migrations' );
?>
<div class="stellarwp-migration-card__actions">
	<?php if ( $show_run ) : ?>
		<button
			type="button"
			class="stellarwp-migration-btn stellarwp-migration-btn--primary"
			data-action="run"
			aria-label="<?php echo esc_attr( $run_aria_label ); ?>"
		>
			<?php
			Config::get_template_engine()->template(
				'icons/' . $run_migration_icon,
				[
					'is_aria_hidden' => true,
				]
			);
			?>
			<?php echo esc_html( $run_migration_label ); ?>
		</button>
	<?php endif; ?>

	<?php if ( $show_rollback ) : ?>
		<button
			type="button"
			class="stellarwp-migration-btn stellarwp-migration-btn--secondary"
			data-action="rollback"
			<?php // translators: %s: migration label. ?>
			aria-label="<?php echo esc_attr( $rollback_aria_label ); ?>"
		>
			<?php
			Config::get_template_engine()->template(
				'icons/rollback',
				[
					'is_aria_hidden' => true,
				]
			);
			?>
			<?php esc_html_e( 'Rollback', 'stellarwp-migrations' ); ?>
		</button>
	<?php endif; ?>
</div>

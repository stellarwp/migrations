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
 * @var StellarWP\Migrations\Contracts\Migration $migration Migration object.
 */

defined( 'ABSPATH' ) || exit;

use StellarWP\Migrations\Config;
use StellarWP\Migrations\Contracts\Migration;
use StellarWP\Migrations\Enums\Operation;
use StellarWP\Migrations\Enums\Status;

if ( ! isset( $migration ) || ! $migration instanceof Migration ) {
	return;
}

$migration_label          = $migration->get_label();
$migration_status         = $migration->get_status();
$can_run                  = $migration->can_run();
$is_applicable            = $migration->is_applicable();
$total_items              = $migration->get_total_items();
$total_items_for_rollback = $migration->get_total_items( Operation::DOWN() );

$status_value = $migration_status->getValue();

// Determine which buttons to show based on status.
$show_run = (
	$is_applicable
	&& $can_run
	&& in_array(
		$status_value,
		[
			Status::COMPLETED()->getValue(),
			Status::PENDING()->getValue(),
			Status::CANCELED()->getValue(),
			Status::FAILED()->getValue(),
			Status::REVERTED()->getValue(),
		],
		true
	)
	&& $total_items > 0
);

$show_rollback = (
	$is_applicable
	&& in_array(
		$status_value,
		[
			Status::COMPLETED()->getValue(),
			Status::CANCELED()->getValue(),
			Status::FAILED()->getValue(),
		],
		true
	)
	&& $total_items_for_rollback > 0
);

$run_migration_label = $migration_status->equals( Status::COMPLETED() )
	? __( 'Run again', 'stellarwp-migrations' )
	: __( 'Start', 'stellarwp-migrations' );
$run_migration_icon  = $migration_status->equals( Status::COMPLETED() )
	? 'retry'
	: 'start';

$run_aria_label = $migration_label
	? sprintf(
		/* translators: %s: migration label. */
		__( 'Run Migration %s', 'stellarwp-migrations' ),
		$migration_label
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

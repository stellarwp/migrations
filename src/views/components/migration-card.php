<?php
/**
 * Migration Card Component Template.
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

use StellarWP\Migrations\Config;
use StellarWP\Migrations\Contracts\Migration;
use StellarWP\Migrations\Enums\Status;
use StellarWP\Migrations\Admin\Provider as Admin_Provider;

if ( ! isset( $migration ) || ! $migration instanceof Migration ) {
	return;
}

$execution = $migration->get_latest_execution();

$migration_id     = $migration->get_id();
$single_url       = Admin_Provider::get_single_url( $migration_id );
$migration_label  = $migration->get_label();
$description      = $migration->get_description();
$migration_status = $migration->get_status();
$can_run          = $migration->can_run();
$is_applicable    = $migration->is_applicable();
$total_items      = $migration->get_total_items();
$items_processed  = $execution ? $execution->get_items_processed() : 0;
$migration_tags   = $migration->get_tags();

$status_value = $migration_status->getValue();
$status_label = $migration_status->get_label();

// Determine which buttons to show based on status.
// Note: Non-applicable migrations should not show any action buttons.
$show_run      = $is_applicable && in_array( $status_value, [ Status::PENDING()->getValue(), Status::CANCELED()->getValue(), Status::FAILED()->getValue(), Status::REVERTED()->getValue() ], true ) && $can_run;
$show_rollback = $is_applicable && in_array( $status_value, [ Status::COMPLETED()->getValue(), Status::CANCELED()->getValue(), Status::FAILED()->getValue() ], true );
?>
<div class="stellarwp-migration-card" data-migration-id="<?php echo esc_attr( $migration_id ); ?>">
	<div class="stellarwp-migration-card__header">
		<h3 class="stellarwp-migration-card__label">
			<a href="<?php echo esc_url( $single_url ); ?>"><?php echo esc_html( $migration_label ); ?></a>
		</h3>
		<?php if ( ! empty( $migration_tags ) ) : ?>
			<div class="stellarwp-migration-card__tags">
				<?php foreach ( $migration_tags as $migration_tag ) : ?>
					<span class="stellarwp-migration-card__tag"><?php echo esc_html( $migration_tag ); ?></span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<p class="stellarwp-migration-card__description">
		<?php echo esc_html( $description ); ?>
	</p>
	<hr class="stellarwp-migration-card__separator" />
	<div class="stellarwp-migration-card__footer">
		<div class="stellarwp-migration-card__status">
			<span class="stellarwp-migration-card__status-label stellarwp-migration-card__status-label--<?php echo esc_attr( $status_value ); ?>">
				<?php echo esc_html( $status_label ); ?>
			</span>
			<?php if ( $total_items > 0 ) : ?>
				<span class="stellarwp-migration-card__progress-text">
					<?php
					printf(
						/* translators: %1$d: items processed, %2$d: total items */
						esc_html__( '%1$d / %2$d', 'stellarwp-migrations' ),
						absint( $items_processed ),
						absint( $total_items )
					);
					?>
				</span>
			<?php endif; ?>
		</div>
		<?php if ( $total_items > 0 ) : ?>
			<?php
			Config::get_template_engine()->template(
				'components/progress-bar',
				[ 'migration' => $migration ]
			);
			?>
		<?php endif; ?>
		<div class="stellarwp-migration-card__actions">
			<?php if ( $show_run ) : ?>
				<button
					type="button"
					class="stellarwp-migration-btn stellarwp-migration-btn--primary"
					data-action="run"
					<?php // translators: %s: migration label. ?>
					aria-label="<?php echo esc_attr( sprintf( __( 'Run Migration %s', 'stellarwp-migrations' ), $migration_label ) ); ?>"
				>
					<?php esc_html_e( 'Run', 'stellarwp-migrations' ); ?>
				</button>
			<?php endif; ?>
			<?php if ( $show_rollback ) : ?>
				<button
					type="button"
					class="stellarwp-migration-btn stellarwp-migration-btn--secondary"
					data-action="rollback"
					<?php // translators: %s: migration label. ?>
					aria-label="<?php echo esc_attr( sprintf( __( 'Rollback Migration %s', 'stellarwp-migrations' ), $migration_label ) ); ?>"
				>
					<?php esc_html_e( 'Rollback', 'stellarwp-migrations' ); ?>
				</button>
			<?php endif; ?>
		</div>
	</div>
	<div class="stellarwp-migration-card__message" style="display: none;"></div>
</div>

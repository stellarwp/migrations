<?php
/**
 * Migration Status Card Component Template.
 *
 * Displays the current migration status with progress and action buttons.
 * Used on the single migration detail page.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations
 *
 * @version 0.0.1
 *
 * @var StellarWP\Migrations\Contracts\Migration $migration  Migration object.
 * @var list<array<string,mixed>>                $executions List of execution records.
 */

defined( 'ABSPATH' ) || exit;

use StellarWP\Migrations\Config;
use StellarWP\Migrations\Contracts\Migration;

if ( ! isset( $migration ) || ! $migration instanceof Migration ) {
	return;
}

$executions ??= [];

$latest_execution = $migration->get_latest_execution();

$migration_id     = $migration->get_id();
$migration_status = $migration->get_status();
$total_items      = $migration->get_total_items();
$items_processed  = $latest_execution ? $latest_execution->get_items_processed() : 0;

$status_value = $migration_status->getValue();
$status_label = $migration_status->get_label();

$started_at = $latest_execution ? $latest_execution->get_start_date() : null;
?>
<div class="stellarwp-migration-status-card" data-migration-id="<?php echo esc_attr( $migration_id ); ?>">
	<div class="stellarwp-migration-status-card__content">
		<div class="stellarwp-migration-status-card__status">
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
			<div class="stellarwp-migration-status-card__progress">
				<?php
				Config::get_template_engine()->template(
					'components/progress-bar',
					[ 'migration' => $migration ]
				);
				?>
			</div>
		<?php endif; ?>

		<?php
		Config::get_template_engine()->template(
			'components/migration-actions',
			[
				'migration' => $migration,
			]
		);
		?>
	</div>

	<hr class="stellarwp-migration-card__separator" />

	<div class="stellarwp-migration-status-card__timing">
		<?php if ( $started_at ) : ?>
			<span class="stellarwp-migration-status-card__started">
				<?php
				printf(
					/* translators: %s: human-readable time difference */
					esc_html__( 'Started %s ago', 'stellarwp-migrations' ),
					esc_html( human_time_diff( $started_at->getTimestamp() ) )
				);
				?>
			</span>
		<?php else : ?>
			<span class="stellarwp-migration-status-card__not-started">
				<?php esc_html_e( 'Not yet started', 'stellarwp-migrations' ); ?>
			</span>
		<?php endif; ?>
	</div>

	<div class="stellarwp-migration-card__message" style="display: none;" role="alert" aria-live="assertive"></div>
</div>

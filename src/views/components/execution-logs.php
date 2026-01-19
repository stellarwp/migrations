<?php
/**
 * Execution Logs Component Template.
 *
 * Displays logs for migration executions with load more functionality.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations
 *
 * @version 0.0.1
 *
 * @var StellarWP\Migrations\Contracts\Migration $migration     Migration object.
 * @var string                                   $rest_base_url REST API base URL.
 * @var list<array{
 *     id: int,
 *     migration_id: string,
 *     start_date_gmt: DateTimeInterface,
 *     end_date_gmt: DateTimeInterface,
 *     status: StellarWP\Migrations\Enums\Status,
 *     items_total: int,
 *     items_processed: int,
 *     created_at: DateTimeInterface
 * }> $executions List of execution records.
 */

defined( 'ABSPATH' ) || exit;

use StellarWP\Migrations\Contracts\Migration;
use StellarWP\Migrations\Enums\Status;

if ( ! isset( $migration ) || ! $migration instanceof Migration ) {
	return;
}

$executions    ??= [];
$rest_base_url ??= '';
?>
<div class="stellarwp-migration-logs" data-rest-url="<?php echo esc_url( $rest_base_url ); ?>">
	<?php if ( empty( $executions ) ) : ?>
		<div class="stellarwp-migration-logs__empty">
			<p><?php esc_html_e( 'No executions yet. Run the migration to see logs.', 'stellarwp-migrations' ); ?></p>
		</div>
	<?php else : ?>
		<div class="stellarwp-migration-logs__selector">
			<label for="stellarwp-execution-select" class="screen-reader-text">
				<?php esc_html_e( 'Select Execution', 'stellarwp-migrations' ); ?>
			</label>
			<select id="stellarwp-execution-select" class="stellarwp-migration-logs__select">
				<?php foreach ( $executions as $index => $execution ) : ?>
					<?php
					$exec_id     = $execution['id'] ?? 0;
					$exec_status = $execution['status'] ?? null;
					$created_at  = $execution['created_at'] ?? null;

					$status_label = '';
					if ( $exec_status instanceof Status ) {
						$status_label = $exec_status->get_label();
					}

					$date_label = '';
					if ( $created_at instanceof DateTimeInterface ) {
						$date_label = wp_date( 'M j, Y g:i a', $created_at->getTimestamp() );
					}

					$label = sprintf(
						/* translators: %1$d: execution number, %2$s: date, %3$s: status */
						__( '#%1$d - %2$s (%3$s)', 'stellarwp-migrations' ),
						$exec_id,
						$date_label,
						$status_label
					);

					$start_date = $execution['start_date_gmt'] instanceof DateTimeInterface ? wp_date( 'M j, Y g:i:s a', $execution['start_date_gmt']->getTimestamp() ) : '';
					$end_date   = $execution['end_date_gmt'] instanceof DateTimeInterface ? wp_date( 'M j, Y g:i:s a', $execution['end_date_gmt']->getTimestamp() ) : '';
					?>
					<option
						value="<?php echo esc_attr( (string) $exec_id ); ?>"
						<?php selected( 0 === $index ); ?>
						data-start="<?php echo esc_attr( $start_date && is_string( $start_date ) ? $start_date : '' ); ?>"
						data-end="<?php echo esc_attr( $end_date && is_string( $end_date ) ? $end_date : '' ); ?>"
						data-status="<?php echo $exec_status instanceof Status ? esc_attr( $exec_status->getValue() ) : ''; ?>"
					>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="stellarwp-migration-logs__execution-info">
			<span class="stellarwp-migration-logs__execution-start"></span>
			<span class="stellarwp-migration-logs__execution-end"></span>
		</div>

		<div class="stellarwp-migration-logs__container">
			<div class="stellarwp-migration-logs__list"></div>
			<div class="stellarwp-migration-logs__loading" style="display: none;">
				<span class="spinner is-active"></span>
				<span><?php esc_html_e( 'Loading logs...', 'stellarwp-migrations' ); ?></span>
			</div>
			<div class="stellarwp-migration-logs__no-logs" style="display: none;">
				<p><?php esc_html_e( 'No logs for this execution.', 'stellarwp-migrations' ); ?></p>
			</div>
			<div class="stellarwp-migration-logs__load-more" style="display: none;">
				<button type="button" class="button stellarwp-migration-logs__load-more-btn">
					<?php esc_html_e( 'Load More', 'stellarwp-migrations' ); ?>
				</button>
			</div>
		</div>
	<?php endif; ?>
</div>

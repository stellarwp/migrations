<?php
/**
 * Execution Logs Component Template.
 *
 * Displays logs for migration executions with load more functionality.
 *
 * @since 0.0.1
 * @version 0.0.1
 *
 * @package StellarWP\Migrations
 *
 * @var StellarWP\Migrations\Contracts\Migration $migration     Migration object.
 * @var string                                   $rest_base_url REST API base URL.
 * @var list<StellarWP\Migrations\Models\Execution> $executions List of execution records.
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
					$exec_id     = $execution->get_id();
					$exec_status = $execution->get_status();
					$created_at  = $execution->get_created_at();

					$status_label = '';
					if ( $exec_status instanceof Status ) {
						$status_label = $exec_status->get_label();
					}

					$date_label = wp_date( 'M j, Y g:i a', $created_at->getTimestamp() );

					$label = sprintf(
						/* translators: %1$d: execution number, %2$s: date, %3$s: status */
						__( '#%1$d - %2$s (%3$s)', 'stellarwp-migrations' ),
						$exec_id,
						$date_label,
						$status_label
					);

					$start_date_obj = $execution->get_start_date();
					$end_date_obj   = $execution->get_end_date();
					$start_date     = $start_date_obj ? wp_date( 'M j, Y g:i:s a', $start_date_obj->getTimestamp() ) : '';
					$end_date       = $end_date_obj ? wp_date( 'M j, Y g:i:s a', $end_date_obj->getTimestamp() ) : '';
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

		<div class="stellarwp-migration-logs__execution-info" aria-live="polite" aria-atomic="true">
			<span class="stellarwp-migration-logs__execution-start">
				<span class="screen-reader-text"><?php esc_html_e( 'Execution started:', 'stellarwp-migrations' ); ?></span>
			</span>
			<span class="stellarwp-migration-logs__execution-end">
				<span class="screen-reader-text"><?php esc_html_e( 'Execution ended:', 'stellarwp-migrations' ); ?></span>
			</span>
		</div>

		<div class="stellarwp-migration-logs__container" aria-live="polite" aria-relevant="additions removals">
			<div class="stellarwp-migration-logs__list" role="log" aria-label="<?php esc_attr_e( 'Migration execution logs', 'stellarwp-migrations' ); ?>"></div>
			<div class="stellarwp-migration-logs__loading" style="display: none;" aria-hidden="true">
				<span class="spinner is-active"></span>
				<span><?php esc_html_e( 'Loading logs...', 'stellarwp-migrations' ); ?></span>
			</div>
			<div class="stellarwp-migration-logs__no-logs" style="display: none;">
				<p><?php esc_html_e( 'No logs for this execution.', 'stellarwp-migrations' ); ?></p>
			</div>
		</div>
		<div class="stellarwp-migration-logs__load-more" style="display: none;">
			<button type="button" class="button stellarwp-migration-logs__load-more-btn" aria-label="<?php esc_attr_e( 'Load More Logs', 'stellarwp-migrations' ); ?>">
				<?php esc_html_e( 'Load More', 'stellarwp-migrations' ); ?>
			</button>
		</div>
	<?php endif; ?>
</div>

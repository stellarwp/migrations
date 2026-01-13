<?php
/**
 * Cleanup Logs Task.
 *
 * Shepherd task that cleans up old migration logs after a configurable retention period.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tasks
 */

declare(strict_types=1);

namespace StellarWP\Migrations\Tasks;

use StellarWP\DB\DB;
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Tables\Migration_Executions;
use StellarWP\Migrations\Tables\Migration_Logs;
use StellarWP\Migrations\Utilities\Cast;
use StellarWP\Migrations\Utilities\Logger;
use StellarWP\Shepherd\Abstracts\Task_Abstract;
use StellarWP\Shepherd\Exceptions\ShepherdTaskFailWithoutRetryException;
use InvalidArgumentException;

/**
 * Task to clean up old migration logs.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tasks
 */
class Cleanup_Logs extends Task_Abstract {
	/**
	 * Get the retention period in days for migration logs.
	 *
	 * @since 0.0.1
	 *
	 * @return int The retention period in days.
	 */
	public static function get_retention_days(): int {
		$default_retention_days = 180;

		$prefix = Config::get_hook_prefix();


		/**
		 * Filters the retention period in days for migration logs.
		 *
		 * @since 0.0.1
		 *
		 * @param int $retention_days The number of days to retain logs. Default is 180.
		 *
		 * @return int The retention period in days.
		 */
		$retention_days = (int) apply_filters( "stellarwp_migrations_{$prefix}_log_retention_days", $default_retention_days );

		// Fallback to the default retention days if the filtered value is invalid.
		return $retention_days > 1 ? $retention_days : $default_retention_days;
	}

	/**
	 * Process the task - clean up old logs.
	 *
	 * @return void
	 *
	 * @throws ShepherdTaskFailWithoutRetryException If the cleanup fails.
	 */
	public function process(): void {
		$retention_days = self::get_retention_days();

		error_log( 'Retention days: ' . $retention_days );
		return;

		// Calculate the cutoff date.
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		// Get all logs older than the retention period, grouped by execution_id.
		$logs_table       = Migration_Logs::table_name( true );
		$executions_table = Migration_Executions::table_name( true );

		// Query to get execution IDs and their statuses for logs that will be deleted.
		$executions_with_old_logs = DB::get_results(
			DB::prepare(
				'SELECT DISTINCT ml.migration_execution_id, me.status
				FROM %i AS ml
				INNER JOIN %i AS me ON ml.migration_execution_id = me.id
				WHERE ml.created_at < %s',
				$logs_table,
				$executions_table,
				$cutoff_date
			)
		);

		if ( empty( $executions_with_old_logs ) ) {
			// No logs to clean up.
			return;
		}

		// Delete the old logs.
		$deleted_count = DB::query(
			DB::prepare(
				'DELETE FROM %i WHERE created_at < %s',
				$logs_table,
				$cutoff_date
			)
		);

		if ( false === $deleted_count ) {
			throw new ShepherdTaskFailWithoutRetryException(
				'Failed to delete old migration logs.'
			);
		}

		// Add summary log entries for each execution that had logs deleted.
		$deletion_date = current_time( 'mysql', true );

		foreach ( $executions_with_old_logs as $execution_data ) {
			$execution_id = Cast::to_int( $execution_data->migration_execution_id );
			$status       = Cast::to_string( $execution_data->status );

			$logger = new Logger( $execution_id );
			$logger->info(
				sprintf(
					'Old logs deleted on %s. Migration execution status: %s.',
					$deletion_date,
					$status
				),
				[
					'deletion_date'  => $deletion_date,
					'status'         => $status,
					'retention_days' => $retention_days,
				]
			);
		}
	}

	/**
	 * Get the task prefix.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function get_task_prefix(): string {
		return 'mig_cleanup_logs_';
	}

	/**
	 * Get the task's group.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function get_group(): string {
		return sprintf( '%s_migrations', Config::get_hook_prefix() );
	}

	/**
	 * Validate the task's arguments.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException If the task's arguments are invalid.
	 */
	protected function validate_args(): void {
		// This task doesn't require any arguments.
	}
}

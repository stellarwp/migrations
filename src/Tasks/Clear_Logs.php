<?php
/**
 * Clear Logs Task.
 *
 * Shepherd task that clears old migration logs after a configurable retention period.
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
use StellarWP\Migrations\Utilities\Logger;
use StellarWP\Shepherd\Abstracts\Task_Abstract;
use StellarWP\Shepherd\Exceptions\ShepherdTaskFailWithoutRetryException;
use stdClass;
use StellarWP\Migrations\Utilities\Cast;

/**
 * Task to clear old migration logs.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tasks
 */
class Clear_Logs extends Task_Abstract {
	/**
	 * Get the retention period in days for migration logs.
	 *
	 * @since 0.0.1
	 *
	 * @return int The retention period in days.
	 */
	public static function get_retention_days(): int {
		$default_retention_days = 180;
		$prefix                 = Config::get_hook_prefix();


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
	 * Process the task - clear old logs.
	 *
	 * @return void
	 *
	 * @throws ShepherdTaskFailWithoutRetryException If the cleanup fails.
	 */
	public function process(): void {
		$retention_days = self::get_retention_days();

		// Calculate the cutoff date.
		$cutoff_date = gmdate( 'Y-m-d H:i:s', (int) strtotime( "-{$retention_days} days" ) );

		// Get all executions older than the retention period.

		$old_executions = DB::table( Migration_Executions::table_name( false ) )
			->select( 'id', 'status' )
			->where( 'end_date_gmt', $cutoff_date, '<' )
			->getAll();

		if (
			! is_array( $old_executions )
			|| empty( $old_executions )
		) {
			// No executions to process.
			return;
		}

		// Delete all logs for these execution IDs.

		$deleted_count = DB::table( Migration_Logs::table_name( false ) )
			->whereIn(
				'migration_execution_id',
				array_column( $old_executions, 'id' )
			)
			->delete();

		if ( false === $deleted_count ) {
			throw new ShepherdTaskFailWithoutRetryException(
				'Failed to delete old migration logs.'
			);
		}

		// Add summary log entries for each execution that was processed.

		$deletion_date = current_time( 'mysql', true );

		/** @var stdClass $execution */
		foreach ( $old_executions as $execution ) {
			$execution_id = Cast::to_int( $execution->id );
			$status       = Cast::to_string( $execution->status );

			$logger = new Logger( $execution_id );

			$logger->info(
				sprintf(
					'Old logs deleted on %s. Migration execution status: %s.',
					$deletion_date,
					$status
				),
				[
					'deletion_date'    => $deletion_date,
					'migration_status' => $status,
					'retention_days'   => $retention_days,
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
		return 'mig_clear_logs_';
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
}

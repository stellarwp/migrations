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
use InvalidArgumentException;

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
		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		// Get all executions older than the retention period.

		$old_executions = DB::table( Migration_Executions::table_name( true ) )
			->select( 'id', 'status' )
			->where( 'end_date_gmt', '<', $cutoff_date )
			->getAll();

		if ( empty( $old_executions ) ) {
			// No executions to process.
			return;
		}

		// Delete all logs for these execution IDs.

		$deleted_count = DB::table( Migration_Logs::table_name( true ) )
			->where( 'migration_execution_id', 'IN', $old_executions->pluck( 'id' ) )
			->delete();

		if ( false === $deleted_count ) {
			throw new ShepherdTaskFailWithoutRetryException(
				'Failed to delete old migration logs.'
			);
		}

		// Add summary log entries for each execution that had logs deleted.

		$deletion_date = current_time( 'mysql', true );

		foreach ( $old_executions as $execution ) {
			$logger = new Logger( $execution->id );

			$logger->info(
				sprintf(
					'Old logs deleted on %s. Migration execution status: %s.',
					$deletion_date,
					$execution->status
				),
				[
					'deletion_date'    => $deletion_date,
					'migration_status' => $execution->status,
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

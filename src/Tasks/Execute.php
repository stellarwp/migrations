<?php
/**
 * Run Migration Task.
 *
 * Shepherd task that executes a migration's up() method.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tasks
 */

declare(strict_types=1);

namespace StellarWP\Migrations\Tasks;

use StellarWP\Migrations\Enums\Status;
use StellarWP\Migrations\Tables\Migration_Executions;
use StellarWP\Migrations\Utilities\Cast;
use StellarWP\Migrations\Utilities\Logger;
use StellarWP\Shepherd\Abstracts\Task_Abstract;
use StellarWP\Shepherd\Exceptions\ShepherdTaskFailWithoutRetryException;
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Registry;
use StellarWP\Migrations\Contracts\Migration;
use Exception;
use InvalidArgumentException;
use function StellarWP\Shepherd\shepherd;

/**
 * Task to run a migration's up() method.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tasks
 */
class Execute extends Task_Abstract {
	/**
	 * Constructor.
	 *
	 * @param string $method        The method to run.
	 * @param string $migration_id  The migration id.
	 * @param int    $batch         The batch number.
	 * @param int    $batch_size    The batch size.
	 * @param int    $execution_id  The execution id.
	 * @param mixed  ...$extra_args Extra arguments controlled by each migration.
	 */
	public function __construct( string $method, string $migration_id, int $batch, int $batch_size, int $execution_id, ...$extra_args ) {
		parent::__construct( $method, $migration_id, $batch, $batch_size, $execution_id, ...$extra_args );
	}

	/**
	 * Process the task - run the migration.
	 *
	 * @return void
	 *
	 * @throws ShepherdTaskFailWithoutRetryException If the migration fails.
	 */
	public function process(): void {
		$args = $this->get_args();
		[ $method, $migration_id, $batch, $batch_size, $execution_id ] = [
			Cast::to_string( $args[0] ), // Method.
			Cast::to_string( $args[1] ), // Migration ID.
			Cast::to_int( $args[2] ), // Batch.
			Cast::to_int( $args[3] ), // Batch size.
			Cast::to_int( $args[4] ), // Execution ID.
		];

		unset( $args[0], $args[1], $args[2], $args[3], $args[4] ); // Remove default arguments.
		$extra_args = $args;

		$is_rollback    = 'down' === $method;
		$operation_type = $is_rollback ? 'Rollback' : 'Migration';

		$container = Config::get_container();
		$registry  = $container->get( Registry::class );
		$migration = $registry->get( $migration_id );
		$execution = Migration_Executions::get_first_by( 'id', $execution_id );

		if (
			! $migration
			|| ! $execution
			|| ! is_array( $execution )
		) {
			throw new ShepherdTaskFailWithoutRetryException(
				sprintf(
					'Migration "%1$s" or execution "%2$s" not found.',
					$migration_id,
					$execution_id
				)
			);
		}

		// Bind the Logger to the container with the execution ID.
		$container->singleton( Logger::class, static fn() => new Logger( $execution_id ) );

		$logger = $container->get( Logger::class );

		$method_to_check_if_done = "is_{$method}_done";

		if ( $migration->$method_to_check_if_done() ) {
			$this->handle_migration_completion( $method, $batch, $execution_id, $logger );

			return;
		}

		$logger->info(
			sprintf( '%s batch %d started.', $operation_type, $batch ),
			[
				'method'     => $method,
				'batch'      => $batch,
				'batch_size' => $batch_size,
				'extra_args' => $extra_args,
			]
		);

		// Update the execution status to running and record the start date.

		if (
				! $is_rollback // Rollback does not change the execution status.
				&& 1 === $batch
			) {

				Migration_Executions::update_single(
					[
						'id'             => $execution_id,
						'status'         => Status::RUNNING()->getValue(),
						'start_date_gmt' => current_time( 'mysql', true ),
					]
				);
		}

		$prefix = Config::get_hook_prefix();

		try {
			$migration->{"before_{$method}"}( $batch, $batch_size );

			/**
			 * Fires before a batch is processed.
			 *
			 * @param Migration $migration    The migration instance.
			 * @param string    $method       The method to run.
			 * @param int       $batch        The batch number.
			 * @param int       $batch_size   The batch size.
			 * @param int       $execution_id The execution id.
			 */
			do_action( "stellarwp_migrations_{$prefix}_before_{$method}_batch_processed", $migration, $method, $batch, $batch_size, $execution_id );

			/**
			 * Fires before a batch is processed.
			 *
			 * @param Migration $migration    The migration instance.
			 * @param string    $method       The method to run.
			 * @param int       $batch        The batch number.
			 * @param int       $batch_size   The batch size.
			 * @param int       $execution_id The execution id.
			 */
			do_action( "stellarwp_migrations_{$prefix}_before_batch_processed", $migration, $method, $batch, $batch_size, $execution_id );

			$migration->$method( $batch, $batch_size, ...$extra_args );

			/**
			 * Fires after a batch is processed successfully.
			 *
			 * @param Migration $migration    The migration instance.
			 * @param string    $method       The method to run.
			 * @param int       $batch        The batch number.
			 * @param int       $batch_size   The batch size.
			 * @param int       $execution_id The execution id.
			 */
			do_action( "stellarwp_migrations_{$prefix}_post_{$method}_batch_processed", $migration, $method, $batch, $batch_size, $execution_id );

			/**
			 * Fires after a batch is processed successfully.
			 *
			 * @param Migration $migration    The migration instance.
			 * @param string    $method       The method to run.
			 * @param int       $batch        The batch number.
			 * @param int       $batch_size   The batch size.
			 * @param int       $execution_id The execution id.
			 */
			do_action( "stellarwp_migrations_{$prefix}_post_batch_processed", $migration, $method, $batch, $batch_size, $execution_id );
		} catch ( Exception $e ) {
			/**
			 * Fires when a batch fails.
			 *
			 * @param Migration $migration    The migration instance.
			 * @param string    $method       The method to run.
			 * @param int       $batch        The batch number.
			 * @param int       $batch_size   The batch size.
			 * @param int       $execution_id The execution id.
			 * @param Exception $e            The exception.
			 */
			do_action( "stellarwp_migrations_{$prefix}_{$method}_batch_failed", $migration, $method, $batch, $batch_size, $execution_id, $e );

			/**
			 * Fires when a batch fails.
			 *
			 * @param Migration $migration    The migration instance.
			 * @param string    $method       The method to run.
			 * @param int       $batch        The batch number.
			 * @param int       $batch_size   The batch size.
			 * @param int       $execution_id The execution id.
			 * @param Exception $e            The exception.
			 */
			do_action( "stellarwp_migrations_{$prefix}_batch_failed", $migration, $method, $batch, $batch_size, $execution_id, $e );

			$logger->error(
				sprintf(
					'%s batch %d failed: %s',
					$operation_type,
					$batch,
					$e->getMessage()
				),
				[
					'method'     => $method,
					'batch'      => $batch,
					'batch_size' => $batch_size,
					'extra_args' => $extra_args,
					'exception'  => get_class( $e ),
					'trace'      => $e->getTraceAsString(),
				]
			);

			if ( ! $is_rollback ) {
				// Set the execution status to failed.
				Migration_Executions::update_single(
					[
						'id'     => $execution_id,
						'status' => Status::FAILED()->getValue(),
					]
				);

				// Start the rollback from the first batch.

				$logger->warning(
					'Rollback scheduled.',
					[
						'reason' => $e->getMessage(),
					]
				);

				// If it failed we need to trigger the rollback.
				shepherd()->dispatch( new self( 'down', $migration_id, 1, $batch_size, $execution_id, ...$migration->get_down_extra_args_for_batch( 1, $batch_size ) ) );
			}

			throw new ShepherdTaskFailWithoutRetryException(
				sprintf(
					'Batch "%d" for migration "%s" and method "%s" failed with message: %s',
					$batch,
					$migration_id,
					$method,
					$e->getMessage()
				)
			);
		}

		$is_completed = $migration->$method_to_check_if_done();

		$migration->{"after_{$method}"}( $batch, $batch_size, $is_completed );

		// Update the items processed count for successful migrations.

		if ( ! $is_rollback ) {
			Migration_Executions::update_single(
				[
					'id'              => $execution_id,
					'items_processed' => min( Cast::to_int( $execution['items_total'] ), Cast::to_int( $execution['items_processed'] ) + $batch_size ),
				]
			);
		}

		$logger->info(
			sprintf( '%s batch %d completed.', $operation_type, $batch ),
			[
				'method'     => $method,
				'batch'      => $batch,
				'batch_size' => $batch_size,
				'extra_args' => $extra_args,
			]
		);

		// If the migration is not completed, dispatch the next batch.

		if ( ! $is_completed ) {
			$next_batch = $batch + 1;

			/** @var array<mixed> $extra_args */
			$extra_args = $migration->{ "get_{$method}_extra_args_for_batch" }( $next_batch, $batch_size );

			$logger->info(
				sprintf( '%s batch %d scheduled for execution.', $operation_type, $next_batch ),
				[
					'method'     => $method,
					'batch'      => $next_batch,
					'batch_size' => $batch_size,
					'extra_args' => $extra_args,
				]
			);

			shepherd()->dispatch( new self( $method, $migration_id, $next_batch, $batch_size, $execution_id, ...$extra_args ) );

			return;
		}

		// Handle migration completion.

		$this->handle_migration_completion( $method, $batch, $execution_id, $logger );
	}

	/**
	 * Migrations should not retry automatically.
	 *
	 * @return int
	 */
	public function get_max_retries(): int {
		$container = Config::get_container();

		$registry  = $container->get( Registry::class );
		$migration = $registry->get( Cast::to_string( $this->get_args()[1] ) );

		if ( ! $migration ) {
			return 0;
		}

		return $migration->get_number_of_retries_per_batch();
	}

	/**
	 * Get the task prefix.
	 *
	 * @return string
	 */
	public function get_task_prefix(): string {
		return 'mig_' . Cast::to_string( $this->get_args()[0] ) . '_';
	}

	/**
	 * Get the task's group.
	 *
	 * @return string
	 */
	public function get_group(): string {
		return sprintf( '%s_migrations', Config::get_hook_prefix() );
	}

	/**
	 * Validate the task's arguments.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException If the task's arguments are invalid.
	 */
	protected function validate_args(): void {
		$args = $this->get_args();

		if ( count( $args ) < 5 ) {
			throw new InvalidArgumentException( 'Execute task requires at least 5 arguments: method, migration_id, batch, batch_size, execution_id.' );
		}

		if ( ! in_array( $args[0], [ 'up', 'down' ], true ) ) {
			throw new InvalidArgumentException( 'Execute task method must be either "up" or "down".' );
		}

		if ( ! is_string( $args[1] ) ) {
			throw new InvalidArgumentException( 'Execute task migration_id must be a string.' );
		}

		if ( ! is_int( $args[2] ) ) {
			throw new InvalidArgumentException( 'Execute task batch must be an integer.' );
		}

		if ( $args[2] < 1 ) {
			throw new InvalidArgumentException( 'Execute task batch must be greater than 0.' );
		}

		if ( ! is_int( $args[3] ) ) {
			throw new InvalidArgumentException( 'Execute task batch_size must be an integer.' );
		}

		if ( $args[3] < 1 ) {
			throw new InvalidArgumentException( 'Execute task batch_size must be greater than 0.' );
		}

		if ( ! is_int( $args[4] ) ) {
			throw new InvalidArgumentException( 'Execute task execution_id must be an integer.' );
		}

		if ( $args[4] < 1 ) {
			throw new InvalidArgumentException( 'Execute task execution_id must be greater than 0.' );
		}
	}

	/**
	 * Handle migration completion.
	 *
	 * @param string $method       The method to run.
	 * @param int    $batch        The batch number.
	 * @param int    $execution_id The execution ID.
	 * @param Logger $logger       The logger instance.
	 *
	 * @return void
	 */
	private function handle_migration_completion( string $method, int $batch, int $execution_id, Logger $logger ): void {
		$completion_status = null;

		// Rollback completion.
		if ( 'down' === $method ) {
			$logger->info(
				'Migration rollback completed.',
				[
					'total_batches' => $batch,
				]
			);

			$completion_status = Status::FAILED()->getValue();
		} else {
			// Successful migration completion.
			$logger->info(
				'Migration completed successfully.',
				[
					'total_batches' => $batch,
				]
			);

			$completion_status = Status::COMPLETED()->getValue();
		}

		Migration_Executions::update_single(
			[
				'id'           => $execution_id,
				'status'       => $completion_status,
				'end_date_gmt' => current_time( 'mysql', true ),
			]
		);
	}
}

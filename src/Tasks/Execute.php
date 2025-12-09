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

use StellarWP\Shepherd\Abstracts\Task_Abstract;
use StellarWP\Shepherd\Exceptions\ShepherdTaskFailWithoutRetryException;
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Registry;
use StellarWP\Migrations\Contracts\Migration;
use Exception;
use InvalidArgumentException;
use StellarWP\Migrations\Tables\Migration_Events;
use function StellarWP\Shepherd\shepherd;

// phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found

/**
 * Task to run a migration's up() method.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tasks;
 */
class Execute extends Task_Abstract {
	/**
	 * Constructor.
	 *
	 * @param string $method        The method to run.
	 * @param string $migration_id  The migration id.
	 * @param int    $batch         The batch number.
	 * @param mixed  ...$extra_args Extra arguments controlled by each migration.
	 */
	public function __construct( string $method, string $migration_id, int $batch, ...$extra_args ) {
		parent::__construct( $method, $migration_id, $batch, ...$extra_args );
	}

	/**
	 * Process the task - run the migration.
	 *
	 * @return void
	 *
	 * @throws ShepherdTaskFailWithoutRetryException If the migration fails.
	 */
	public function process(): void {
		$args                              = $this->get_args();
		[ $method, $migration_id, $batch ] = $args;

		unset( $args[0], $args[1], $args[2] );
		$extra_args = $args;

		$container = Config::get_container();
		$registry  = $container->get( Registry::class );

		$migration = $registry->get( $migration_id );

		if ( ! $migration ) {
			throw new ShepherdTaskFailWithoutRetryException(
				sprintf(
					'Migration "%s" not found.',
					$migration_id
				)
			);
		}

		$method_to_check_if_done = "is_{$method}_done";

		if ( $migration->$method_to_check_if_done() ) {
			return;
		}

		Migration_Events::insert(
			[
				'migration_id' => $migration->get_id(),
				'type'         => Migration_Events::TYPE_BATCH_STARTED,
				'data'         => [
					'args' => [ $method, $migration_id, $batch, ...$extra_args ],
				],
			]
		);

		$prefix = Config::get_hook_prefix();

		try {
			$migration->before( $batch, $method );

			/**
			 * Fires before a batch is processed.
			 *
			 * @param Migration $migration The migration instance.
			 * @param int       $batch     The batch number.
			 * @param string    $method    The method to run.
			 */
			do_action( "stellarwp_migrations_{$prefix}_before_{$method}_batch_processed", $migration, $batch, $method );

			/**
			 * Fires before a batch is processed.
			 *
			 * @param Migration $migration The migration instance.
			 * @param int       $batch     The batch number.
			 * @param string    $method    The method to run.
			 */
			do_action( "stellarwp_migrations_{$prefix}_before_batch_processed", $migration, $batch, $method );

			$migration->$method( $batch, ...$extra_args );

			/**
			 * Fires after a batch is processed successfully.
			 *
			 * @param Migration $migration The migration instance.
			 * @param int       $batch     The batch number.
			 * @param string    $method    The method to run.
			 */
			do_action( "stellarwp_migrations_{$prefix}_post_{$method}_batch_processed", $migration, $batch, $method );

			/**
			 * Fires after a batch is processed successfully.
			 *
			 * @param Migration $migration The migration instance.
			 * @param int       $batch     The batch number.
			 * @param string    $method    The method to run.
			 */
			do_action( "stellarwp_migrations_{$prefix}_post_batch_processed", $migration, $batch, $method );
		} catch ( Exception $e ) {
			/**
			 * Fires when a batch fails.
			 *
			 * @param Migration $migration The migration instance.
			 * @param int       $batch     The batch number.
			 * @param Exception $e         The exception.
			 */
			do_action( "stellarwp_migrations_{$prefix}_{$method}_batch_failed", $migration, $batch, $e );

			/**
			 * Fires when a batch fails.
			 *
			 * @param Migration $migration The migration instance.
			 * @param int       $batch     The batch number.
			 * @param Exception $e         The exception.
			 */
			do_action( "stellarwp_migrations_{$prefix}_batch_failed", $migration, $batch, $e );

			Migration_Events::insert(
				[
					'migration_id' => $migration->get_id(),
					'type'         => Migration_Events::TYPE_FAILED,
					'data'         => [
						'args'    => [ $method, $migration_id, $batch, ...$extra_args ],
						'message' => $e->getMessage(),
					],
				]
			);

			if ( 'up' === $method ) {
				Migration_Events::insert(
					[
						'migration_id' => $migration->get_id(),
						'type'         => Migration_Events::TYPE_SCHEDULED,
						'data'         => [
							'args'    => [ 'down', $migration_id, 1, ...$extra_args ],
							'message' => $e->getMessage(),
						],
					]
				);
				// If it failed we need to trigger the rollback.
				shepherd()->dispatch( new self( 'down', $migration_id, 1, ...$migration->get_down_extra_args_for_batch( 1 ) ) );
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

		$migration->after( $batch, $method, $is_completed );

		if ( ! $is_completed ) {
			Migration_Events::insert(
				[
					'migration_id' => $migration->get_id(),
					'type'         => Migration_Events::TYPE_BATCH_COMPLETED,
					'data'         => [
						'args' => [ $method, $migration_id, $batch, ...$extra_args ],
					],
				]
			);
			shepherd()->dispatch( new self( $method, $migration_id, $batch + 1, ...$migration->{ "get_{$method}_extra_args_for_batch" }( $batch + 1 ) ) );
			return;
		}

		Migration_Events::insert(
			[
				'migration_id' => $migration->get_id(),
				'type'         => Migration_Events::TYPE_COMPLETED,
				'data'         => [
					'args' => [ $method, $migration_id, $batch, ...$extra_args ],
				],
			]
		);
	}

	/**
	 * Migrations should not retry automatically.
	 *
	 * @return int
	 */
	public function get_max_retries(): int {
		return 0;
	}

	/**
	 * Get the task prefix.
	 *
	 * @return string
	 */
	public function get_task_prefix(): string {
		return 'mig_' . $this->get_args()[0] . '_';
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

		if ( count( $args ) < 3 ) {
			throw new InvalidArgumentException( 'Execute task requires at least 3 arguments: method, migration_id, batch.' );
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
	}
}

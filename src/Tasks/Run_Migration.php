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
use function StellarWP\Shepherd\shepherd;

/**
 * Task to run a migration's up() method.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tasks;
 */
class Run_Migration extends Task_Abstract {
	/**
	 * Constructor.
	 *
	 * @param string $migration_slug The migration slug.
	 * @param int    $batch          The batch number.
	 */
	public function __construct( string $migration_id, int $batch ) {
		parent::__construct( $migration_id, $batch );
	}

	/**
	 * Process the task - run the migration.
	 *
	 * @return void
	 *
	 * @throws ShepherdTaskFailWithoutRetryException If the migration fails.
	 */
	public function process(): void {
		[ $migration_id, $batch ] = $this->get_args();

		$container  = Config::get_container();
		$registry   = $container->get( Registry::class );

		$migration = $registry->get( $migration_id );

		if ( ! $migration ) {
			throw new ShepherdTaskFailWithoutRetryException(
				sprintf(
					'Migration "%s" not found.',
					$migration_id
				)
			);
		}

		if ( $migration->is_up_done() ) {
			return;
		}

		$prefix = Config::get_hook_prefix();

		try {
			$migration->before( $batch, 'up' );

			/**
			 * Fires before a batch is processed.
			 *
			 * @param Migration $migration The migration instance.
			 * @param int       $batch     The batch number.
			 */
			do_action( "stellarwp_migrations_{$prefix}_before_batch_processed", $migration, $batch );

			$migration->up( $batch );

			/**
			 * Fires after a batch is processed successfully.
			 *
			 * @param Migration $migration The migration instance.
			 * @param int       $batch     The batch number.
			 */
			do_action( "stellarwp_migrations_{$prefix}_after_batch_processed", $migration, $batch );
		} catch ( Exception $e ) {
			/**
			 * Fires when a batch fails.
			 *
			 * @param Migration $migration The migration instance.
			 * @param int       $batch     The batch number.
			 * @param Exception $e         The exception.
			 */
			do_action( "stellarwp_migrations_{$prefix}_batch_failed", $migration, $batch, $e );

			// If it failed we need to trigger the rollback.
			shepherd()->dispatch( new Rollback_Migration( $migration_id, 1 ) );

			throw new ShepherdTaskFailWithoutRetryException(
				sprintf(
					'Batch "%d" for migration "%s" failed with message: %s',
					$batch,
					$migration_id,
					$e->getMessage()
				)
			);
		}

		if ( ! $migration->is_up_done() ) {
			shepherd()->dispatch( new self( $migration_id, $batch + 1 ) );
			return;
		}

		$migration->after( $batch, 'up' );
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
		return 'mig_run_';
	}

	/**
	 * Get the task's group.
	 *
	 * @return string
	 */
	public function get_group(): string {
		return sprintf( '%s_migrations', Config::get_hook_prefix());
	}
}

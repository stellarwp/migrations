<?php
/**
 * Rollback Migration Task.
 *
 * Shepherd task that executes a migration's down() method.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tasks;
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
 * Task to rollback a migration (run down() method).
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tasks;
 */
class Rollback_Migration extends Task_Abstract {
	/**
	 * Constructor.
	 *
	 * @param string $migration_id The migration ID.
	 * @param int    $batch      The batch number.
	 */
	public function __construct( string $migration_id, int $batch ) {
		parent::__construct( $migration_id, $batch );
	}

	/**
	 * Process the task - rollback the migration.
	 *
	 * @return void
	 *
	 * @throws ShepherdTaskFailWithoutRetryException If the rollback fails.
	 */
	public function process(): void {
		[ $migration_id, $batch ] = $this->get_args();

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

		if ( $migration->is_rolled_back() ) {
			return;
		}

		$prefix = Config::get_hook_prefix();

		try {
			/**
			 * Fires before a batch is rolled back.
			 *
			 * @param Migration $migration The migration instance.
			 * @param int       $batch     The batch number.
			 */
			do_action( "stellarwp_migrations_{$prefix}_before_batch_rolled_back", $migration, $batch );

			$migration->down();

			/**
			 * Fires after a batch is rolled back successfully.
			 *
			 * @param Migration $migration The migration instance.
			 * @param int       $batch     The batch number.
			 */
			do_action( "stellarwp_migrations_{$prefix}_after_batch_rolled_back", $migration, $batch );
		} catch ( Exception $e ) {
			/**
			 * Fires when a batch fails to be rolled back.
			 *
			 * @param Migration $migration The migration instance.
			 * @param int       $batch     The batch number.
			 * @param Exception $e         The exception.
			 */
			do_action( "stellarwp_migrations_{$prefix}_batch_rolled_back_failed", $migration, $batch, $e );

			throw new ShepherdTaskFailWithoutRetryException(
				sprintf(
					'Batch "%d" for migration "%s" failed to be rolled back with message: %s',
					$batch,
					$migration_id,
					$e->getMessage()
				)
			);
		}

		wp_cache_flush();

		if ( ! $migration->is_rolled_back() ) {
			shepherd()->dispatch( new self( $migration_id, $batch + 1 ) );
		}
	}

	/**
	 * Rollbacks should not retry automatically.
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
		return 'mig_roll_';
	}

	/**
	 * Get the task's group.
	 *
	 * @return string
	 */
	public function get_group(): string {
		return sprintf( '%s_migrations', Config::get_hook_prefix() );
	}
}

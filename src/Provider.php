<?php
/**
 * Main Migrations Service Provider.
 *
 * @package StellarWP\Migrations
 */

declare(strict_types=1);

namespace StellarWP\Migrations;

use Exception;
use StellarWP\DB\DB;
use StellarWP\Migrations\Enums\Status;
use StellarWP\Migrations\Tables\Migration_Executions;
use StellarWP\Migrations\Utilities\Logger;
use StellarWP\Shepherd\Abstracts\Provider_Abstract;
use StellarWP\Shepherd\Provider as Shepherd_Provider;
use StellarWP\Shepherd\Config as Shepherd_Config;
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Tasks\Execute;
use StellarWP\Migrations\Tables\Provider as Tables_Provider;
use StellarWP\Migrations\CLI\Provider as CLI_Provider;
use StellarWP\Migrations\REST\Provider as REST_Provider;
use StellarWP\Migrations\Contracts\Migration;
use StellarWP\Migrations\Enums\Operation;
use function StellarWP\Shepherd\shepherd;

/**
 * Main service provider for the Migrations library.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations
 */
class Provider extends Provider_Abstract {

	/**
	 * Whether the provider has been registered.
	 *
	 * @since 0.0.1
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Register the service provider.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function register(): void {
		if ( Shepherd_Provider::is_registered() ) {
			$this->register_migrations();
			return;
		}

		$prefix = Config::get_hook_prefix();

		Shepherd_Config::set_container( $this->container );
		Shepherd_Config::set_hook_prefix( $prefix );

		if ( has_action( "shepherd_{$prefix}_tables_registered", [ $this, 'register_migrations' ] ) ) {
			return;
		}

		add_action( "shepherd_{$prefix}_tables_registered", [ $this, 'register_migrations' ] );

		$this->container->get( Shepherd_Provider::class )->register();
	}

	/**
	 * Register migrations.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function register_migrations(): void {
		if ( self::is_registered() ) {
			return;
		}

		$prefix = Config::get_hook_prefix();

		if ( ! did_action( "shepherd_{$prefix}_tables_registered" ) && ! doing_action( "shepherd_{$prefix}_tables_registered" ) ) {
			add_action( "shepherd_{$prefix}_tables_registered", [ $this, 'register_migrations' ] );
			return;
		}

		self::$registered = true;

		add_action( "stellarwp_migrations_{$prefix}_tables_registered", [ $this, 'on_migrations_schema_up' ] );

		$this->container->singleton( Registry::class );

		$tables_provider = $this->container->get( Tables_Provider::class );
		$tables_provider->register();
	}

	/**
	 * Called when the migrations schema is up.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function on_migrations_schema_up(): void {
		$prefix = Config::get_hook_prefix();

		$this->container->get( CLI_Provider::class )->register();
		$this->container->get( REST_Provider::class )->register();

		// During WP-CLI execution, we don't need to schedule migrations, we'll run them directly.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		add_action( 'shutdown', [ $this, 'trigger_migrations_scheduling_action' ], 100 );
		add_action( "stellarwp_migrations_{$prefix}_schedule_migrations", [ $this, 'schedule_migrations' ] );
	}

	/**
	 * Check if the provider is registered.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public static function is_registered(): bool {
		return self::$registered;
	}

	/**
	 * Reset registration status. Primarily for testing.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$registered = false;
		Shepherd_Config::reset();
	}

	/**
	 * Trigger the migrations scheduling action.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function trigger_migrations_scheduling_action(): void {
		$prefix = Config::get_hook_prefix();

		/**
		 * Fires when the migrations scheduling action is triggered.
		 *
		 * @since 0.0.1
		 */
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );
	}

	/**
	 * Schedule migrations.
	 *
	 * @since 0.0.1
	 *
	 * @throws Exception If the migration execution cannot be inserted.
	 *
	 * @return void
	 */
	public function schedule_migrations(): void {
		$prefix = Config::get_hook_prefix();


		/**
		 * Filters whether migrations should be automatically scheduled for this prefix.
		 *
		 * @since TBD
		 *
		 * @param bool $automatic_schedule Whether the migration should be automatically scheduled. Default is true.
		 *
		 * @return bool Whether the migration should be automatically scheduled.
		 */
		if ( ! apply_filters( "stellarwp_migrations_{$prefix}_automatic_schedule", true ) ) {
			return;
		}

		/**
		 * Fires before the migrations are scheduled.
		 *
		 * @since 0.0.1
		 */
		do_action( "stellarwp_migrations_{$prefix}_pre_schedule_migrations" );

		$migrations_registry = $this->container->get( Registry::class );

		/** @var Migration $migration */
		foreach ( $migrations_registry as $migration_id => $migration ) {
			if ( ! $migration->is_applicable() ) {
				continue;
			}

			if ( ! $migration->can_run() ) {
				continue;
			}

			// Check if there is already an execution for this migration.
			$existing_execution = Migration_Executions::get_first_by( 'migration_id', $migration_id );

			if ( $existing_execution ) {
				continue; // skip the automatic scheduling for this migration.
			}

			$insert_status = Migration_Executions::insert(
				[
					'migration_id'    => $migration_id,
					'status'          => Status::SCHEDULED()->getValue(),
					'items_total'     => $migration->get_total_items(),
					'items_processed' => 0,
				]
			);

			if ( ! $insert_status ) {
				throw new Exception(
					sprintf(
						// translators: %1$s is the migration ID.
						__( 'Failed to insert migration execution for migration "%1$s"', 'stellarwp-migrations' ),
						$migration_id
					)
				);
			}

			$execution_id = DB::last_insert_id();
			$batch_number = 1;
			$batch_size   = $migration->get_default_batch_size();

			/** @var array{0: string, 1: string, 2: int, 3: int, 4: int, ...} $args */
			$args = [ Operation::UP()->getValue(), $migration_id, $batch_number, $batch_size, $execution_id, ...$migration->get_up_extra_args_for_batch( $batch_number, $batch_size ) ];

			// Log the migration scheduling.

			$logger = new Logger( $execution_id );
			$logger->info(
				sprintf( 'Migration "%s" scheduled for execution.', $migration_id ),
				[
					'batch'       => $batch_number,
					'batch_size'  => $batch_size,
					'items_total' => $migration->get_total_items(),
					'extra_args'  => $migration->get_up_extra_args_for_batch( $batch_number, $batch_size ),
				]
			);

			shepherd()->dispatch( new Execute( ...$args ) );
		}

		/**
		 * Fires after the migrations are scheduled.
		 *
		 * @since 0.0.1
		 */
		do_action( "stellarwp_migrations_{$prefix}_post_schedule_migrations" );
	}
}

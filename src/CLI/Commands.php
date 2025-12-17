<?php
/**
 * WP-CLI Commands for Migrations.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\CLI
 */

declare(strict_types=1);

namespace StellarWP\Migrations\CLI;

use WP_CLI;
use WP_CLI\Utils;
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Registry;
use StellarWP\Migrations\Contracts\Migration;
use StellarWP\Migrations\Tasks\Execute;
use StellarWP\Shepherd\Contracts\Task;
use function StellarWP\Shepherd\shepherd;
use function WP_CLI\Utils\make_progress_bar;

/**
 * Manage database migrations.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\CLI
 *
 * ## EXAMPLES
 *
 *     # Run all pending migrations
 *     $ wp migrations run
 *
 *     # Run migrations for a specific plugin
 *     $ wp migrations run --plugin=tec
 *
 *     # Rollback last batch of migrations
 *     $ wp migrations rollback --plugin=tec
 *
 *     # Show migration status
 *     $ wp migrations status
 */
class Commands {
	/**
	 * List registered migrations.
	 *
	 * ## OPTIONS
	 *
	 * [--tags=<tags>]
	 * : The tags to list migrations for. If not specified, lists for all registered migrations.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv, yaml. Default: table.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # List all registered migrations
	 *     $ wp migrations list
	 *
	 *     # List migrations for a specific plugin
	 *     $ wp migrations list --tags=tec,ld
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function list( array $args, array $assoc_args ): void {
		$tags   = Utils\get_flag_value( $assoc_args, 'tags', '' );
		$format = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$tags = explode( ',', $tags );

		$container = Config::get_container();
		$registry  = $container->get( Registry::class );

		if ( ! empty( $tags ) ) {
			$registry->filter( fn( ?Migration $migration ): bool => $migration && in_array( $tags, $migration->get_tags(), true ) );
		}

		$items = $registry->all();

		if ( empty( $items ) ) {
			WP_CLI::log( 'No migrations found.' );
			return;
		}

		$migrations_as_arrays = [];

		foreach ( $items as $migration_id => $migration ) {
			$migrations_as_arrays[] = array_merge( [ 'id' => $migration_id ], $migration->to_array() );
		}

		Utils\format_items(
			$format,
			$migrations_as_arrays,
			[ 'id', 'label', 'description', 'tags', 'total_batches', 'can_run', 'is_applicable', 'status' ]
		);
	}

	/**
	 * Run a migration.
	 *
	 * ## OPTIONS
	 *
	 * <migration_ids>...
	 * : The migration ID to run.
	 *
	 * [--from-batch=<batch>]
	 * : The batch number to start from. If not specified, starts from the first batch.
	 *
	 * [--to-batch=<batch>]
	 * : The batch number to end at. If not specified, ends at the last batch.
	 *
	 * [--batch-size=<batch-size>]
	 * : The number of batches to run at once. If not specified, runs one batch at a time.
	 *
	 * [--in-parallel]
	 * : Whether to run the batches in parallel. If not specified, runs the batches sequentially.
	 *
	 * ## EXAMPLES
	 *
	 *     # Run a migration
	 *     $ wp migrations run my_migration
	 *
	 *     # Run a migration for a specific batch
	 *     $ wp migrations run my_migration --from-batch=1 --to-batch=10
	 *
	 *     # Run a migration in parallel
	 *     $ wp migrations run my_migration --in-parallel
	 *
	 *     # Run a migration for a specific batch in parallel
	 *     $ wp migrations run my_migration --from-batch=1 --to-batch=10 --in-parallel
	 *
	 *     # Run a migration for a specific batch size
	 *     $ wp migrations run my_migration --batch-size=10
	 *
	 *     # Run a migration for a specific batch size in parallel
	 *     $ wp migrations run my_migration --batch-size=10 --in-parallel
	 *
	 *     # Run a migration for a specific batch size in parallel for a specific batch
	 *     $ wp migrations run my_migration --batch-size=10 --in-parallel --from-batch=1 --to-batch=10
	 *
	 *     # Run a migration for a specific batch size in parallel for a specific batch
	 *     $ wp migrations run my_migration --batch-size=10 --in-parallel --from-batch=1 --to-batch=10
	 *
	 *     # Run a migration for a specific batch size in parallel for a specific batch
	 *     $ wp migrations run my_migration --batch-size=10 --in-parallel --from-batch=1 --to-batch=10
	 *
	 * @subcommand run
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function run( array $args, array $assoc_args ): void {
		$migration_id = $args[0] ?? null;

		if ( ! $migration_id ) {
			WP_CLI::error( 'Migration ID is required.' );
		}

		$container = Config::get_container();
		$registry  = $container->get( Registry::class );

		$migration = $registry->get( $migration_id );

		if ( ! $migration ) {
			WP_CLI::error( "Migration with ID {$migration_id} not found." );
		}

		$batch_size    = Utils\get_flag_value( $assoc_args, 'batch-size', $migration->get_default_batch_size() );
		$total_batches = $migration->get_total_batches( $batch_size );

		$from_batch = Utils\get_flag_value( $assoc_args, 'from-batch', 1 );
		$to_batch   = Utils\get_flag_value( $assoc_args, 'to-batch', $total_batches );

		$from_batch = max( 1, $from_batch );
		$to_batch   = min( $to_batch, $total_batches );

		$in_parallel = isset( $assoc_args['in-parallel'] );

		if ( $in_parallel ) {
			WP_CLI::error( 'Running migrations in parallel is not supported yet.' );
		}

		$tasks = [];

		for ( $i = $from_batch; $i <= $to_batch; $i += 1 ) {
			$task = new Execute( 'up', $migration_id, $i, $batch_size, ...$migration->get_up_extra_args_for_batch( $i ) );
			$tasks[] = $task;
		}

		$batches = count( $tasks );

		$progress_bar = make_progress_bar(
			"Running `{$batches}` batches for migration `{$migration_id}`. From batch `{$from_batch}` to batch `{$to_batch}` with a batch size of `{$batch_size}`.",
			$batches
		);

		$callables = [
			'before' => function ( Task $task ) use ( $progress_bar ): void {
				WP_CLI::log( "Running batch `{$task->get_args()[2]}` for migration `{$task->get_args()[1]}`." );
			},
			'after' => function ( Task $task ) use ( $progress_bar ): void {
				$progress_bar->tick();
			},
			'always' => function ( array $tasks ) use ( $progress_bar ): void {
				$progress_bar->finish();
			},
		];

		shepherd()->run( $tasks, $callables );
	}
}

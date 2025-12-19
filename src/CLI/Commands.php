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
use StellarWP\Migrations\Enums\Operation;
use StellarWP\Migrations\Tables\Migration_Events;
use StellarWP\DB\DB;
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
		$this->run_operation( Operation::UP(), $args, $assoc_args );
	}

	/**
	 * Rollback a migration.
	 *
	 * ## OPTIONS
	 *
	 * <migration_ids>...
	 * : The migration ID to rollback.
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
	 *     # Rollback a migration
	 *     $ wp migrations rollback my_migration
	 *
	 *     # Rollback a migration for a specific batch
	 *     $ wp migrations rollback my_migration --from-batch=1 --to-batch=10
	 *
	 *     # Rollback a migration in parallel
	 *     $ wp migrations rollback my_migration --in-parallel
	 *
	 *     # Rollback a migration for a specific batch in parallel
	 *     $ wp migrations rollback my_migration --from-batch=1 --to-batch=10 --in-parallel
	 *
	 *     # Rollback a migration for a specific batch size
	 *     $ wp migrations rollback my_migration --batch-size=10
	 *
	 *     # Rollback a migration for a specific batch size in parallel
	 *     $ wp migrations rollback my_migration --batch-size=10 --in-parallel
	 *
	 *     # Rollback a migration for a specific batch size in parallel for a specific batch
	 *     $ wp migrations rollback my_migration --batch-size=10 --in-parallel --from-batch=1 --to-batch=10
	 *
	 *     # Rollback a migration for a specific batch size in parallel for a specific batch
	 *     $ wp migrations rollback my_migration --batch-size=10 --in-parallel --from-batch=1 --to-batch=10
	 *
	 *     # Rollback a migration for a specific batch size in parallel for a specific batch
	 *     $ wp migrations rollback my_migration --batch-size=10 --in-parallel --from-batch=1 --to-batch=10
	 *
	 * @subcommand rollback
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function rollback( array $args, array $assoc_args ): void {
		$this->run_operation( Operation::DOWN(), $args, $assoc_args );
	}

	/**
	 * List logs for a specific migration.
	 *
	 * ## OPTIONS
	 *
	 * <migration_id>
	 * : The migration ID to list logs for.
	 *
	 * [--type=<type>]
	 * : Filter logs by event type (e.g., scheduled, batch-started, batch-completed, completed, failed).
	 *
	 * [--limit=<limit>]
	 * : Limit the number of results returned.
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
	 *     # List logs for a specific migration
	 *     $ wp migrations logs my_migration
	 *
	 *     # List logs in JSON format
	 *     $ wp migrations logs my_migration --format=json
	 *
	 *     # List only failed logs
	 *     $ wp migrations logs my_migration --type=failed
	 *
	 *     # List the last 10 logs
	 *     $ wp migrations logs my_migration --limit=10
	 *
	 * @since 0.0.1
	 *
	 * @subcommand logs
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function logs( array $args, array $assoc_args ): void {
		$migration_id = $args[0] ?? null;

		if ( ! $migration_id ) {
			WP_CLI::error( 'Migration ID is required.' );
		}

		$container = Config::get_container();
		$registry  = $container->get( Registry::class );
		$migration = $registry->get( $migration_id );

		if ( ! $migration ) {
			WP_CLI::error( "Migration with ID '{$migration_id}' not found." );
		}

		$format     = Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$type       = Utils\get_flag_value( $assoc_args, 'type', '' );
		$limit      = Utils\get_flag_value( $assoc_args, 'limit', 0 );
		$table_name = Migration_Events::table_name();

		$query      = 'SELECT id, migration_id, type, data, created_at FROM %i WHERE migration_id = %s';
		$query_args = [ $table_name, $migration_id ];

		if ( $type ) {
			$query       .= ' AND type = %s';
			$query_args[] = $type;
		}

		$query .= ' ORDER BY created_at ASC';

		if ( $limit > 0 ) {
			$query       .= ' LIMIT %d';
			$query_args[] = (int) $limit;
		}

		$results = DB::get_results(
			DB::prepare( $query, ...$query_args ),
			ARRAY_A
		);

		if ( empty( $results ) ) {
			WP_CLI::log( "No logs found for migration '{$migration_id}'." );
			return;
		}

		Utils\format_items(
			$format,
			$results,
			[ 'id', 'migration_id', 'type', 'data', 'created_at' ]
		);
	}

	/**
	 * Run a migration operation.
	 *
	 * @param Operation $operation  The operation to run.
	 * @param array     $args       The arguments.
	 * @param array     $assoc_args The associative arguments.
	 *
	 * @return void
	 */
	private function run_operation( Operation $operation, array $args, array $assoc_args ): void {
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
		$total_batches = $migration->get_total_batches( $batch_size, $operation );

		$from_batch = Utils\get_flag_value( $assoc_args, 'from-batch', 1 );
		$to_batch   = Utils\get_flag_value( $assoc_args, 'to-batch', $total_batches );

		$from_batch = max( 1, $from_batch );
		$to_batch   = min( $to_batch, $total_batches );

		$in_parallel = isset( $assoc_args['in-parallel'] );

		if ( $in_parallel ) {
			WP_CLI::error( 'Running migrations in parallel is not supported yet.' );
		}

		$tasks = [];

		for ( $i = $from_batch; $i <= $to_batch; $i++ ) {
			$task    = new Execute( $operation->getValue(), $migration_id, $i, $batch_size, ...$migration->{'get_' . $operation->getValue() . '_extra_args_for_batch'}( $i, $batch_size ) );
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
			'after'  => function () use ( $progress_bar ): void {
				$progress_bar->tick();
			},
			'always' => function () use ( $progress_bar ): void {
				$progress_bar->finish();
			},
		];

		shepherd()->run( $tasks, $callables );
	}
}

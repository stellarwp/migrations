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
use StellarWP\Migrations\Tables\Migration_Logs;
use StellarWP\Migrations\Tables\Migration_Executions;
use StellarWP\Migrations\Utilities\Cast;
use StellarWP\Migrations\Enums\Status;
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
	 * @since 0.0.1
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
	 * @param array<mixed>               $args       Positional arguments.
	 * @param array<string, bool|string> $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function list( array $args, array $assoc_args ): void {
		/** @var string $tags_string */
		$tags_string = Utils\get_flag_value( $assoc_args, 'tags', '' );
		/** @var string $format */
		$format = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$tags = array_filter( explode( ',', $tags_string ) );

		$container = Config::get_container();
		$registry  = $container->get( Registry::class );

		if ( ! empty( $tags ) ) {
			$registry = $registry->filter(
				fn( ?Migration $migration ): bool => $migration && ! empty( array_intersect( $tags, $migration->get_tags() ) )
			);
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
	 * @since 0.0.1
	 *
	 * ## OPTIONS
	 *
	 * <migration_id>
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
	 *     # Run a migration with all options combined
	 *     $ wp migrations run my_migration --batch-size=10 --in-parallel --from-batch=1 --to-batch=10
	 *
	 * @subcommand run
	 *
	 * @param array<mixed>               $args       Positional arguments.
	 * @param array<string, bool|string> $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function run( array $args, array $assoc_args ): void {
		$this->run_operation( Operation::UP(), $args, $assoc_args );
	}

	/**
	 * Rollback a migration.
	 *
	 * @since 0.0.1
	 *
	 * ## OPTIONS
	 *
	 * <migration_id>
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
	 *     # Rollback a migration with all options combined
	 *     $ wp migrations rollback my_migration --batch-size=10 --in-parallel --from-batch=1 --to-batch=10
	 *
	 * @subcommand rollback
	 *
	 * @param array<mixed>               $args       Positional arguments.
	 * @param array<string, bool|string> $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function rollback( array $args, array $assoc_args ): void {
		$this->run_operation( Operation::DOWN(), $args, $assoc_args );
	}

	/**
	 * List logs for a specific migration.
	 *
	 * @since 0.0.1
	 *
	 * ## OPTIONS
	 *
	 * <execution_id>
	 * : The execution ID to list logs for.
	 *
	 * [--type=<type>]
	 * : Filter logs by log type (e.g., info, warning, error, debug). Accepts multiple types separated by commas.
	 *
	 * [--not-type=<not-type>]
	 * : Filter logs by log type that is not the specified type (e.g., info, warning, error, debug). Accepts multiple types separated by commas.
	 *
	 * [--search=<search>]
	 * : Filter logs by search term.
	 *
	 * [--limit=<limit>]
	 * : Limit the number of results returned. Default is 100.
	 *
	 * [--offset=<offset>]
	 * : Offset the results by the specified number of records. Default is 0.
	 *
	 * [--order=<order>]
	 * : Order the results by ASC or DESC. Default is DESC.
	 *
	 * [--order-by=<order-by>]
	 * : Order the results by the specified column. Default is created_at.
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
	 *     # List logs for a specific execution
	 *     $ wp migrations logs 123
	 *
	 *     # List logs in JSON format
	 *     $ wp migrations logs 123 --format=json
	 *
	 *     # List only failed logs
	 *     $ wp migrations logs 123 --type=error
	 *
	 *     # List the last 10 logs
	 *     $ wp migrations logs 123 --limit=10
	 *
	 *     # List logs for a specific migration
	 *     $ wp migrations logs 123 --search="failed to update record"
	 *
	 *     # List logs for a specific type
	 *     $ wp migrations logs 123 --type=error
	 *
	 *     # List logs for a specific search term and not type
	 *     $ wp migrations logs 123 --not-type=info --search="failed to update record"
	 *
	 *     # List logs for a specific search term and not multiple types
	 *     $ wp migrations logs 123 --not-type=info,debug --search="failed to update record"
	 *
	 * @subcommand logs
	 *
	 * @param array<mixed>               $args       Positional arguments.
	 * @param array<string, bool|string> $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function logs( array $args, array $assoc_args ): void {
		$execution_id = $args[0] ?? null;

		if ( ! $execution_id ) {
			WP_CLI::error( 'Execution ID is required.' );
		}

		/** @var int $execution_id */
		$execution = Migration_Executions::get_by_id( $execution_id );

		if ( ! $execution || ! is_array( $execution ) ) {
			WP_CLI::error( "Execution with ID '{$execution_id}' not found." );
		}

		$migration_id = ! empty( $execution['migration_id'] ) ? $execution['migration_id'] : false;

		if ( ! $migration_id ) {
			WP_CLI::error( "Execution with ID '{$execution_id}' not found." );
		}

		$container = Config::get_container();
		$registry  = $container->get( Registry::class );
		/** @var string $migration_id */
		$migration = $registry->get( $migration_id );

		if ( ! $migration ) {
			WP_CLI::error( "The migration associated with execution '{$execution_id}' is no longer available." );
		}

		/** @var string $format */
		$format = Utils\get_flag_value( $assoc_args, 'format', 'table' );
		/** @var string $types */
		$types = Utils\get_flag_value( $assoc_args, 'type', '' );
		/** @var string $not_types */
		$not_types = Utils\get_flag_value( $assoc_args, 'not-type', '' );
		$limit     = Cast::to_int( Utils\get_flag_value( $assoc_args, 'limit', 100 ) );
		$offset    = Cast::to_int( Utils\get_flag_value( $assoc_args, 'offset', 0 ) );
		$order     = strtoupper( Cast::to_string( Utils\get_flag_value( $assoc_args, 'order', 'DESC' ) ) );
		/** @var string $order_by */
		$order_by = Utils\get_flag_value( $assoc_args, 'order-by', 'created_at' );
		/** @var string $search */
		$search = Utils\get_flag_value( $assoc_args, 'search', '' );

		// Validate order direction.
		if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
			WP_CLI::error( 'Invalid order direction. Use ASC or DESC.' );
		}

		// Validate order-by column.
		$allowed_order_by = [ 'id', 'type', 'created_at' ];
		if ( ! in_array( $order_by, $allowed_order_by, true ) ) {
			WP_CLI::error( sprintf( 'Invalid order-by column. Allowed: %s', implode( ', ', $allowed_order_by ) ) );
		}

		$arguments = [
			'offset'                 => $offset,
			'orderby'                => $order_by,
			'order'                  => $order,
			'query_operator'         => 'AND',
			'migration_execution_id' => [
				'column'   => 'migration_execution_id',
				'value'    => $execution_id,
				'operator' => '=',
			],
		];

		if ( $search ) {
			$arguments['term'] = $search;
		}

		if ( $types && $not_types ) {
			WP_CLI::error( 'Cannot filter by type and not-type at the same time. Use one or the other.' );
		}

		if ( $types ) {
			$types             = explode( ',', $types );
			$arguments['type'] = [
				'query_operator' => 'OR',
			];
			foreach ( $types as $type ) {
				$arguments['type'][] = [
					'column'   => 'type',
					'value'    => $type,
					'operator' => '=',
				];
			}
		}

		if ( $not_types ) {
			$not_types             = explode( ',', $not_types );
			$arguments['not_type'] = [
				'query_operator' => 'AND',
			];
			foreach ( $not_types as $type ) {
				$arguments['not_type'][] = [
					'column'   => 'type',
					'value'    => $type,
					'operator' => '!=',
				];
			}
		}

		$logs = Migration_Logs::paginate(
			$arguments,
			$limit
		);

		if ( empty( $logs ) ) {
			WP_CLI::log( "No logs found for execution '{$execution_id}' of migration '{$migration_id}'." );
			return;
		}

		Utils\format_items(
			$format,
			$logs,
			[ 'id', 'type', 'message', 'data', 'created_at' ]
		);
	}

	/**
	 * List executions.
	 *
	 * @since 0.0.1
	 *
	 * ## OPTIONS
	 *
	 * <migration_id>
	 * : The migration ID to list executions for.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     # List executions for a specific migration
	 *     $ wp migrations executions my_migration
	 *
	 *     # List executions in JSON format
	 *     $ wp migrations executions my_migration --format=json
	 *
	 * @subcommand executions
	 *
	 * @param array<mixed>               $args       Positional arguments.
	 * @param array<string, bool|string> $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function executions( array $args, array $assoc_args ): void {
		$migration_id = $args[0] ?? null;

		if ( ! $migration_id ) {
			WP_CLI::error( 'Migration ID is required.' );
		}

		/** @var string $format */
		$format = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$executions = Migration_Executions::get_all_by( 'migration_id', $migration_id );

		Utils\format_items( $format, $executions, [ 'id', 'migration_id', 'start_date_gmt', 'end_date_gmt', 'status', 'items_total', 'items_processed', 'created_at' ] );
	}

	/**
	 * Run a migration operation.
	 *
	 * @since 0.0.1
	 *
	 * @param Operation                  $operation  The operation to run.
	 * @param array<mixed>               $args       The arguments.
	 * @param array<string, bool|string> $assoc_args The associative arguments.
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

		/** @var string $migration_id */
		$migration = $registry->get( $migration_id );

		if ( ! $migration ) {
			WP_CLI::error( "Migration with ID {$migration_id} not found." );
		}

		/** @var Migration $migration */
		$batch_size    = Cast::to_int( Utils\get_flag_value( $assoc_args, 'batch-size', $migration->get_default_batch_size() ) );
		$total_batches = $migration->get_total_batches( $batch_size, $operation );

		$from_batch = Cast::to_int( Utils\get_flag_value( $assoc_args, 'from-batch', 1 ) );
		$to_batch   = Cast::to_int( Utils\get_flag_value( $assoc_args, 'to-batch', $total_batches ) );

		$from_batch = max( 1, $from_batch );
		$to_batch   = min( $to_batch, $total_batches );

		if ( $from_batch > $to_batch ) {
			WP_CLI::error( 'from-batch cannot be greater than to-batch.' );
		}

		$in_parallel = isset( $assoc_args['in-parallel'] );

		if ( $in_parallel ) {
			WP_CLI::error( 'Running migrations in parallel is not supported yet.' );
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
			WP_CLI::error(
				sprintf(
					// translators: %1$s is the migration ID.
					__( 'Failed to insert migration execution for migration "%1$s"', 'stellarwp-migrations' ),
					$migration_id
				)
			);
		}

		$execution_id = DB::last_insert_id();

		$tasks = [];

		for ( $i = $from_batch; $i <= $to_batch; $i++ ) {
			$task    = new Execute( $operation->getValue(), $migration_id, $i, $batch_size, $execution_id, ...$migration->{'get_' . $operation->getValue() . '_extra_args_for_batch'}( $i, $batch_size ) );
			$tasks[] = $task;
		}

		$batches = count( $tasks );

		/** @var \cli\progress\Bar $progress_bar */
		$progress_bar = make_progress_bar(
			"Running `{$batches}` batches for migration `{$migration_id}`. From batch `{$from_batch}` to batch `{$to_batch}` with a batch size of `{$batch_size}`.",
			$batches
		);

		$callables = [
			'before' => function ( Task $task ): void {
				$migration_id = Cast::to_string( $task->get_args()[1] );
				$batch        = Cast::to_int( $task->get_args()[2] );
				WP_CLI::log( "Running batch `{$batch}` for migration `{$migration_id}`." );
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

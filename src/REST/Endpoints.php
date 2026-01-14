<?php
/**
 * REST Endpoints for Migrations.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\REST
 */

declare(strict_types=1);

namespace StellarWP\Migrations\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Registry;
use StellarWP\Migrations\Contracts\Migration;
use StellarWP\Migrations\Tasks\Execute;
use StellarWP\Migrations\Enums\Operation;
use StellarWP\Migrations\Tables\Migration_Executions;
use StellarWP\Migrations\Traits\API_Methods;
use StellarWP\Migrations\Utilities\Cast;
use StellarWP\Migrations\Enums\Status;
use StellarWP\Migrations\Exceptions\ApiMethodException;
use StellarWP\DB\DB;
use function StellarWP\Shepherd\shepherd;

/**
 * REST API Endpoints for Migrations.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\REST
 */
class Endpoints {
	use API_Methods;

	/**
	 * Check if the current user can manage migrations.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public function can_manage_migrations(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * List registered migrations.
	 *
	 * @since 0.0.1
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function list( WP_REST_Request $request ): WP_REST_Response {
		/** @var string $tags_string */
		$tags_string = $request->get_param( 'tags' ) ?? '';

		$items = $this->get_list( $tags_string );

		$items = $this->normalize_items( $items, 'json' );

		return new WP_REST_Response( $items, 200 );
	}

	/**
	 * Run a migration.
	 *
	 * @since 0.0.1
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function run( WP_REST_Request $request ): WP_REST_Response {
		return $this->run_operation( Operation::UP(), $request );
	}

	/**
	 * Rollback a migration.
	 *
	 * @since 0.0.1
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function rollback( WP_REST_Request $request ) {
		return $this->run_operation( Operation::DOWN(), $request );
	}

	/**
	 * List logs for a specific execution.
	 *
	 * @since 0.0.1
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function logs( WP_REST_Request $request ): WP_REST_Response {
		$execution_id = $request->get_param( 'execution_id' ) ?? null;

		if ( ! ( $execution_id && is_numeric( $execution_id ) ) ) {
			return $this->error( __( 'Execution ID is required.', 'stellarwp-migrations' ) );
		}

		$execution_id = Cast::to_int( $execution_id );

		/** @var string $types */
		$types = $request->get_param( 'type' ) ?? '';
		/** @var string $not_types */
		$not_types = $request->get_param( 'not-type' ) ?? '';
		$limit     = Cast::to_int( $request->get_param( 'limit' ) ?? 100 );
		$offset    = Cast::to_int( $request->get_param( 'offset' ) ?? 0 );
		$order     = strtoupper( Cast::to_string( $request->get_param( 'order' ) ?? 'DESC' ) );

		/** @var string $order_by */
		$order_by = $request->get_param( 'order-by' ) ?? 'created_at';
		/** @var string $search */
		$search = $request->get_param( 'search' ) ?? '';

		try {
			$items = $this->get_logs( $execution_id, $types, $not_types, $limit, $offset, $order, $order_by, $search );

			$items = $this->normalize_items( $items, 'json' );

			return new WP_REST_Response( $items, 200 );
		} catch ( ApiMethodException $e ) {
			return $this->error( $e->getMessage() );
		}
	}

	/**
	 * List executions for a migration.
	 *
	 * @since 0.0.1
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function executions( WP_REST_Request $request ) {
		$migration_id = $request->get_param( 'migration_id' ) ?? null;

		if ( ! $migration_id ) {
			return $this->error( __( 'Migration ID is required.', 'stellarwp-migrations' ) );
		}

		// Validate migration exists.
		$container = Config::get_container();
		$registry  = $container->get( Registry::class );
		/** @var string $migration_id */
		$migration = $registry->get( $migration_id );

		if ( ! $migration ) {
			return $this->error( "Migration with ID {$migration_id} not found." );
		}

		$items = $this->get_executions( $migration_id );

		$items = $this->normalize_items( $items, 'json' );

		return new WP_REST_Response( $items, 200 );
	}

	/**
	 * Run a migration operation.
	 *
	 * @since 0.0.1
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @param Operation       $operation The operation to run.
	 * @param WP_REST_Request $request   The request object.
	 *
	 * @return WP_REST_Response
	 */
	protected function run_operation( Operation $operation, WP_REST_Request $request ): WP_REST_Response {
		$migration_id = $request->get_param( 'migration_id' ) ?? null;

		if ( ! $migration_id ) {
			return $this->error( __( 'Migration ID is required.', 'stellarwp-migrations' ) );
		}

		$container = Config::get_container();
		$registry  = $container->get( Registry::class );

		/** @var string $migration_id */
		$migration = $registry->get( $migration_id );

		if ( ! $migration ) {
			return $this->error( "Migration with ID {$migration_id} not found." );
		}

		/** @var Migration $migration */
		$batch_size    = Cast::to_int( $request->get_param( 'batch-size' ) ?? $migration->get_default_batch_size() );
		$total_batches = $migration->get_total_batches( $batch_size, $operation );

		$from_batch = Cast::to_int( $request->get_param( 'from-batch' ) ?? 1 );
		$to_batch   = Cast::to_int( $request->get_param( 'to-batch' ) ?? $total_batches );

		$from_batch = max( 1, $from_batch );
		$to_batch   = min( $to_batch, $total_batches );

		if ( $from_batch > $to_batch ) {
			return $this->error( __( 'from-batch cannot be greater than to-batch.', 'stellarwp-migrations' ) );
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
			return $this->error(
				sprintf(
					/* translators: %s is the migration ID */
					__( 'Failed to insert migration execution for migration "%s"', 'stellarwp-migrations' ),
					$migration_id
				)
			);
		}

		$execution_id = DB::last_insert_id();

		for ( $i = $from_batch; $i <= $to_batch; $i++ ) {
			shepherd()->dispatch( new Execute( $operation->getValue(), $migration_id, $i, $batch_size, $execution_id, ...$migration->{'get_' . $operation->getValue() . '_extra_args_for_batch'}( $i, $batch_size ) ) );
		}

		return new WP_REST_Response(
			[
				'success'      => true,
				'message'      => __( 'Migration scheduled for execution.', 'stellarwp-migrations' ),
				'execution_id' => $execution_id,
				'operation'    => $operation->getValue(),
				'from_batch'   => $from_batch,
				'to_batch'     => $to_batch,
				'batch_size'   => $batch_size,
			],
			200
		);
	}

	/**
	 * Log a message.
	 *
	 * @since 0.0.1
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	protected function log( string $message ): void {
		// We don't need to log messages for REST endpoints.
	}

	/**
	 * Return an error response.
	 *
	 * @since 0.0.1
	 *
	 * @param string $message The error message to return.
	 *
	 * @return WP_REST_Response
	 */
	protected function error( string $message ): WP_REST_Response {
		return new WP_REST_Response(
			[
				'code'    => 'migrations_error',
				'success' => false,
				'message' => $message,
			],
			400
		);
	}
}

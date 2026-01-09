<?php
/**
 * WP-CLI Commands for Migrations.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\CLI
 */

declare(strict_types=1);

namespace StellarWP\Migrations\Abstracts;

use StellarWP\Migrations\Config;
use StellarWP\Migrations\Registry;
use StellarWP\Migrations\Contracts\Migration;
use StellarWP\Migrations\Tables\Migration_Logs;
use StellarWP\Migrations\Tables\Migration_Executions;
use DateTimeInterface;
use MyCLabs\Enum\Enum;
use WP_REST_Response;

/**
 * Manage database migrations.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Abstracts
 */
abstract class API_Abstract {
	/**
	 * List registered migrations.
	 *
	 * @since 0.0.1
	 *
	 * @param string $tags_string The tags to list migrations for.
	 * @param string $format The format to list migrations in.
	 *
	 * @return void|WP_REST_Response
	 */
	protected function real_list( string $tags_string = '', string $format = 'table' ) {
		$tags = array_filter( explode( ',', $tags_string ) );

		$container = Config::get_container();
		$registry  = $container->get( Registry::class );

		if ( ! empty( $tags ) ) {
			$registry = $registry->filter(
				static fn( ?Migration $migration ): bool => $migration && ! empty( array_intersect( $tags, $migration->get_tags() ) )
			);
		}

		$items = $registry->all();

		if ( empty( $items ) ) {
			$this->log( 'No migrations found.' );
			return;
		}

		$migrations_as_arrays = [];

		foreach ( $items as $migration_id => $migration ) {
			$migrations_as_arrays[] = array_merge( [ 'id' => $migration_id ], $migration->to_array() );
		}

		return $this->display_items_in_format( $migrations_as_arrays, [ 'id', 'label', 'description', 'tags', 'total_batches', 'can_run', 'is_applicable', 'status' ], $format );
	}

	/**
	 * List logs for a specific migration.
	 *
	 * @since 0.0.1
	 *
	 * @param int    $execution_id The execution ID to list logs for.
	 * @param string $format       The format to list logs in.
	 * @param string $types        The types to list logs for.
	 * @param string $not_types    The types to not list logs for.
	 * @param int    $limit        The limit of logs to list.
	 * @param int    $offset       The offset of logs to list.
	 * @param string $order        The order of logs to list.
	 * @param string $order_by     The column to order logs by.
	 * @param string $search       The search term to list logs for.
	 *
	 * @return void|WP_REST_Response
	 */
	public function real_logs( int $execution_id, string $format = 'table', string $types = '', string $not_types = '', int $limit = 100, int $offset = 0, string $order = 'DESC', string $order_by = 'created_at', string $search = '' ) {
		if ( ! $execution_id ) {
			return $this->error( 'Execution ID is required.' );
		}

		$execution = Migration_Executions::get_by_id( $execution_id );

		if ( ! $execution || ! is_array( $execution ) ) {
			return $this->error( "Execution with ID '{$execution_id}' not found." );
		}

		$migration_id = ! empty( $execution['migration_id'] ) ? $execution['migration_id'] : false;

		if ( ! $migration_id ) {
			return $this->error( "Execution with ID '{$execution_id}' not found." );
		}

		$container = Config::get_container();
		$registry  = $container->get( Registry::class );
		/** @var string $migration_id */
		$migration = $registry->get( $migration_id );

		if ( ! $migration ) {
			return $this->error( "The migration associated with execution '{$execution_id}' is no longer available." );
		}

		// Validate order direction.
		if ( ! in_array( $order, [ 'ASC', 'DESC' ], true ) ) {
			return $this->error( 'Invalid order direction. Use ASC or DESC.' );
		}

		// Validate order-by column.
		$allowed_order_by = [ 'id', 'type', 'created_at' ];
		if ( ! in_array( $order_by, $allowed_order_by, true ) ) {
			return $this->error( sprintf( 'Invalid order-by column. Allowed: %s', implode( ', ', $allowed_order_by ) ) );
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
			return $this->error( 'Cannot filter by type and not-type at the same time. Use one or the other.' );
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
			$this->log( "No logs found for execution '{$execution_id}' of migration '{$migration_id}'." );
			return;
		}

		return $this->display_items_in_format(
			$logs,
			[ 'id', 'type', 'message', 'data', 'created_at' ],
			$format
		);
	}

	/**
	 * List executions.
	 *
	 * @since 0.0.1
	 *
	 * @param string $migration_id The migration ID to list executions for.
	 * @param string $format The format to list executions in.
	 *
	 * @return void|WP_REST_Response
	 */
	public function real_executions( string $migration_id, string $format = 'table' ) {
		if ( ! $migration_id ) {
			return $this->error( 'Migration ID is required.' );
		}

		$executions = Migration_Executions::get_all_by( 'migration_id', $migration_id );

		return $this->display_items_in_format( $executions, [ 'id', 'migration_id', 'start_date_gmt', 'end_date_gmt', 'status', 'items_total', 'items_processed', 'created_at' ], $format );
	}

	/**
	 * Normalize items to be displayed in the CLI.
	 *
	 * @since 0.0.1
	 *
	 * @param array<int|string, array<string, mixed>> $items  The items to normalize.
	 * @param string                                  $format The format to normalize the items to.
	 *
	 * @return array<int|string, array<string, mixed>> The normalized items.
	 */
	protected function normalize_items( array $items, string $format ): array {
		foreach ( $items as $offset => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			foreach ( $item as $column => $value ) {
				if ( is_array( $value ) ) {
					$normalized                  = $this->normalize_array_value( $value, $format );
					$items[ $offset ][ $column ] = 'table' === $format ? implode( ', ', $normalized ) : $normalized;
					continue;
				}

				if ( $value instanceof Enum ) {
					$items[ $offset ][ $column ] = $value->getValue();
					continue;
				}

				if ( is_bool( $value ) ) {
					if ( 'table' === $format ) {
						$items[ $offset ][ $column ] = $value ? 'true' : 'false';
					} elseif ( 'csv' === $format ) {
						$items[ $offset ][ $column ] = (int) $value;
					} else {
						$items[ $offset ][ $column ] = $value;
					}
					continue;
				}

				if ( $value instanceof DateTimeInterface ) {
					$items[ $offset ][ $column ] = $value->format( DateTimeInterface::ATOM );
					continue;
				}
			}
		}

		return $items;
	}

	/**
	 * Normalize an array value for CLI display.
	 *
	 * Handles nested arrays by converting objects (Enums, DateTimes) to their string representations.
	 *
	 * @since 0.0.1
	 *
	 * @param array<mixed> $value  The array value to normalize.
	 * @param string       $format The format to normalize to.
	 *
	 * @return array<mixed> The normalized array.
	 */
	private function normalize_array_value( array $value, string $format ): array {
		$normalized = [];

		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) ) {
				$normalized[ $key ] = $this->normalize_array_value( $item, $format );
			} elseif ( $item instanceof Enum ) {
				$normalized[ $key ] = $item->getValue();
			} elseif ( $item instanceof DateTimeInterface ) {
				$normalized[ $key ] = $item->format( DateTimeInterface::ATOM );
			} elseif ( is_bool( $item ) ) {
				if ( 'table' === $format ) {
					$normalized[ $key ] = $item ? 'true' : 'false';
				} elseif ( 'csv' === $format ) {
					$normalized[ $key ] = (int) $item;
				} else {
					$normalized[ $key ] = $item;
				}
			} else {
				$normalized[ $key ] = $item;
			}
		}

		return $normalized;
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
	abstract protected function log( string $message ): void;

	/**
	 * Log an error message.
	 *
	 * @since 0.0.1
	 *
	 * @param string $message The error message to log.
	 *
	 * @return void|WP_REST_Response
	 */
	abstract protected function error( string $message );

	/**
	 * Display items in a format.
	 *
	 * @since 0.0.1
	 *
	 * @param array<mixed>  $items The items to display.
	 * @param array<string> $columns The columns to display.
	 * @param string        $format The format to display the items in.
	 *
	 * @return void|WP_REST_Response
	 */
	abstract protected function display_items_in_format( array $items, array $columns, string $format = 'table' );
}

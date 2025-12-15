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
	 * [--plugin=<plugin>]
	 * : The plugin slug to list migrations for. If not specified, lists for all registered plugins.
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, csv, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     # List all registered migrations
	 *     $ wp migrations list
	 *
	 *     # List migrations for a specific plugin
	 *     $ wp migrations list --plugin=tec
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
}

<?php
/**
 * The Migration Events table schema.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tables
 */

namespace StellarWP\Migrations\Tables;

use StellarWP\Shepherd\Abstracts\Table_Abstract;
use StellarWP\Schema\Collections\Column_Collection;
use StellarWP\Schema\Columns\ID;
use StellarWP\Schema\Columns\Created_At;
use StellarWP\Schema\Columns\String_Column;
use StellarWP\Schema\Columns\Text_Column;
use StellarWP\Schema\Tables\Table_Schema;
use StellarWP\Schema\Columns\PHP_Types;

/**
 * Tasks table schema.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tables
 */
class Migration_Events extends Table_Abstract {
	/**
	 * The schema version.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	const SCHEMA_VERSION = '0.0.1';

	/**
	 * The type of migration event.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	const TYPE_SCHEDULED = 'scheduled';

	/**
	 * The type of migration event.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	const TYPE_BATCH_STARTED = 'batch-started';

	/**
	 * The type of migration event.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	const TYPE_BATCH_COMPLETED = 'batch-completed';

	/**
	 * The type of migration event.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	const TYPE_COMPLETED = 'completed';

	/**
	 * The type of migration event.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	const TYPE_FAILED = 'failed';

	/**
	 * The base table name, without the table prefix.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	protected static $base_table_name = 'stellarwp_%s_migration_events';

	/**
	 * The table group.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	protected static $group = 'stellarwp_migrations';

	/**
	 * The slug used to identify the custom table.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	protected static $schema_slug = 'stellarwp-migrations-%s-migration-events';

	/**
	 * Gets the schema history for the table.
	 *
	 * @since 0.0.1
	 *
	 * @return array<string, callable> The schema history for the table.
	 */
	public static function get_schema_history(): array {
		return [
			self::SCHEMA_VERSION => [ self::class, 'get_schema_version_0_0_1' ],
		];
	}

	/**
	 * Gets the schema for version 0.0.1.
	 *
	 * @since 0.0.1
	 *
	 * @return Table_Schema The schema for version 0.0.1.
	 */
	public static function get_schema_version_0_0_1(): Table_Schema {
		$columns = new Column_Collection(
			[
				new ID( 'id' ),
				( new String_Column( 'migration_id' ) )->set_length( 191 )->set_is_index( true ),
				( new String_Column( 'type' ) )->set_length( 191 )->set_is_index( true ),
				( new Text_Column( 'data' ) )->set_nullable( true )->set_php_type( PHP_Types::JSON ),
				new Created_At( 'created_at' ),
			]
		);

		return new Table_Schema( self::table_name( true ), $columns );
	}
}

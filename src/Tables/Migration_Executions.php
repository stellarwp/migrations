<?php
/**
 * The Migration Executions table schema.
 *
 * @since TBD
 *
 * @package StellarWP\Migrations\Tables
 */

namespace StellarWP\Migrations\Tables;

use StellarWP\Schema\Columns\Created_At;
use StellarWP\Shepherd\Abstracts\Table_Abstract;
use StellarWP\Schema\Collections\Column_Collection;
use StellarWP\Schema\Columns\Datetime_Column;
use StellarWP\Schema\Columns\ID;
use StellarWP\Schema\Columns\Integer_Column;
use StellarWP\Schema\Columns\String_Column;
use StellarWP\Schema\Tables\Table_Schema;

/**
 * Migration Executions table schema.
 *
 * @since TBD
 *
 * @package StellarWP\Migrations\Tables
 */
class Migration_Executions extends Table_Abstract {
	/**
	 * The schema version.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	const SCHEMA_VERSION = '0.0.1';

	/**
	 * The base table name, without the table prefix.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	protected static $base_table_name = 'stellarwp_%s_migration_executions';

	/**
	 * The table group.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	protected static $group = 'stellarwp_migrations';

	/**
	 * The slug used to identify the custom table.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	protected static $schema_slug = 'stellarwp-migrations-%s-migration-executions';

	/**
	 * Gets the schema history for the table.
	 *
	 * @since TBD
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
	 * @since TBD
	 *
	 * @return Table_Schema The schema for version 0.0.1.
	 */
	public static function get_schema_version_0_0_1(): Table_Schema {
		$columns = new Column_Collection(
			[
				new ID( 'id' ),
				( new String_Column( 'migration_id' ) )->set_length( 191 )->set_is_index( true ),
				( new Datetime_Column( 'start_date_gmt' ) )->set_nullable( true ),
				( new Datetime_Column( 'end_date_gmt' ) )->set_nullable( true ),
				( new String_Column( 'status' ) )->set_length( 191 )->set_is_index( true ),
				( new Integer_Column( 'items_number_total' ) )->set_nullable( true ),
				( new Integer_Column( 'items_number_processed' ) )->set_nullable( true ),
				new Created_At( 'created_at' ),
			]
		);

		return new Table_Schema( self::table_name( true ), $columns );
	}
}

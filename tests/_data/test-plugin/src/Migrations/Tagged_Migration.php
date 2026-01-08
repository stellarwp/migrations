<?php
/**
 * Tagged Migration for CLI integration testing.
 *
 * @since 0.0.1
 *
 * @package Test_Plugin\Migrations
 */

declare( strict_types=1 );

namespace Test_Plugin\Migrations;

use StellarWP\Migrations\Abstracts\Migration_Abstract;
use StellarWP\Migrations\Enums\Operation;

/**
 * A migration with specific tags for testing tag filtering in CLI.
 *
 * Used for testing the `--tags` filter option on the `list` command.
 *
 * @since 0.0.1
 */
class Tagged_Migration extends Migration_Abstract {
	/**
	 * Option key for tracking completion.
	 *
	 * @var string
	 */
	protected const OPTION_KEY = 'test_plugin_tagged_migration_done';

	/**
	 * Get the migration label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return 'Tagged Migration';
	}

	/**
	 * Get the migration description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'A migration with tags for testing tag-based filtering.';
	}

	/**
	 * Get migration tags.
	 *
	 * @return array<string>
	 */
	public function get_tags(): array {
		return [ 'schema', 'important', 'v1.0' ];
	}

	/**
	 * Get total items to process.
	 *
	 * @param Operation|null $operation The operation type.
	 *
	 * @return int
	 */
	public function get_total_items( ?Operation $operation = null ): int {
		return 1;
	}

	/**
	 * Get default batch size.
	 *
	 * @return int
	 */
	public function get_default_batch_size(): int {
		return 1;
	}

	/**
	 * Whether this migration is applicable.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		return true;
	}

	/**
	 * Check if the migration has completed running up.
	 *
	 * @return bool
	 */
	public function is_up_done(): bool {
		return (bool) get_option( self::OPTION_KEY, false );
	}

	/**
	 * Check if the migration has completed rolling back.
	 *
	 * @return bool
	 */
	public function is_down_done(): bool {
		return ! get_option( self::OPTION_KEY, false );
	}

	/**
	 * Run the migration up.
	 *
	 * @param int $batch      The current batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 */
	public function up( int $batch, int $batch_size ): void {
		update_option( self::OPTION_KEY, true );
	}

	/**
	 * Roll back the migration.
	 *
	 * @param int $batch      The current batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 */
	public function down( int $batch, int $batch_size ): void {
		delete_option( self::OPTION_KEY );
	}
}

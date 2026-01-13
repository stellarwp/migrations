<?php
/**
 * Tagged Migration for REST integration testing.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Migrations
 */

declare( strict_types=1 );

namespace StellarWP\Migrations\Tests\Migrations;

use StellarWP\Migrations\Abstracts\Migration_Abstract;
use StellarWP\Migrations\Enums\Operation;

/**
 * A migration with specific tags for testing tag filtering in REST API.
 *
 * Used for testing the `tags` filter parameter on the migrations endpoint.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Migrations
 */
class Tagged_Migration extends Migration_Abstract {

	/**
	 * @var bool
	 */
	public static bool $up_called = false;

	/**
	 * @var bool
	 */
	public static bool $down_called = false;

	/**
	 * @var array<int>
	 */
	public static array $up_batches = [];

	/**
	 * @var array<int>
	 */
	public static array $down_batches = [];

	/**
	 * Reset the migration state.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$up_called    = false;
		self::$down_called  = false;
		self::$up_batches   = [];
		self::$down_batches = [];
	}

	/**
	 * Get the migration label.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function get_label(): string {
		return 'Tagged Migration';
	}

	/**
	 * Get the migration description.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'A migration with tags for testing tag-based filtering.';
	}

	/**
	 * Get migration tags.
	 *
	 * @since 0.0.1
	 *
	 * @return array<string>
	 */
	public function get_tags(): array {
		return [ 'data', 'test' ];
	}

	/**
	 * Get total items to process.
	 *
	 * @since 0.0.1
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
	 * @since 0.0.1
	 *
	 * @return int
	 */
	public function get_default_batch_size(): int {
		return 1;
	}

	/**
	 * Whether this migration is applicable.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		return true;
	}

	/**
	 * Check if the migration has completed running up.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public function is_up_done(): bool {
		return self::$up_called;
	}

	/**
	 * Check if the migration has completed rolling back.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public function is_down_done(): bool {
		return ! self::$up_called || self::$down_called;
	}

	/**
	 * Run the migration up.
	 *
	 * @since 0.0.1
	 *
	 * @param int $batch      The current batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 */
	public function up( int $batch, int $batch_size ): void {
		self::$up_called    = true;
		self::$up_batches[] = $batch;
	}

	/**
	 * Roll back the migration.
	 *
	 * @since 0.0.1
	 *
	 * @param int $batch      The current batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 */
	public function down( int $batch, int $batch_size ): void {
		self::$down_called    = true;
		self::$down_batches[] = $batch;
		self::$up_called      = false;
	}
}

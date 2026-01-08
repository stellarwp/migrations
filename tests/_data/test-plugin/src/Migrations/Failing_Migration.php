<?php
/**
 * Failing Migration for CLI integration testing.
 *
 * @since 0.0.1
 *
 * @package Test_Plugin\Migrations
 */

declare( strict_types=1 );

namespace Test_Plugin\Migrations;

use RuntimeException;
use StellarWP\Migrations\Abstracts\Migration_Abstract;
use StellarWP\Migrations\Enums\Operation;

/**
 * A migration that throws an exception during execution.
 *
 * Used for testing CLI error handling, failed status tracking,
 * and execution logs for failed migrations.
 *
 * The migration will fail based on an option flag, allowing tests
 * to control when it should fail.
 *
 * @since 0.0.1
 */
class Failing_Migration extends Migration_Abstract {
	/**
	 * Get the migration label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return 'Failing Migration';
	}

	/**
	 * Get the migration description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'A migration that intentionally fails for testing error handling.';
	}

	/**
	 * Get migration tags.
	 *
	 * @return array<string>
	 */
	public function get_tags(): array {
		return [ 'test', 'error' ];
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
		return false;
	}

	/**
	 * Check if the migration has completed rolling back.
	 *
	 * @return bool
	 */
	public function is_down_done(): bool {
		return false;
	}

	/**
	 * Run the migration up.
	 *
	 * @param int $batch      The current batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When the migration is set to fail.
	 */
	public function up( int $batch, int $batch_size ): void {
		throw new RuntimeException( 'Migration failed intentionally for testing.' );
	}

	/**
	 * Roll back the migration.
	 *
	 * @param int $batch      The current batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 *
	 * @throws RuntimeException When the migration is set to fail.
	 */
	public function down( int $batch, int $batch_size ): void {
		throw new RuntimeException( 'Migration rollback failed intentionally for testing.' );
	}
}

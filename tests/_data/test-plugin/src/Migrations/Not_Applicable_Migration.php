<?php
/**
 * Not Applicable Migration for CLI integration testing.
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
 * A migration that is never applicable.
 *
 * Used for testing that non-applicable migrations are displayed correctly
 * in the CLI list command and cannot be run.
 *
 * @since 0.0.1
 */
class Not_Applicable_Migration extends Migration_Abstract {
	/**
	 * Get the migration label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return 'Not Applicable Migration';
	}

	/**
	 * Get the migration description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'A migration that should never run because it is not applicable.';
	}

	/**
	 * Get migration tags.
	 *
	 * @return array<string>
	 */
	public function get_tags(): array {
		return [ 'legacy', 'deprecated' ];
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
	 * Always returns false - this migration should never apply.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		return false;
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
		return true;
	}

	/**
	 * Run the migration up.
	 *
	 * This should never be called because is_applicable() returns false.
	 *
	 * @param int $batch      The current batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 */
	public function up( int $batch, int $batch_size ): void {
		// This migration should never run.
	}

	/**
	 * Roll back the migration.
	 *
	 * This should never be called because is_applicable() returns false.
	 *
	 * @param int $batch      The current batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 */
	public function down( int $batch, int $batch_size ): void {
		// This migration should never roll back.
	}
}

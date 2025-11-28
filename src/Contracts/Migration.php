<?php
/**
 * Migration contract.
 *
 * @package StellarWP\Migrations
 */

declare(strict_types=1);

namespace StellarWP\Migrations\Contracts;

/**
 * Interface for migrations.
 */
interface Migration {
	/**
	 * Get the migration ID.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Whether the migration requires user input before it can be run.
	 *
	 * If it doesn't, the migration will run automatically.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public function requires_user_input(): bool;

	/**
	 * Whether the migration is applicable to the current site.
	 *
	 * This is something that should not change by whether the migration has been run or not.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public function is_applicable(): bool;

	/**
	 * Whether the migration has been completed.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public function is_done(): bool;

	/**
	 * Whether the migration has been rolled back.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public function is_rolled_back(): bool;

	/**
	 * Runs the migration.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function up(): void;

	/**
	 * Reverts the migration.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function down(): void;
}

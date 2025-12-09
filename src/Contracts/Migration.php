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
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Contracts
 */
interface Migration {
	/**
	 * Get the migration ID.
	 *
	 * Maximum length is 191 characters!
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function get_id(): string;

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
	public function is_up_done(): bool;

	/**
	 * Whether the migration has been rolled back.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public function is_down_done(): bool;

	/**
	 * Runs the migration.
	 *
	 * @since 0.0.1
	 *
	 * @param int $batch The batch number.
	 *
	 * @return void
	 */
	public function up( int $batch ): void;

	/**
	 * Reverts the migration.
	 *
	 * @since 0.0.1
	 *
	 * @param int $batch The batch number.
	 *
	 * @return void
	 */
	public function down( int $batch ): void;

	/**
	 * Runs before each batch of the migration.
	 *
	 * @since 0.0.1
	 *
	 * @param int    $batch   The batch number.
	 * @param string $context The context of the migration.
	 *
	 * @return void
	 */
	public function before( int $batch, string $context ): void;

	/**
	 * Runs after each batch of the migration.
	 *
	 * @since 0.0.1
	 *
	 * @param int    $batch       The batch number.
	 * @param string $context     The context of the migration.
	 * @param bool   $is_complete Whether the migration has finished.
	 *
	 * @return void
	 */
	public function after( int $batch, string $context, bool $is_complete ): void;

	/**
	 * Get extra arguments to be passed to the `up()` or `down()` method.
	 *
	 * @since 0.0.1
	 *
	 * @param string $method The method to get extra arguments for.
	 * @param int    $batch  The batch number.
	 *
	 * @return array
	 */
	public function get_extra_args( string $method, int $batch ): array;
}

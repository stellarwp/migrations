<?php
/**
 * Migration contract.
 *
 * @package StellarWP\Migrations\Contracts
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
	 * Get the migration label.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Get the migration description.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public function get_description(): string;

	/**
	 * Get the total number of items to process.
	 *
	 * @since 0.0.1
	 *
	 * @return int
	 */
	public function get_total_items(): int;

	/**
	 * Get the number of retries per batch.
	 *
	 * @since 0.0.1
	 *
	 * @return int
	 */
	public function get_number_of_retries_per_batch(): int;

	/**
	 * Get the default number of items to process per batch by default.
	 * It can be overridden in the runtime.
	 *
	 * @since 0.0.1
	 *
	 * @return int
	 */
	public function get_default_batch_size(): int;

	/**
	 * Get the migration tags.
	 *
	 * @since 0.0.1
	 *
	 * @return array<string>
	 */
	public function get_tags(): array;

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
	 * Whether the migration can run.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public function can_run(): bool;

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
	 * @param int $batch      The batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 */
	public function up( int $batch, int $batch_size ): void;

	/**
	 * Reverts the migration.
	 *
	 * @since 0.0.1
	 *
	 * @param int $batch      The batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 */
	public function down( int $batch, int $batch_size ): void;

	/**
	 * Runs before each batch of the rollback.
	 *
	 * @since 0.0.1
	 *
	 * @param int $batch      The batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 */
	public function before_down( int $batch, int $batch_size ): void;

	/**
	 * Runs before each batch of the migration.
	 *
	 * @since 0.0.1
	 *
	 * @param int $batch The batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 */
	public function before_up( int $batch, int $batch_size ): void;

	/**
	 * Runs after each batch of the migration.
	 *
	 * @since 0.0.1
	 *
	 * @param int  $batch        The batch number.
	 * @param int  $batch_size   The batch size.
	 * @param bool $is_completed Whether the migration has been completed.
	 *
	 * @return void
	 */
	public function after_up( int $batch, int $batch_size, bool $is_completed ): void;

	/**
	 * Runs after each batch of the rollback.
	 *
	 * @since 0.0.1
	 *
	 * @param int  $batch        The batch number.
	 * @param int  $batch_size   The batch size.
	 * @param bool $is_completed Whether there are more batches to run.
	 *
	 * @return void
	 */
	public function after_down( int $batch, int $batch_size, bool $is_completed ): void;

	/**
	 * Get extra arguments to be passed to the `up()` method for a specific batch.
	 *
	 * @since 0.0.1
	 *
	 * @param int $batch The batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return array<mixed>
	 */
	public function get_up_extra_args_for_batch( int $batch, int $batch_size ): array;

	/**
	 * Get extra arguments to be passed to the `down()` method for a specific batch.
	 *
	 * @since 0.0.1
	 *
	 * @param int $batch The batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return array<mixed>
	 */
	public function get_down_extra_args_for_batch( int $batch, int $batch_size ): array;

	/**
	 * Sets the current execution ID.
	 *
	 * @since TBD
	 *
	 * @param int $execution_id The execution ID to set.
	 *
	 * @return void
	 */
	public function set_execution_id( int $execution_id ): void;
}

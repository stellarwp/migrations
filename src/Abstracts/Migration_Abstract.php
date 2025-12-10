<?php
/**
 * Abstract Migration class.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Abstracts
 */

declare(strict_types=1);

namespace StellarWP\Migrations\Abstracts;

use StellarWP\Migrations\Contracts\Migration;

/**
 * Base class for migrations.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Abstracts
 */
abstract class Migration_Abstract implements Migration {
	/**
	 * Runs before each batch of the migration.
	 *
	 * @param int $batch The batch number.
	 *
	 * @return void
	 */
	public function before_up( int $batch ): void {}

	/**
	 * Runs after each batch of the migration.
	 *
	 * @param int  $batch        The batch number.
	 * @param bool $is_completed Whether the migration has been completed.
	 *
	 * @return void
	 */
	public function after_up( int $batch, bool $is_completed ): void {}

	/**
	 * Runs before each batch of the rollback.
	 *
	 * @param int $batch The batch number.
	 *
	 * @return void
	 */
	public function before_down( int $batch ): void {}

	/**
	 * Runs after each batch of the rollback.
	 *
	 * @param int  $batch        The batch number.
	 * @param bool $is_completed Whether there are more batches to run.
	 *
	 * @return void
	 */
	public function after_down( int $batch, bool $is_completed ): void {}

	/**
	 * Whether the migration can run.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public function can_run(): bool {
		return true;
	}

	/**
	 * Whether the migration can be repeated.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public function is_repeatable(): bool {
		return false;
	}

	/**
	 * Get the number of retries per batch.
	 *
	 * @since 0.0.1
	 *
	 * @return int
	 */
	public function get_number_of_retries_per_batch(): int {
		return 0;
	}

	/**
	 * Get the migration tags.
	 *
	 * @since 0.0.1
	 *
	 * @return array<string>
	 */
	public function get_tags(): array {
		return [];
	}

	/**
	 * Get extra arguments to be passed to the `up()` method for a specific batch.
	 *
	 * @since 0.0.1
	 *
	 * @param int $batch The batch number.
	 *
	 * @return array<mixed>
	 */
	public function get_up_extra_args_for_batch( int $batch ): array {
		return [];
	}

	/**
	 * Get extra arguments to be passed to the `down()` method for a specific batch.
	 *
	 * @since 0.0.1
	 *
	 * @param int $batch The batch number.
	 *
	 * @return array<mixed>
	 */
	public function get_down_extra_args_for_batch( int $batch ): array {
		return [];
	}
}

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
	 * @param int    $batch   The batch number.
	 * @param string $context The context of the migration.
	 *
	 * @return void
	 */
	public function before( int $batch, string $context ): void {}

	/**
	 * Runs after each batch of the migration.
	 *
	 * @param int    $batch       The batch number.
	 * @param string $context     The context of the migration.
	 * @param bool   $is_complete Whether the migration has finished.
	 *
	 * @return void
	 */
	public function after( int $batch, string $context, bool $is_complete ): void {}

	/**
	 * Get extra arguments to be passed to the `up()` method for a specific batch.
	 *
	 * @since 0.0.1
	 *
	 * @param int $batch The batch number.
	 *
	 * @return array
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
	 * @return array
	 */
	public function get_down_extra_args_for_batch( int $batch ): array {
		return [];
	}
}

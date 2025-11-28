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
	 * @param int    $batch                  The batch number.
	 * @param string $context                The context of the migration.
	 * @param bool   $there_are_more_batches Whether there are more batches to run.
	 *
	 * @return void
	 */
	public function after( int $batch, string $context, bool $there_are_more_batches ): void {}
}

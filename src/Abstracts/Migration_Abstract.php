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
	 * @inheritDoc
	 */
	public function before( int $batch, string $context ): void {}

	/**
	 * @inheritDoc
	 */
	public function after( int $batch, string $context ): void {}
}

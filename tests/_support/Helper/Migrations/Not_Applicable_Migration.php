<?php
declare( strict_types=1 );

namespace StellarWP\Migrations\Tests\Migrations;

use StellarWP\Migrations\Abstracts\Migration_Abstract;
use StellarWP\Migrations\Enums\Operation;

/**
 * A migration that is never applicable.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Helper\Migrations
 */
class Not_Applicable_Migration extends Migration_Abstract {
	/**
	 * @var bool
	 */
	public static bool $up_called = false;

	/**
	 * @var bool
	 */
	public static bool $down_called = false;

	/**
	 * @return void
	 */
	public static function reset(): void {
		self::$up_called   = false;
		self::$down_called = false;
	}

	public function get_total_items( ?Operation $operation = null ): int {
		return 1;
	}

	public function get_default_batch_size(): int {
		return 1;
	}

	public function get_label(): string {
		return 'Not Applicable Migration';
	}

	public function get_description(): string {
		return 'This migration is not applicable.';
	}

	/**
	 * Get migration tags.
	 *
	 * @since 0.0.1
	 *
	 * @return array<string>
	 */
	public function get_tags(): array {
		return [ 'legacy' ];
	}

	public function is_applicable(): bool {
		return false;
	}

	public function is_up_done(): bool {
		return self::$up_called;
	}

	public function is_down_done(): bool {
		return ! self::$up_called || self::$down_called;
	}

	public function up( int $batch, int $batch_size ): void {
		self::$up_called = true;
	}

	public function down( int $batch, int $batch_size ): void {
		self::$down_called = true;
		self::$up_called   = false;
	}
}

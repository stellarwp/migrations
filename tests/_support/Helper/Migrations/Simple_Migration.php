<?php
declare( strict_types=1 );

namespace StellarWP\Migrations\Tests\Migrations;

use StellarWP\Migrations\Abstracts\Migration_Abstract;

/**
 * A simple one-batch migration for basic testing.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Helper\Migrations
 */
class Simple_Migration extends Migration_Abstract {
	/**
	 * @var bool
	 */
	public static bool $up_called = false;

	/**
	 * @var bool
	 */
	public static bool $down_called = false;

	/**
	 * @var array<int>
	 */
	public static array $up_batches = [];

	/**
	 * @var array<int>
	 */
	public static array $down_batches = [];

	/**
	 * @return void
	 */
	public static function reset(): void {
		self::$up_called    = false;
		self::$down_called  = false;
		self::$up_batches   = [];
		self::$down_batches = [];
	}

	public function get_total_batches(): int {
		return 1;
	}

	public function get_label(): string {
		return 'Simple Migration';
	}

	public function get_description(): string {
		return 'This is a simple migration that runs a single batch.';
	}

	public function is_applicable(): bool {
		return true;
	}

	public function is_up_done(): bool {
		return self::$up_called;
	}

	public function is_down_done(): bool {
		return ! self::$up_called || self::$down_called;
	}

	public function up( int $batch ): void {
		self::$up_called    = true;
		self::$up_batches[] = $batch;
	}

	public function down( int $batch ): void {
		self::$down_called    = true;
		self::$down_batches[] = $batch;
		self::$up_called      = false;
	}
}

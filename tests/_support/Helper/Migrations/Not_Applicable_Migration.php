<?php
declare( strict_types=1 );

namespace StellarWP\Migrations\Tests\Migrations;

use StellarWP\Migrations\Abstracts\Migration_Abstract;

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

	public function get_id(): string {
		return 'tests_not_applicable_migration';
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

	public function up( int $batch ): void {
		self::$up_called = true;
	}

	public function down( int $batch ): void {
		self::$down_called = true;
		self::$up_called   = false;
	}
}

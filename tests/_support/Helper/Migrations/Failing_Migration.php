<?php
declare( strict_types=1 );

namespace StellarWP\Migrations\Tests\Migrations;

use StellarWP\Migrations\Abstracts\Migration_Abstract;
use RuntimeException;

/**
 * A migration that throws an exception on up().
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Helper\Migrations
 */
class Failing_Migration extends Migration_Abstract {
	/**
	 * @var bool
	 */
	public static bool $up_attempted = false;

	/**
	 * @var bool
	 */
	public static bool $down_called = false;

	/**
	 * @var array<int>
	 */
	public static array $down_batches = [];

	/**
	 * @var string
	 */
	public static string $error_message = 'Migration failed intentionally for testing';

	/**
	 * @var bool
	 */
	public static bool $should_fail = true;

	/**
	 * @var bool
	 */
	public static bool $should_fail_down = false;

	/**
	 * @return void
	 */
	public static function reset(): void {
		self::$up_attempted     = false;
		self::$down_called      = false;
		self::$down_batches     = [];
		self::$error_message    = 'Migration failed intentionally for testing';
		self::$should_fail      = true;
		self::$should_fail_down = false;
	}

	public function get_total_batches(): int {
		return 1;
	}

	public function get_label(): string {
		return 'Failing Migration';
	}

	public function get_description(): string {
		return 'This migration fails intentionally.';
	}

	public function get_id(): string {
		return 'tests_failing_migration';
	}

	public function is_applicable(): bool {
		return true;
	}

	public function is_up_done(): bool {
		return self::$up_attempted && ! self::$should_fail;
	}

	public function is_down_done(): bool {
		return self::$down_called || ! self::$up_attempted;
	}

	public function up( int $batch ): void {
		self::$up_attempted = true;

		if ( self::$should_fail ) {
			throw new RuntimeException( self::$error_message );
		}
	}

	public function down( int $batch ): void {
		self::$down_called    = true;
		self::$down_batches[] = $batch;

		if ( self::$should_fail_down ) {
			throw new RuntimeException( self::$error_message );
		}

		self::$up_attempted = false;
	}
}

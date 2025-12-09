<?php
declare( strict_types=1 );

namespace StellarWP\Migrations\Tests\Migrations;

use StellarWP\Migrations\Abstracts\Migration_Abstract;

/**
 * A migration that uses extra arguments to be passed to the `up()` and `down()` methods.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Helper\Migrations
 */
class Extra_Args_Migration extends Migration_Abstract {
	/**
	 * @var int
	 */
	public static int $up_batch_count = 0;

	/**
	 * @var int
	 */
	public static int $down_batch_count = 0;

	/**
	 * @return void
	 */
	public static function reset(): void {
		self::$up_batch_count      = 0;
		self::$down_batch_count    = 0;
	}

	public function get_id(): string {
		return 'tests_extra_args_migration';
	}

	public function is_applicable(): bool {
		return true;
	}

	public function is_up_done(): bool {
		return self::$up_batch_count >= 4;
	}

	public function is_down_done(): bool {
		return self::$down_batch_count >= 4;
	}

	public function up( int $batch, ...$extra_args ): void {
		self::$up_batch_count++;
		do_action( 'stellarwp_migrations_tests_extra_args_migration_up_batch_processed', $batch, $extra_args );

		if ( 4 === self::$up_batch_count ) {
			throw new \Exception( 'Up batch count is 4' );
		}
	}

	public function down( int $batch, ...$extra_args ): void {
		self::$down_batch_count++;
		do_action( 'stellarwp_migrations_tests_extra_args_migration_down_batch_processed', $batch, $extra_args );
	}

	/**
	 * @inheritDoc
	 */
	public function get_up_extra_args_for_batch( int $batch ): array {
		return [ "arg1_batch_up_{$batch}", "arg2_batch_up_{$batch}" ];
	}

	/**
	 * @inheritDoc
	 */
	public function get_down_extra_args_for_batch( int $batch ): array {
		return [ "arg1_batch_down_{$batch}", "arg2_batch_down_{$batch}" ];
	}
}

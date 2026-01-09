<?php
declare( strict_types=1 );

namespace StellarWP\Migrations\Tests\Migrations;

use StellarWP\Migrations\Abstracts\Migration_Abstract;
use RuntimeException;
use StellarWP\Migrations\Enums\Operation;

/**
 * A migration that fails at a specific batch number.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Helper\Migrations
 */
class Failing_At_Batch_Migration extends Migration_Abstract {
	/**
	 * @var int
	 */
	public static int $fail_at_batch = 2;

	/**
	 * @var int
	 */
	public static int $total_batches = 3;

	/**
	 * @var int
	 */
	public static int $up_batch_count = 0;

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
		self::$fail_at_batch  = 2;
		self::$total_batches  = 3;
		self::$up_batch_count = 0;
		self::$up_batches     = [];
		self::$down_batches   = [];
	}

	public function get_total_items( ?Operation $operation = null ): int {
		return self::$total_batches;
	}

	public function get_default_batch_size(): int {
		return 1;
	}

	public function get_label(): string {
		return 'Failing At Batch Migration';
	}

	public function get_description(): string {
		return 'This migration fails at a specific batch number.';
	}

	public function is_applicable(): bool {
		return true;
	}

	public function is_up_done(): bool {
		return self::$up_batch_count >= self::$total_batches;
	}

	public function is_down_done(): bool {
		return self::$up_batch_count <= 0;
	}

	public function up( int $batch, int $batch_size ): void {
		if ( $batch === self::$fail_at_batch ) {
			throw new RuntimeException( sprintf( 'Migration failed at batch %d', $batch ) );
		}

		++self::$up_batch_count;
		self::$up_batches[] = $batch;
	}

	public function down( int $batch, int $batch_size ): void {
		self::$down_batches[] = $batch;
		--self::$up_batch_count;
	}
}

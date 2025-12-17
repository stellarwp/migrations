<?php
declare( strict_types=1 );

namespace StellarWP\Migrations\Tests\Migrations;

use StellarWP\Migrations\Abstracts\Migration_Abstract;

/**
 * A migration that requires multiple batches to complete.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Helper\Migrations
 */
class Multi_Batch_Migration extends Migration_Abstract {
	/**
	 * @var int
	 */
	public static int $total_batches = 3;

	/**
	 * @var int
	 */
	public static int $up_batch_count = 0;

	/**
	 * @var int
	 */
	public static int $down_batch_count = 0;

	/**
	 * @var array<int>
	 */
	public static array $up_batches = [];

	/**
	 * @var array<int>
	 */
	public static array $down_batches = [];

	/**
	 * @var array<array{batch: int, context: string}>
	 */
	public static array $before_calls = [];

	/**
	 * @var array<array{batch: int, context: string, more: bool}>
	 */
	public static array $after_calls = [];

	/**
	 * @return void
	 */
	public static function reset(): void {
		self::$total_batches    = 3;
		self::$up_batch_count   = 0;
		self::$down_batch_count = 0;
		self::$up_batches       = [];
		self::$down_batches     = [];
		self::$before_calls     = [];
		self::$after_calls      = [];
	}

	public function is_applicable(): bool {
		return true;
	}

	public function get_total_items(): int {
		return self::$total_batches;
	}

	public function get_default_batch_size(): int {
		return 1;
	}

	public function get_label(): string {
		return 'Multi Batch Migration';
	}

	public function get_description(): string {
		return 'This migration runs multiple batches to complete.';
	}

	public function is_up_done(): bool {
		return self::$up_batch_count >= self::$total_batches;
	}

	public function is_down_done(): bool {
		return self::$up_batch_count <= 0;
	}

	public function up( int $batch, int $batch_size ): void {
		++self::$up_batch_count;
		self::$up_batches[] = $batch;
	}

	public function down( int $batch, int $batch_size ): void {
		++self::$down_batch_count;
		self::$down_batches[] = $batch;
		--self::$up_batch_count;
	}

	public function before_up( int $batch, int $batch_size ): void {
		self::$before_calls[] = [
			'batch'   => $batch,
			'context' => 'up',
		];
	}

	public function after_up( int $batch, int $batch_size, bool $is_completed ): void {
		self::$after_calls[] = [
			'batch'   => $batch,
			'context' => 'up',
			'more'    => $is_completed,
		];
	}

	public function before_down( int $batch, int $batch_size ): void {
		self::$before_calls[] = [
			'batch'   => $batch,
			'context' => 'down',
		];
	}

	public function after_down( int $batch, int $batch_size, bool $is_completed ): void {
		self::$after_calls[] = [
			'batch'   => $batch,
			'more'    => $is_completed,
			'context' => 'down',
		];
	}
}

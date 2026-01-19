<?php
/**
 * Counter Migration for testing.
 *
 * Increments an option value by 1 each batch until reaching the target.
 *
 * @since 0.0.1
 *
 * @package Test_Plugin\Migrations
 */

declare( strict_types=1 );

namespace Test_Plugin\Migrations;

use StellarWP\Migrations\Abstracts\Migration_Abstract;
use StellarWP\Migrations\Enums\Operation;

/**
 * A migration that increments a counter option by 1 per batch.
 *
 * - Up: Increments the option value by 1 each batch until it reaches TARGET.
 * - Down: Decrements the option value by 1 each batch until it reaches 0.
 *
 * @since 0.0.1
 */
class Counter_Migration extends Migration_Abstract {
	/**
	 * Target value to reach.
	 *
	 * @var int
	 */
	protected const TARGET = 10;

	/**
	 * Option key for the counter.
	 *
	 * @var string
	 */
	protected const OPTION_KEY = 'test_plugin_counter_value';

	/**
	 * Get the migration label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return 'Counter Migration';
	}

	/**
	 * Get the migration description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Increments a counter option by 1 each batch until reaching ' . self::TARGET . '. Rollback decrements back to 0.';
	}

	/**
	 * Get migration tags.
	 *
	 * @return array<string>
	 */
	public function get_tags(): array {
		return [ 'counter', 'options' ];
	}

	/**
	 * Get total items to process.
	 *
	 * @param Operation|null $operation The operation type.
	 *
	 * @return int
	 */
	public function get_total_items( ?Operation $operation = null ): int {
		if ( $operation && $operation->equals( Operation::DOWN() ) ) {
			return $this->get_counter_value();
		}

		return self::TARGET;
	}

	/**
	 * Get default batch size (1 item per batch).
	 *
	 * @return int
	 */
	public function get_default_batch_size(): int {
		return 1;
	}

	/**
	 * Whether this migration is applicable.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		return true;
	}

	/**
	 * Check if the migration has completed running up.
	 *
	 * @return bool
	 */
	public function is_up_done(): bool {
		return $this->get_counter_value() >= self::TARGET;
	}

	/**
	 * Check if the migration has completed rolling back.
	 *
	 * @return bool
	 */
	public function is_down_done(): bool {
		return $this->get_counter_value() <= 0;
	}

	/**
	 * Run the migration up - increment counter by 1.
	 *
	 * @param int $batch      The current batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 */
	public function up( int $batch, int $batch_size ): void {
		$current = $this->get_counter_value();

		if ( $current < self::TARGET ) {
			$this->set_counter_value( $current + 1 );
		}
	}

	/**
	 * Roll back the migration - decrement counter by 1.
	 *
	 * @param int $batch      The current batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 */
	public function down( int $batch, int $batch_size ): void {
		$current = $this->get_counter_value();

		if ( $current > 0 ) {
			$this->set_counter_value( $current - 1 );
		}
	}

	/**
	 * Get the current counter value.
	 *
	 * @return int
	 */
	protected function get_counter_value(): int {
		return (int) get_option( self::OPTION_KEY, 0 );
	}

	/**
	 * Set the counter value.
	 *
	 * @param int $value The value to set.
	 *
	 * @return void
	 */
	protected function set_counter_value( int $value ): void {
		update_option( self::OPTION_KEY, $value );
	}
}

<?php
/**
 * Large Data Migration for testing pagination and progress.
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
 * A migration with many items to test UI pagination and progress display.
 *
 * @since 0.0.1
 */
class Large_Data_Migration extends Migration_Abstract {
	/**
	 * Total number of items to process.
	 *
	 * @var int
	 */
	protected const TOTAL_ITEMS = 100;

	/**
	 * Default batch size.
	 *
	 * @var int
	 */
	protected const BATCH_SIZE = 10;

	/**
	 * Option key for tracking processed count.
	 *
	 * @var string
	 */
	protected const OPTION_KEY = 'test_plugin_large_data_processed';

	/**
	 * Get the migration label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return 'Large Data Migration';
	}

	/**
	 * Get the migration description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Processes 100 items in batches of 10. Useful for testing progress bars and batch processing UI.';
	}

	/**
	 * Get migration tags.
	 *
	 * @return array<string>
	 */
	public function get_tags(): array {
		return [ 'data', 'large', 'performance' ];
	}

	/**
	 * Get total items to process.
	 *
	 * @param Operation|null $operation The operation type.
	 *
	 * @return int
	 */
	public function get_total_items( ?Operation $operation = null ): int {
		return self::TOTAL_ITEMS;
	}

	/**
	 * Get default batch size.
	 *
	 * @return int
	 */
	public function get_default_batch_size(): int {
		return self::BATCH_SIZE;
	}

	/**
	 * Get number of retries per batch.
	 *
	 * @return int
	 */
	public function get_number_of_retries_per_batch(): int {
		return 2;
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
		return $this->get_processed_count() >= self::TOTAL_ITEMS;
	}

	/**
	 * Check if the migration has completed rolling back.
	 *
	 * @return bool
	 */
	public function is_down_done(): bool {
		return $this->get_processed_count() <= 0;
	}

	/**
	 * Run the migration up.
	 *
	 * @param int $batch      The current batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 */
	public function up( int $batch, int $batch_size ): void {
		$current   = $this->get_processed_count();
		$new_count = min( $current + $batch_size, self::TOTAL_ITEMS );
		$this->set_processed_count( $new_count );
	}

	/**
	 * Roll back the migration.
	 *
	 * @param int $batch      The current batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 */
	public function down( int $batch, int $batch_size ): void {
		$current   = $this->get_processed_count();
		$new_count = max( $current - $batch_size, 0 );
		$this->set_processed_count( $new_count );
	}

	/**
	 * Get the current processed count.
	 *
	 * @return int
	 */
	protected function get_processed_count(): int {
		return (int) get_option( self::OPTION_KEY, 0 );
	}

	/**
	 * Set the processed count.
	 *
	 * @param int $count The count to set.
	 *
	 * @return void
	 */
	protected function set_processed_count( int $count ): void {
		update_option( self::OPTION_KEY, $count );
	}
}

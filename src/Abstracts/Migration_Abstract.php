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
use StellarWP\Migrations\Tables\Migration_Events;
use RuntimeException;

/**
 * Base class for migrations.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Abstracts
 */
abstract class Migration_Abstract implements Migration {
	/**
	 * The status of the migration.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	public const STATUS_PENDING = 'pending';

	/**
	 * The status of the migration.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	public const STATUS_RUNNING = 'running';

	/**
	 * The status of the migration.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	public const STATUS_COMPLETED = 'completed';

	/**
	 * The status of the migration.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	public const STATUS_FAILED = 'failed';

	/**
	 * The status of the migration.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	public const STATUS_DOWN_PENDING = 'rollback-pending';

	/**
	 * The status of the migration.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	public const STATUS_DOWN_RUNNING = 'rollback-running';

	/**
	 * The status of the migration.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	public const STATUS_DOWN_COMPLETED = 'rollback-completed';

	/**
	 * The status of the migration.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	public const STATUS_DOWN_FAILED = 'rollback-failed';

	/**
	 * Runs before each batch of the migration.
	 *
	 * @param int $batch The batch number.
	 *
	 * @return void
	 */
	public function before_up( int $batch ): void {}

	/**
	 * Runs after each batch of the migration.
	 *
	 * @param int  $batch        The batch number.
	 * @param bool $is_completed Whether the migration has been completed.
	 *
	 * @return void
	 */
	public function after_up( int $batch, bool $is_completed ): void {}

	/**
	 * Runs before each batch of the rollback.
	 *
	 * @param int $batch The batch number.
	 *
	 * @return void
	 */
	public function before_down( int $batch ): void {}

	/**
	 * Runs after each batch of the rollback.
	 *
	 * @param int  $batch        The batch number.
	 * @param bool $is_completed Whether there are more batches to run.
	 *
	 * @return void
	 */
	public function after_down( int $batch, bool $is_completed ): void {}

	/**
	 * Whether the migration can run.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public function can_run(): bool {
		return true;
	}

	/**
	 * Whether the migration can be repeated.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public function is_repeatable(): bool {
		return false;
	}

	/**
	 * Get the number of retries per batch.
	 *
	 * @since 0.0.1
	 *
	 * @return int
	 */
	public function get_number_of_retries_per_batch(): int {
		return 0;
	}

	/**
	 * Get the migration tags.
	 *
	 * @since 0.0.1
	 *
	 * @return array
	 */
	public function get_tags(): array {
		return [];
	}

	/**
	 * Get extra arguments to be passed to the `up()` method for a specific batch.
	 *
	 * @since 0.0.1
	 *
	 * @param int $batch The batch number.
	 *
	 * @return array
	 */
	public function get_up_extra_args_for_batch( int $batch ): array {
		return [];
	}

	/**
	 * Get extra arguments to be passed to the `down()` method for a specific batch.
	 *
	 * @since 0.0.1
	 *
	 * @param int $batch The batch number.
	 *
	 * @return array
	 */
	public function get_down_extra_args_for_batch( int $batch ): array {
		return [];
	}

	/**
	 * Convert the migration to an array.
	 *
	 * @since 0.0.1
	 *
	 * @return array
	 */
	public function to_array(): array {
		return [
			'id'            => $this->get_id(),
			'label'         => $this->get_label(),
			'description'   => $this->get_description(),
			'tags'          => $this->get_tags(),
			'total_batches' => $this->get_total_batches(),
			'can_run'       => $this->can_run(),
			'is_applicable' => $this->is_applicable(),
			'is_repeatable' => $this->is_repeatable(),
			'status'        => $this->get_status(),
		];
	}

	/**
	 * Convert the migration to a JSON serializable array.
	 *
	 * @since 0.0.1
	 *
	 * @return array
	 */
	public function jsonSerialize(): array {
		return $this->to_array();
	}

	/**
	 * Get the migration status.
	 *
	 * @since 0.0.1
	 *
	 * @return string
	 *
	 * @throws RuntimeException If the migration event type is invalid.
	 */
	public function get_status(): string {
		// This is equivalent to get_first_by if get_first_by would also support order by...
		$events = Migration_Events::get_all_by( 'migration_id', $this->get_id(), '=', 1, 'created_at DESC' );

		if ( empty( $events ) ) {
			return self::STATUS_PENDING;
		}

		$event = $events[0];

		switch ( $event['type'] ) {
			case Migration_Events::TYPE_SCHEDULED:
				return self::STATUS_PENDING;
			case Migration_Events::TYPE_BATCH_STARTED:
			case Migration_Events::TYPE_BATCH_COMPLETED:
				return self::STATUS_RUNNING;
			case Migration_Events::TYPE_COMPLETED:
				return self::STATUS_COMPLETED;
			case Migration_Events::TYPE_FAILED:
				return self::STATUS_FAILED;
			case Migration_Events::TYPE_DOWN_SCHEDULED:
				return self::STATUS_DOWN_PENDING;
			case Migration_Events::TYPE_DOWN_BATCH_STARTED:
			case Migration_Events::TYPE_DOWN_BATCH_COMPLETED:
				return self::STATUS_DOWN_RUNNING;
			case Migration_Events::TYPE_DOWN_COMPLETED:
				return self::STATUS_DOWN_COMPLETED;
			case Migration_Events::TYPE_DOWN_FAILED:
				return self::STATUS_DOWN_FAILED;
			default:
				throw new RuntimeException( 'Invalid migration event type: ' . $event['type'] );
		}
	}
}

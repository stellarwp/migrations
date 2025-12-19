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
use StellarWP\Migrations\Enums\Operation;
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
	 * @param int $batch      The batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 */
	public function before_up( int $batch, int $batch_size ): void {}

	/**
	 * Runs after each batch of the migration.
	 *
	 * @param int  $batch        The batch number.
	 * @param int  $batch_size   The batch size.
	 * @param bool $is_completed Whether the migration has been completed.
	 *
	 * @return void
	 */
	public function after_up( int $batch, int $batch_size, bool $is_completed ): void {}

	/**
	 * Runs before each batch of the rollback.
	 *
	 * @param int $batch      The batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 */
	public function before_down( int $batch, int $batch_size ): void {}

	/**
	 * Runs after each batch of the rollback.
	 *
	 * @param int  $batch        The batch number.
	 * @param int  $batch_size   The batch size.
	 * @param bool $is_completed Whether the roll-back has been completed.
	 *
	 * @return void
	 */
	public function after_down( int $batch, int $batch_size, bool $is_completed ): void {}

	/**
	 * Whether the migration can run.
	 *
	 * @since 0.0.1
	 *
	 * @return bool Whether the migration can run.
	 */
	public function can_run(): bool {
		return true;
	}

	/**
	 * Get the number of retries per batch.
	 *
	 * @since 0.0.1
	 *
	 * @return int The number of retries per batch.
	 */
	public function get_number_of_retries_per_batch(): int {
		return 0;
	}

	/**
	 * Get the total number of batches.
	 *
	 * @since 0.0.1
	 *
	 * @param int            $batch_size The batch size.
	 * @param Operation|null $operation  The operation to get the total batches for. On null, `Operation::UP()` is assumed.
	 *
	 * @return int The total number of batches.
	 */
	public function get_total_batches( int $batch_size, ?Operation $operation = null ): int {
		return (int) ceil( $this->get_total_items( $operation ) / $batch_size );
	}

	/**
	 * Get the migration tags.
	 *
	 * @since 0.0.1
	 *
	 * @return array<string> The tags associated with the migration.
	 */
	public function get_tags(): array {
		return [];
	}

	/**
	 * Get extra arguments to be passed to the `up()` method for a specific batch.
	 *
	 * @since 0.0.1
	 *
	 * @param int $batch      The batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return array<mixed>
	 */
	public function get_up_extra_args_for_batch( int $batch, int $batch_size ): array {
		return [];
	}

	/**
	 * Get extra arguments to be passed to the `down()` method for a specific batch.
	 *
	 * @since 0.0.1
	 *
	 * @param int $batch      The batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return array<mixed>
	 */
	public function get_down_extra_args_for_batch( int $batch, int $batch_size ): array {
		return [];
	}

	/**
	 * Convert the migration to an array.
	 *
	 * @since 0.0.1
	 *
	 * @phpstan-return array{
	 *     label: string,
	 *     description: string,
	 *     tags: array<string>,
	 *     total_batches: int,
	 *     can_run: bool,
	 *     is_applicable: bool,
	 *     status: string,
	 * }
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'label'         => $this->get_label(),
			'description'   => $this->get_description(),
			'tags'          => $this->get_tags(),
			'total_batches' => $this->get_total_batches( $this->get_default_batch_size() ),
			'can_run'       => $this->can_run(),
			'is_applicable' => $this->is_applicable(),
			'status'        => $this->get_status(),
		];
	}

	/**
	 * Convert the migration to a JSON serializable array.
	 *
	 * @since 0.0.1
	 *
	 * @phpstan-return array{
	 *     label: string,
	 *     description: string,
	 *     tags: array<string>,
	 *     total_batches: int,
	 *     can_run: bool,
	 *     is_applicable: bool,
	 *     status: string,
	 * }
	 *
	 * @return array<string, mixed>
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
		return self::STATUS_PENDING;
	}
}

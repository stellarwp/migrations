<?php
/**
 * Migration UI Utility.
 *
 * Centralizes UI logic for migration views (labels, icons, visibility).
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Utilities
 */

namespace StellarWP\Migrations\Utilities;

use StellarWP\Migrations\Contracts\Migration;
use StellarWP\Migrations\Enums\Operation;
use StellarWP\Migrations\Enums\Status;

/**
 * Utility for migration admin UI (labels, icons, button visibility).
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Utilities
 */
class Migration_UI {

	/**
	 * The migration instance.
	 *
	 * @since 0.0.1
	 *
	 * @var Migration
	 */
	private Migration $migration;

	/**
	 * Constructor.
	 *
	 * @since 0.0.1
	 *
	 * @param Migration $migration Migration instance.
	 */
	public function __construct( Migration $migration ) {
		$this->migration = $migration;
	}

	/**
	 * Returns the run action label for the migration.
	 *
	 * @since 0.0.1
	 *
	 * @return string Translated label.
	 */
	public function get_run_action_label(): string {
		$status = $this->migration->get_status();

		if (
			$status->equals( Status::COMPLETED() )
			|| $status->equals( Status::REVERTED() )
		) {
			return __( 'Run again', 'stellarwp-migrations' );
		}

		if ( $status->equals( Status::FAILED() ) ) {
			return __( 'Retry', 'stellarwp-migrations' );
		}

		return __( 'Start', 'stellarwp-migrations' );
	}

	/**
	 * Returns the run action icon name for the migration.
	 *
	 * @since 0.0.1
	 *
	 * @return string Icon name (e.g. 'start', 'retry').
	 */
	public function get_run_action_icon(): string {
		$status_value = $this->migration->get_status()->getValue();

		if (
			in_array(
				$status_value,
				[
					Status::COMPLETED()->getValue(),
					Status::REVERTED()->getValue(),
					Status::FAILED()->getValue(),
				],
				true
			)
		) {
			return 'retry';
		}

		return 'start';
	}

	/**
	 * Whether the run button should be shown for the migration.
	 *
	 * @since 0.0.1
	 *
	 * @return bool True if run button should be shown.
	 */
	public function show_run(): bool {
		$status_value = $this->migration->get_status()->getValue();

		$runnable_statuses = [
			Status::COMPLETED()->getValue(),
			Status::PENDING()->getValue(),
			Status::CANCELED()->getValue(),
			Status::FAILED()->getValue(),
			Status::REVERTED()->getValue(),
		];

		return $this->migration->is_applicable()
			&& $this->migration->can_run()
			&& in_array( $status_value, $runnable_statuses, true )
			&& $this->migration->get_total_items() > 0;
	}

	/**
	 * Whether the rollback button should be shown for the migration.
	 *
	 * @since 0.0.1
	 *
	 * @return bool True if rollback button should be shown.
	 */
	public function show_rollback(): bool {
		$status_value = $this->migration->get_status()->getValue();

		$rollbackable_statuses = [
			Status::COMPLETED()->getValue(),
			Status::CANCELED()->getValue(),
			Status::FAILED()->getValue(),
		];

		return $this->migration->is_applicable()
			&& in_array( $status_value, $rollbackable_statuses, true )
			&& $this->migration->get_total_items( Operation::DOWN() ) > 0;
	}
}

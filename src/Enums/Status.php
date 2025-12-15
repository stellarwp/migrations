<?php
/**
 * Migrations Statuses Enum.
 *
 * @since TBD
 *
 * @package StellarWP\Migrations\Enums
 */

namespace StellarWP\Migrations\Enums;

use MyCLabs\Enum\Enum;

/**
 * Migrations Statuses enum.
 *
 * @since TBD
 *
 * @extends Enum<Status::*>
 *
 * @method static self CANCELED()
 * @method static self COMPLETED()
 * @method static self FAILED()
 * @method static self PAUSED()
 * @method static self PENDING()
 * @method static self RUNNING()
 */
class Status extends Enum {
	/**
	 * Data migration status 'Canceled'.
	 *
	 * @since TBD
	 */
	private const CANCELED = 'canceled';

	/**
	 * Data migration status 'Completed'.
	 *
	 * @since TBD
	 */
	private const COMPLETED = 'completed';

	/**
	 * Data migration status 'Failed'.
	 *
	 * @since TBD
	 */
	private const FAILED = 'failed';

	/**
	 * Data migration status 'Paused'.
	 *
	 * @since TBD
	 */
	private const PAUSED = 'paused';

	/**
	 * Data migration status 'Scheduled'.
	 *
	 * @since TBD
	 */
	private const SCHEDULED = 'scheduled';

	/**
	 * Data migration status 'Pending'.
	 *
	 * @since TBD
	 */
	private const PENDING = 'pending';

	/**
	 * Data migration status 'Running'.
	 *
	 * @since TBD
	 */
	private const RUNNING = 'running';

	/**
	 * Returns the human-readable label for the status.
	 *
	 * @since TBD
	 *
	 * @return string The label.
	 */
	public function get_label(): string {
		switch ( $this->getValue() ) {
			case self::PENDING:
				return _x( 'Pending', 'Migration status', 'stellarwp-migrations' );
			case self::RUNNING:
				return _x( 'Running', 'Migration status', 'stellarwp-migrations' );
			case self::COMPLETED:
				return _x( 'Completed', 'Migration status', 'stellarwp-migrations' );
			case self::FAILED:
				return _x( 'Failed', 'Migration status', 'stellarwp-migrations' );
			case self::PAUSED:
				return _x( 'Paused', 'Migration status', 'stellarwp-migrations' );
			case self::CANCELED:
				return _x( 'Canceled', 'Migration status', 'stellarwp-migrations' );
			case self::SCHEDULED:
				return _x( 'Scheduled', 'Migration status', 'stellarwp-migrations' );
			default:
				return _x( 'Unknown', 'Migration status', 'stellarwp-migrations' );
		}
	}
}

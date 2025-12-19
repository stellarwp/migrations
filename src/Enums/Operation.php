<?php
/**
 * Migrations Operations Enum.
 *
 * @since TBD
 *
 * @package StellarWP\Migrations\Enums
 */

namespace StellarWP\Migrations\Enums;

use MyCLabs\Enum\Enum;

/**
 * Migrations Operations enum.
 *
 * @since TBD
 *
 * @extends Enum<Operation::*>
 *
 * @method static self UP()
 * @method static self DOWN()
 */
class Operation extends Enum {
	/**
	 * Data migration operation 'Up'.
	 *
	 * @since TBD
	 */
	private const UP = 'up';

	/**
	 * Data migration operation 'Down'.
	 *
	 * @since TBD
	 */
	private const DOWN = 'down';

	/**
	 * Returns the human-readable label for the log type.
	 *
	 * @since TBD
	 *
	 * @return string The label.
	 */
	public function get_label(): string {
		switch ( $this->getValue() ) {
			case self::UP:
				return _x( 'Up', 'Migration operation', 'stellarwp-migrations' );
			case self::DOWN:
				return _x( 'Down', 'Migration operation', 'stellarwp-migrations' );
			default:
				return _x( 'Unknown', 'Migration operation', 'stellarwp-migrations' );
		}
	}
}

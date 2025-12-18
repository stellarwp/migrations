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
}

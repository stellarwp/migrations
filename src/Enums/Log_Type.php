<?php
/**
 * Migration Log Types Enum.
 *
 * @since TBD
 *
 * @package StellarWP\Migrations\Enums
 */

namespace StellarWP\Migrations\Enums;

use MyCLabs\Enum\Enum;

/**
 * Migration Log Types enum.
 *
 * @since TBD
 *
 * @extends Enum<Log_Type::*>
 *
 * @method static self INFO()
 * @method static self WARNING()
 * @method static self ERROR()
 * @method static self DEBUG()
 */
class Log_Type extends Enum {
	/**
	 * Data migration log type 'Info'.
	 *
	 * @since TBD
	 */
	private const INFO = 'info';

	/**
	 * Data migration log type 'Warning'.
	 *
	 * @since TBD
	 */
	private const WARNING = 'warning';

	/**
	 * Data migration log type 'Error'.
	 *
	 * @since TBD
	 */
	private const ERROR = 'error';

	/**
	 * Data migration log type 'Debug'.
	 *
	 * @since TBD
	 */
	private const DEBUG = 'debug';


	/**
	 * Returns the human-readable label for the log type.
	 *
	 * @since TBD
	 *
	 * @return string The label.
	 */
	public function get_label(): string {
		switch ( $this->getValue() ) {
			case self::INFO:
				return _x( 'Info', 'Migration log type', 'stellarwp-migrations' );
			case self::WARNING:
				return _x( 'Warning', 'Migration log type', 'stellarwp-migrations' );
			case self::ERROR:
				return _x( 'Error', 'Migration log type', 'stellarwp-migrations' );
			case self::DEBUG:
				return _x( 'Debug', 'Migration log type', 'stellarwp-migrations' );
			default:
				return _x( 'Unknown', 'Migration log type', 'stellarwp-migrations' );
		}
	}
}

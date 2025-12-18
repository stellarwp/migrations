<?php
/**
 * Migration Logger Utility.
 *
 * @since TBD
 *
 * @package StellarWP\Migrations\Utilities
 */

namespace StellarWP\Migrations\Utilities;

use StellarWP\Migrations\Enums\Log_Type;
use StellarWP\Migrations\Tables\Migration_Logs;
use StellarWP\Migrations\Config;

/**
 * Logger utility for migration executions.
 *
 * @since TBD
 *
 * @package StellarWP\Migrations\Utilities
 */
class Logger {
	/**
	 * Log level priorities (lower = more verbose).
	 *
	 * @since TBD
	 *
	 * @var array<string, int>
	 */
	private const LOG_LEVELS = [
		'debug'   => 0,
		'info'    => 1,
		'warning' => 2,
		'error'   => 3,
	];

	/**
	 * The execution ID.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	private int $execution_id;

	/**
	 * The minimum log level to write to database.
	 *
	 * @since TBD
	 *
	 * @var Log_Type
	 */
	private Log_Type $minimum_log_level;

	/**
	 * Constructor.
	 *
	 * @since TBD
	 *
	 * @param int $execution_id The migration execution ID.
	 */
	public function __construct( int $execution_id ) {
		$this->execution_id      = $execution_id;
		$this->minimum_log_level = $this->determine_minimum_log_level();
	}

	/**
	 * Logs an info message.
	 *
	 * @since TBD
	 *
	 * @param string            $message The log message.
	 * @param array<mixed>|null $data    Optional. Additional data to store.
	 *
	 * @return bool|int The number of rows affected, or `false` if the log level is below the minimum or we fail to insert the log.
	 */
	public function info( string $message, ?array $data = null ) {
		return $this->log( Log_Type::INFO(), $message, $data );
	}

	/**
	 * Logs a warning message.
	 *
	 * @since TBD
	 *
	 * @param string            $message The log message.
	 * @param array<mixed>|null $data    Optional. Additional data to store.
	 *
	 * @return bool|int The number of rows affected, or `false` if the log level is below the minimum or we fail to insert the log.
	 */
	public function warning( string $message, ?array $data = null ) {
		return $this->log( Log_Type::WARNING(), $message, $data );
	}

	/**
	 * Logs an error message.
	 *
	 * @since TBD
	 *
	 * @param string            $message The log message.
	 * @param array<mixed>|null $data    Optional. Additional data to store.
	 *
	 * @return bool|int The number of rows affected, or `false` if the log level is below the minimum or we fail to insert the log.
	 */
	public function error( string $message, ?array $data = null ) {
		return $this->log( Log_Type::ERROR(), $message, $data );
	}

	/**
	 * Logs a debug message.
	 *
	 * @since TBD
	 *
	 * @param string            $message The log message.
	 * @param array<mixed>|null $data    Optional. Additional data to store.
	 *
	 * @return bool|int The number of rows affected, or `false` if the log level is below the minimum or we fail to insert the log.
	 */
	public function debug( string $message, ?array $data = null ) {
		return $this->log( Log_Type::DEBUG(), $message, $data );
	}

	/**
	 * Gets the execution ID.
	 *
	 * @since TBD
	 *
	 * @return int The execution ID.
	 */
	public function get_execution_id(): int {
		return $this->execution_id;
	}

	/**
	 * Logs a message with the specified type.
	 *
	 * @since TBD
	 *
	 * @param Log_Type          $type    The log type.
	 * @param string            $message The log message.
	 * @param array<mixed>|null $data    Optional. Additional data to store.
	 *
	 * @return bool|int The number of rows affected, or `false` if the log level is below the minimum or we fail to insert the log.
	 */
	protected function log( Log_Type $type, string $message, ?array $data = null ) {
		// Skip logging if the log level is below the minimum.
		if ( ! $this->should_log( $type ) ) {
			return false;
		}

		$log_data = [
			'migration_execution_id' => $this->execution_id,
			'type'                   => $type->getValue(),
			'message'                => $message,
		];

		if ( null !== $data ) {
			$log_data['data'] = $data;
		}

		return Migration_Logs::insert( $log_data );
	}

	/**
	 * Determines the minimum log level to write to the database.
	 *
	 * @since TBD
	 *
	 * @return Log_Type The minimum log level.
	 */
	protected function determine_minimum_log_level(): Log_Type {
		$prefix = Config::get_hook_prefix();

		// Determine the default based on WP_DEBUG.
		$default_level = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? Log_Type::DEBUG() : Log_Type::INFO();

		/**
		 * Filters the minimum log level for migrations.
		 *
		 * @since TBD
		 *
		 * @param Log_Type $minimum_log_level The minimum log level.
		 *
		 * @return Log_Type
		 */
		return apply_filters( "stellarwp_migrations_{$prefix}_minimum_log_level", $default_level );
	}

	/**
	 * Determines if a log message should be written based on the minimum log level.
	 *
	 * @since TBD
	 *
	 * @param Log_Type $type The log type to check.
	 *
	 * @return bool Whether the message should be logged.
	 */
	protected function should_log( Log_Type $type ): bool {
		return self::LOG_LEVELS[ $type->getValue() ] >= self::LOG_LEVELS[ $this->minimum_log_level->getValue() ];
	}
}

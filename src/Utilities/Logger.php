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

/**
 * Logger utility for migration executions.
 *
 * @since TBD
 *
 * @package StellarWP\Migrations\Utilities
 */
class Logger {
	/**
	 * The execution ID.
	 *
	 * @since TBD
	 *
	 * @var int
	 */
	private int $execution_id;

	/**
	 * Creates a logger instance for the given execution ID.
	 *
	 * @since TBD
	 *
	 * @param int $execution_id The migration execution ID.
	 *
	 * @return self The logger instance.
	 */
	public static function for_execution( int $execution_id ): self {
		return new self( $execution_id );
	}

	/**
	 * Constructor.
	 *
	 * @since TBD
	 *
	 * @param int $execution_id The migration execution ID.
	 */
	public function __construct( int $execution_id ) {
		$this->execution_id = $execution_id;
	}

	/**
	 * Logs an info message.
	 *
	 * @since TBD
	 *
	 * @param string     $message The log message.
	 * @param array|null $data    Optional. Additional data to store.
	 *
	 * @return int|false The insert ID on success, false on failure.
	 */
	public function info( string $message, ?array $data = null ) {
		return $this->log( Log_Type::INFO(), $message, $data );
	}

	/**
	 * Logs a warning message.
	 *
	 * @since TBD
	 *
	 * @param string     $message The log message.
	 * @param array|null $data    Optional. Additional data to store.
	 *
	 * @return int|false The insert ID on success, false on failure.
	 */
	public function warning( string $message, ?array $data = null ) {
		return $this->log( Log_Type::WARNING(), $message, $data );
	}

	/**
	 * Logs an error message.
	 *
	 * @since TBD
	 *
	 * @param string     $message The log message.
	 * @param array|null $data    Optional. Additional data to store.
	 *
	 * @return int|false The insert ID on success, false on failure.
	 */
	public function error( string $message, ?array $data = null ) {
		return $this->log( Log_Type::ERROR(), $message, $data );
	}

	/**
	 * Logs a debug message.
	 *
	 * @since TBD
	 *
	 * @param string     $message The log message.
	 * @param array|null $data    Optional. Additional data to store.
	 *
	 * @return int|false The insert ID on success, false on failure.
	 */
	public function debug( string $message, ?array $data = null ) {
		return $this->log( Log_Type::DEBUG(), $message, $data );
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
	 * @return int|false The insert ID on success, false on failure.
	 */
	protected function log( Log_Type $type, string $message, ?array $data = null ) {
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
	 * Gets the execution ID.
	 *
	 * @since TBD
	 *
	 * @return int The execution ID.
	 */
	public function get_execution_id(): int {
		return $this->execution_id;
	}
}

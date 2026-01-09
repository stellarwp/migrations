<?php
/**
 * WP_CLI Mock for testing CLI commands in wpunit environment.
 *
 * @since 0.0.1
 * @package StellarWP\Migrations\Tests\Helper
 */

declare( strict_types=1 );

/*
 * WP_CLI utility function mocks.
 */
namespace WP_CLI\Utils {

	function get_flag_value( array $assoc_args, string $flag, $default = null ) {
		return $assoc_args[ $flag ] ?? $default;
	}

	function format_items( string $format, array $items, array $columns ): void {}

	function make_progress_bar( string $message, int $count ): object {
		return new class() {
			public function tick(): void {}
			public function finish(): void {}
		};
	}
}

/*
 * WP_CLI class mock.
 */
namespace {

	// phpcs:ignore Generic.Classes.DuplicateClassName.Found
	class WP_CLI {
		public static array $logs   = [];
		public static array $errors = [];

		public static function reset(): void {
			self::$logs   = [];
			self::$errors = [];
		}

		public static function log( string $message ): void {
			self::$logs[] = $message;
		}

		public static function success( string $message ): void {
			self::$logs[] = $message;
		}

		public static function warning( string $message ): void {
			self::$logs[] = $message;
		}

		public static function error( string $message ): void {
			self::$errors[] = $message;
			throw new \Exception( $message );
		}
	}
}

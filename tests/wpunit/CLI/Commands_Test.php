<?php
declare( strict_types=1 );

// Define WP_CLI\Utils functions if not already defined.
namespace WP_CLI\Utils;

if ( ! function_exists( 'WP_CLI\Utils\get_flag_value' ) ) {
	/**
	 * Mock for WP_CLI\Utils\get_flag_value.
	 *
	 * @param array  $assoc_args The associative arguments.
	 * @param string $flag       The flag to get.
	 * @param mixed  $default    The default value.
	 *
	 * @return mixed The flag value.
	 */
	function get_flag_value( array $assoc_args, string $flag, $default = null ) {
		return $assoc_args[ $flag ] ?? $default;
	}
}

if ( ! function_exists( 'WP_CLI\Utils\format_items' ) ) {
	/**
	 * Mock for WP_CLI\Utils\format_items.
	 *
	 * @param string $format  The format.
	 * @param array  $items   The items.
	 * @param array  $columns The columns.
	 */
	function format_items( string $format, array $items, array $columns ): void {
		// No-op for testing.
	}
}

if ( ! function_exists( 'WP_CLI\Utils\make_progress_bar' ) ) {
	/**
	 * Mock for WP_CLI\Utils\make_progress_bar.
	 *
	 * @param string $message The message.
	 * @param int    $count   The count.
	 *
	 * @return object A mock progress bar.
	 */
	function make_progress_bar( string $message, int $count ): object {
		return new class {
			public function tick(): void {}
			public function finish(): void {}
		};
	}
}

namespace StellarWP\Migrations\CLI;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Registry;
use StellarWP\Migrations\Tests\Migrations\Simple_Migration;
use StellarWP\Migrations\Tables\Migration_Executions;

// Define a minimal WP_CLI mock if not already defined.
if ( ! class_exists( 'WP_CLI' ) ) {
	/**
	 * Minimal WP_CLI mock for testing.
	 */
	class WP_CLI_Mock {
		/**
		 * @var array<string>
		 */
		public static array $logs = [];

		/**
		 * @var array<string>
		 */
		public static array $errors = [];

		/**
		 * Reset the mock state.
		 */
		public static function reset(): void {
			self::$logs   = [];
			self::$errors = [];
		}

		/**
		 * Mock log method.
		 *
		 * @param string $message The message to log.
		 */
		public static function log( string $message ): void {
			self::$logs[] = $message;
		}

		/**
		 * Mock error method.
		 *
		 * @param string $message The error message.
		 *
		 * @throws \Exception Always throws to simulate WP_CLI error behavior.
		 */
		public static function error( string $message ): void {
			self::$errors[] = $message;
			throw new \Exception( $message );
		}
	}

	// Create the WP_CLI class alias in the global namespace.
	class_alias( WP_CLI_Mock::class, 'WP_CLI' );
}

/**
 * Tests for CLI Commands.
 *
 * @since 0.0.1
 */
class Commands_Test extends WPTestCase {
	/**
	 * @before
	 */
	public function reset_state(): void {
		Simple_Migration::reset();

		$container = Config::get_container();
		$container->get( Registry::class )->flush();

		if ( class_exists( WP_CLI_Mock::class ) ) {
			WP_CLI_Mock::reset();
		}
	}

	/**
	 * @test
	 */
	public function it_should_not_create_execution_when_dry_run_is_used(): void {
		$registry = Config::get_container()->get( Registry::class );
		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		// Verify no executions exist before.
		$executions_before = Migration_Executions::get_all_by( 'migration_id', 'tests_simple_migration' );
		$this->assertEmpty( $executions_before );

		// Call the run command with --dry-run.
		$commands = new Commands();
		$commands->run(
			[ 'tests_simple_migration' ],
			[ 'dry-run' => true ]
		);

		// Verify no executions were created.
		$executions_after = Migration_Executions::get_all_by( 'migration_id', 'tests_simple_migration' );
		$this->assertEmpty( $executions_after );

		// Verify migration was not actually run.
		$this->assertFalse( Simple_Migration::$up_called );
	}

	/**
	 * @test
	 */
	public function it_should_output_dry_run_info_without_executing(): void {
		$registry = Config::get_container()->get( Registry::class );
		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$this->assertFalse( Simple_Migration::$up_called );

		// Call with --dry-run.
		$commands = new Commands();
		$commands->run(
			[ 'tests_simple_migration' ],
			[ 'dry-run' => true ]
		);

		// Migration should NOT have been called.
		$this->assertFalse( Simple_Migration::$up_called );
		$this->assertEmpty( Simple_Migration::$up_batches );

		// Check that dry run output was logged.
		if ( class_exists( WP_CLI_Mock::class ) ) {
			$this->assertNotEmpty( WP_CLI_Mock::$logs );
			$this->assertStringContainsString( 'Dry run', WP_CLI_Mock::$logs[0] );
		}
	}

	/**
	 * @test
	 */
	public function it_should_not_rollback_migration_when_dry_run_is_used(): void {
		$registry = Config::get_container()->get( Registry::class );
		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		// First, mark migration as completed so rollback is applicable.
		Simple_Migration::$up_called = true;

		$this->assertFalse( Simple_Migration::$down_called );

		// Call rollback with --dry-run.
		$commands = new Commands();
		$commands->rollback(
			[ 'tests_simple_migration' ],
			[ 'dry-run' => true ]
		);

		// Verify no executions were created.
		$executions = Migration_Executions::get_all_by( 'migration_id', 'tests_simple_migration' );
		$this->assertEmpty( $executions );

		// Migration rollback should NOT have been called.
		$this->assertFalse( Simple_Migration::$down_called );
		$this->assertEmpty( Simple_Migration::$down_batches );
	}

	/**
	 * @after
	 */
	public function cleanup(): void {
		Simple_Migration::reset();

		if ( class_exists( WP_CLI_Mock::class ) ) {
			WP_CLI_Mock::reset();
		}
	}
}

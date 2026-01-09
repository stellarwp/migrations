<?php
/**
 * Tests for CLI Commands.
 *
 * @since 0.0.1
 * @package StellarWP\Migrations\Tests\CLI
 */

declare( strict_types=1 );

namespace StellarWP\Migrations\CLI;

// Load WP_CLI mocks before anything else.
require_once dirname( __DIR__, 2 ) . '/_support/Helper/WP_CLI_Mock.php';

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Registry;
use StellarWP\Migrations\Tests\Migrations\Simple_Migration;
use StellarWP\Migrations\Tables\Migration_Executions;
use WP_CLI;

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
		Config::get_container()->get( Registry::class )->flush();
		WP_CLI::reset();
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
		$commands->run( [ 'tests_simple_migration' ], [ 'dry-run' => true ] );

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
		$commands->run( [ 'tests_simple_migration' ], [ 'dry-run' => true ] );

		// Migration should NOT have been called.
		$this->assertFalse( Simple_Migration::$up_called );
		$this->assertEmpty( Simple_Migration::$up_batches );

		// Check that dry run output was logged.
		$this->assertNotEmpty( WP_CLI::$logs );
		$this->assertStringContainsString( 'Dry run', WP_CLI::$logs[0] );
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
		$commands->rollback( [ 'tests_simple_migration' ], [ 'dry-run' => true ] );

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
		WP_CLI::reset();
	}
}

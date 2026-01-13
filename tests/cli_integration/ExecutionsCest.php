<?php
/**
 * CLI Integration Tests for the `executions` command.
 *
 * @package StellarWP\Migrations
 */

namespace StellarWP\Migrations;

use Cli_integrationTester;
use PHPUnit\Framework\Assert;

class ExecutionsCest {
	/**
	 * Reset migration state before each test.
	 *
	 * @param Cli_integrationTester $I The tester instance.
	 *
	 * @return void
	 */
	public function _before( Cli_integrationTester $I ): void {
		// Reset any migration state.
		$I->cli(
			[
				'option',
				'delete',
				'test_plugin_multi_batch_processed',
			]
		);

		// Clean up executions for test migrations to ensure consistent test state.
		$I->cli(
			[
				'db',
				'query',
				"DELETE FROM wp_stellarwp_bar_migration_executions WHERE migration_id LIKE 'tests_%'",
			]
		);
	}

	/**
	 * @test
	 */
	public function it_should_list_executions_as_json( Cli_integrationTester $I ): void {
		// Run a migration to create an execution.
		$I->cli(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'run',
				'tests_simple_migration',
			]
		);

		$output = $I->cliToString(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'executions',
				'tests_simple_migration',
				'--format=json',
			]
		);

		$executions = json_decode( $output, true );
		Assert::assertIsArray( $executions );
		Assert::assertNotEmpty( $executions );

		// Check structure of first execution.
		$execution = $executions[0];
		Assert::assertArrayHasKey( 'id', $execution );
		Assert::assertArrayHasKey( 'migration_id', $execution );
		Assert::assertArrayHasKey( 'status', $execution );
		Assert::assertArrayHasKey( 'items_total', $execution );
		Assert::assertArrayHasKey( 'items_processed', $execution );
		Assert::assertEquals( 'tests_simple_migration', $execution['migration_id'] );

		// Note: Snapshot assertions are not used here because execution IDs and
		// timestamps are variable and would cause flaky tests.
	}

	/**
	 * @test
	 */
	public function it_should_list_executions_as_table( Cli_integrationTester $I ): void {
		// Run a migration to create an execution.
		$I->cli(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'run',
				'tests_simple_migration',
			]
		);

		$output = $I->cliToString(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'executions',
				'tests_simple_migration',
				'--format=table',
			]
		);

		// Table format should contain headers.
		Assert::assertStringContainsString( 'id', $output );
		Assert::assertStringContainsString( 'migration_id', $output );
		Assert::assertStringContainsString( 'status', $output );
		Assert::assertStringContainsString( 'tests_simple_migration', $output );

		// Note: Snapshot assertions are not used here because execution IDs and
		// timestamps are variable and would cause flaky tests.
	}

	/**
	 * @test
	 */
	public function it_should_show_multiple_executions( Cli_integrationTester $I ): void {
		// Run the migration multiple times.
		$I->cli(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'run',
				'tests_simple_migration',
			]
		);

		$I->cli(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'run',
				'tests_simple_migration',
			]
		);

		$output = $I->cliToString(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'executions',
				'tests_simple_migration',
				'--format=json',
			]
		);

		$executions = json_decode( $output, true );
		Assert::assertGreaterThanOrEqual( 2, count( $executions ) );

		// Note: Snapshot assertions are not used here because execution IDs and
		// timestamps are variable and would cause flaky tests.
	}

	/**
	 * @test
	 */
	public function it_should_track_items_total_and_processed( Cli_integrationTester $I ): void {
		// Run multi-batch migration.
		$I->cli(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'run',
				'tests_multi_batch_migration',
			]
		);

		$output = $I->cliToString(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'executions',
				'tests_multi_batch_migration',
				'--format=json',
			]
		);

		$executions = json_decode( $output, true );
		Assert::assertNotEmpty( $executions );

		$execution = $executions[0];
		// Multi-batch migration has 15 total items.
		Assert::assertEquals( 15, (int) $execution['items_total'] );

		// Note: Snapshot assertions are not used here because execution IDs and
		// timestamps are variable and would cause flaky tests.
	}

	/**
	 * @test
	 */
	public function it_should_show_empty_result_for_migration_without_executions( Cli_integrationTester $I ): void {
		// Don't run any migration, just check executions.
		// Note: This may show executions from other tests, so we use a migration
		// that's less likely to have been run (not_applicable).
		$output = $I->cliToString(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'executions',
				'tests_not_applicable_migration',
				'--format=json',
			]
		);

		Assert::assertSame( 'No executions found.', $output );
	}
}

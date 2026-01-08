<?php
/**
 * CLI Integration Tests for the `rollback` command.
 *
 * @package StellarWP\Migrations
 */

namespace StellarWP\Migrations;

use Cli_integrationTester;
use Codeception\Exception\ModuleException;
use PHPUnit\Framework\Assert;

class RollbackCest {
	/**
	 * Reset migration state before each test.
	 *
	 * @param Cli_integrationTester $I The tester instance.
	 *
	 * @return void
	 */
	public function _before( Cli_integrationTester $I ): void {
		// Reset any migration state by deleting options.
		$I->cli(
			[
				'option',
				'delete',
				'test_plugin_multi_batch_processed',
			] 
		);
	}

	/**
	 * @test
	 */
	public function it_should_rollback_a_simple_migration( Cli_integrationTester $I ): void {
		// First run the migration.
		$I->cli(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'run',
				'tests_simple_migration',
			] 
		);

		// Then rollback.
		$I->cli(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'rollback',
				'tests_simple_migration',
			] 
		);

		// Verify rollback created an execution record.
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
		// Should have at least 2 executions (run + rollback).
		Assert::assertGreaterThanOrEqual( 2, count( $executions ) );
	}

	/**
	 * @test
	 */
	public function it_should_rollback_a_multi_batch_migration( Cli_integrationTester $I ): void {
		// First run the migration.
		$I->cli(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'run',
				'tests_multi_batch_migration',
			] 
		);

		// Then rollback.
		$I->cli(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'rollback',
				'tests_multi_batch_migration',
			] 
		);

		// Verify rollback created an execution record.
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
		Assert::assertIsArray( $executions );
		// Should have at least 2 executions (run + rollback).
		Assert::assertGreaterThanOrEqual( 2, count( $executions ) );
	}

	/**
	 * @test
	 */
	public function it_should_rollback_with_from_and_to_batch( Cli_integrationTester $I ): void {
		// First run the migration.
		$I->cli(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'run',
				'tests_multi_batch_migration',
			] 
		);

		// Rollback only specific batches.
		$I->cli(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'rollback',
				'tests_multi_batch_migration',
				'--from-batch=1',
				'--to-batch=2',
			] 
		);

		// Verify the command executed successfully by checking executions.
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
		Assert::assertIsArray( $executions );
		Assert::assertNotEmpty( $executions );
	}

	/**
	 * @test
	 */
	public function it_should_error_when_migration_not_found_for_rollback( Cli_integrationTester $I ): void {
		try {
			$I->cli(
				[
					tests_migrations_cli_integration_get_prefix(),
					'migrations',
					'rollback',
					'non_existent_migration',
				] 
			);
			Assert::fail( 'Expected command to fail' );
		} catch ( ModuleException $e ) {
			// Command failed as expected.
			Assert::assertStringContainsString( 'failed with exit code', $e->getMessage() );
		}
	}

	/**
	 * @test
	 */
	public function it_should_create_execution_record_when_rolling_back( Cli_integrationTester $I ): void {
		// Run the migration first.
		$I->cli(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'run',
				'tests_simple_migration',
			] 
		);

		// Rollback.
		$I->cli(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'rollback',
				'tests_simple_migration',
			] 
		);

		// Check executions - should have at least 2 (run + rollback).
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
	}
}

<?php
/**
 * CLI Integration Tests for the `run` command.
 *
 * @package StellarWP\Migrations
 */

namespace StellarWP\Migrations;

use Cli_integrationTester;
use Codeception\Exception\ModuleException;
use PHPUnit\Framework\Assert;

class RunCest {
	/**
	 * Reset migration state before each test.
	 *
	 * @param Cli_integrationTester $I The tester instance.
	 *
	 * @return void
	 */
	public function _before( Cli_integrationTester $I ): void {
		// Reset any migration state by deleting options.
		$I->cli( [
			'option',
			'delete',
			'test_plugin_multi_batch_processed',
		] );
		$I->cli( [
			'option',
			'delete',
			'test_plugin_failing_migration_should_fail',
		] );
		$I->cli( [
			'option',
			'delete',
			'test_plugin_failing_migration_completed',
		] );
	}

	/**
	 * @test
	 */
	public function it_should_run_a_simple_migration( Cli_integrationTester $I ): void {
		// Run the migration.
		$I->cli( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'run',
			'tests_simple_migration',
		] );

		// Verify it created an execution record.
		$output = $I->cliToString( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'executions',
			'tests_simple_migration',
			'--format=json',
		] );

		$executions = json_decode( $output, true );
		Assert::assertIsArray( $executions );
		Assert::assertNotEmpty( $executions );
		Assert::assertEquals( 'tests_simple_migration', $executions[0]['migration_id'] );
	}

	/**
	 * @test
	 */
	public function it_should_run_a_multi_batch_migration( Cli_integrationTester $I ): void {
		// Run the migration.
		$I->cli( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'run',
			'tests_multi_batch_migration',
		] );

		// Verify it created an execution record with correct item count.
		$output = $I->cliToString( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'executions',
			'tests_multi_batch_migration',
			'--format=json',
		] );

		$executions = json_decode( $output, true );
		Assert::assertIsArray( $executions );
		Assert::assertNotEmpty( $executions );
		// Multi-batch migration has 15 total items.
		Assert::assertEquals( 15, (int) $executions[0]['items_total'] );
	}

	/**
	 * @test
	 */
	public function it_should_run_migration_with_custom_batch_size( Cli_integrationTester $I ): void {
		// Run the migration with custom batch size.
		$I->cli( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'run',
			'tests_multi_batch_migration',
			'--batch-size=15',
		] );

		// Verify it ran successfully.
		$output = $I->cliToString( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'executions',
			'tests_multi_batch_migration',
			'--format=json',
		] );

		$executions = json_decode( $output, true );
		Assert::assertIsArray( $executions );
		Assert::assertNotEmpty( $executions );
		// Verify command ran (execution was created).
		Assert::assertEquals( 'tests_multi_batch_migration', $executions[0]['migration_id'] );
	}

	/**
	 * @test
	 */
	public function it_should_run_migration_with_from_and_to_batch( Cli_integrationTester $I ): void {
		// Run the migration with from/to batch parameters.
		$I->cli( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'run',
			'tests_multi_batch_migration',
			'--from-batch=2',
			'--to-batch=3',
		] );

		// Verify it ran successfully.
		$output = $I->cliToString( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'executions',
			'tests_multi_batch_migration',
			'--format=json',
		] );

		$executions = json_decode( $output, true );
		Assert::assertIsArray( $executions );
		Assert::assertNotEmpty( $executions );
	}

	/**
	 * @test
	 */
	public function it_should_error_when_migration_not_found( Cli_integrationTester $I ): void {
		try {
			$I->cli( [
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'run',
				'non_existent_migration',
			] );
			Assert::fail( 'Expected command to fail' );
		} catch ( ModuleException $e ) {
			// Command failed as expected.
			Assert::assertStringContainsString( 'failed with exit code', $e->getMessage() );
		}
	}

	/**
	 * @test
	 */
	public function it_should_error_when_from_batch_greater_than_to_batch( Cli_integrationTester $I ): void {
		try {
			$I->cli( [
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'run',
				'tests_multi_batch_migration',
				'--from-batch=3',
				'--to-batch=1',
			] );
			Assert::fail( 'Expected command to fail' );
		} catch ( ModuleException $e ) {
			// Command failed as expected.
			Assert::assertStringContainsString( 'failed with exit code', $e->getMessage() );
		}
	}

	/**
	 * @test
	 */
	public function it_should_create_execution_record_when_running( Cli_integrationTester $I ): void {
		// Run the migration first.
		$I->cli( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'run',
			'tests_simple_migration',
		] );

		// Check executions.
		$output = $I->cliToString( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'executions',
			'tests_simple_migration',
			'--format=json',
		] );

		$executions = json_decode( $output, true );
		Assert::assertNotEmpty( $executions );
		Assert::assertEquals( 'tests_simple_migration', $executions[0]['migration_id'] );
	}
}

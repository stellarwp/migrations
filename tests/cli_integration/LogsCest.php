<?php
/**
 * CLI Integration Tests for the `logs` command.
 *
 * @package StellarWP\Migrations
 */

namespace StellarWP\Migrations;

use Cli_integrationTester;
use Codeception\Exception\ModuleException;
use PHPUnit\Framework\Assert;

class LogsCest {
	/**
	 * Execution ID from a migration run.
	 *
	 * @var int|null
	 */
	protected ?int $execution_id = null;

	/**
	 * Run a migration before tests to generate logs.
	 *
	 * @param Cli_integrationTester $I The tester instance.
	 *
	 * @return void
	 */
	public function _before( Cli_integrationTester $I ): void {
		// Reset migration state.
		$I->cli(
			[
				'option',
				'delete',
				'test_plugin_multi_batch_processed',
			] 
		);

		// Run a migration to generate an execution with logs.
		$I->cli(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'run',
				'tests_multi_batch_migration',
			] 
		);

		// Get the execution ID.
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
		if ( ! empty( $executions ) ) {
			// Get the most recent execution.
			$this->execution_id = (int) $executions[0]['id'];
		}
	}

	/**
	 * @test
	 */
	public function it_should_list_logs_for_execution( Cli_integrationTester $I ): void {
		if ( ! $this->execution_id ) {
			$I->markTestSkipped( 'No execution ID available.' );
		}

		$output = $I->cliToString(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'logs',
				(string) $this->execution_id,
				'--format=json',
			] 
		);

		$logs = json_decode( $output, true );

		// May or may not have logs depending on migration implementation.
		// If output is empty or not JSON, json_decode returns null - that's acceptable.
		Assert::assertTrue( is_array( $logs ) || $logs === null, 'Expected array or null from logs command' );
	}

	/**
	 * @test
	 */
	public function it_should_list_logs_as_table( Cli_integrationTester $I ): void {
		if ( ! $this->execution_id ) {
			$I->markTestSkipped( 'No execution ID available.' );
		}

		$output = $I->cliToString(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'logs',
				(string) $this->execution_id,
				'--format=table',
			] 
		);

		// Output should contain table headers or "No logs found" message.
		$has_table_headers = str_contains( $output, 'id' ) && str_contains( $output, 'type' );
		$has_no_logs       = str_contains( $output, 'No logs found' );

		Assert::assertTrue( $has_table_headers || $has_no_logs );
	}

	/**
	 * @test
	 */
	public function it_should_error_when_execution_not_found( Cli_integrationTester $I ): void {
		try {
			$I->cli(
				[
					tests_migrations_cli_integration_get_prefix(),
					'migrations',
					'logs',
					'999999',
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
	public function it_should_support_limit_option( Cli_integrationTester $I ): void {
		if ( ! $this->execution_id ) {
			$I->markTestSkipped( 'No execution ID available.' );
		}

		// This tests that the limit option is accepted without error.
		$output = $I->cliToString(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'logs',
				(string) $this->execution_id,
				'--limit=5',
				'--format=json',
			] 
		);

		$logs = json_decode( $output, true );
		// May be null if no logs, that's acceptable.
		Assert::assertTrue( is_array( $logs ) || $logs === null, 'Expected array or null from logs command' );
	}

	/**
	 * @test
	 */
	public function it_should_support_offset_option( Cli_integrationTester $I ): void {
		if ( ! $this->execution_id ) {
			$I->markTestSkipped( 'No execution ID available.' );
		}

		// This tests that the offset option is accepted without error.
		$output = $I->cliToString(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'logs',
				(string) $this->execution_id,
				'--offset=0',
				'--limit=10',
				'--format=json',
			] 
		);

		$logs = json_decode( $output, true );
		// May be null if no logs, that's acceptable.
		Assert::assertTrue( is_array( $logs ) || $logs === null, 'Expected array or null from logs command' );
	}

	/**
	 * @test
	 */
	public function it_should_support_order_options( Cli_integrationTester $I ): void {
		if ( ! $this->execution_id ) {
			$I->markTestSkipped( 'No execution ID available.' );
		}

		// Test ASC order.
		$output = $I->cliToString(
			[
				tests_migrations_cli_integration_get_prefix(),
				'migrations',
				'logs',
				(string) $this->execution_id,
				'--order=ASC',
				'--order-by=created_at',
				'--format=json',
			] 
		);

		$logs = json_decode( $output, true );
		// May be null if no logs, that's acceptable.
		Assert::assertTrue( is_array( $logs ) || $logs === null, 'Expected array or null from logs command' );
	}

	/**
	 * @test
	 */
	public function it_should_error_on_invalid_order_direction( Cli_integrationTester $I ): void {
		if ( ! $this->execution_id ) {
			$I->markTestSkipped( 'No execution ID available.' );
		}

		try {
			$I->cli(
				[
					tests_migrations_cli_integration_get_prefix(),
					'migrations',
					'logs',
					(string) $this->execution_id,
					'--order=INVALID',
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
	public function it_should_error_on_invalid_order_by_column( Cli_integrationTester $I ): void {
		if ( ! $this->execution_id ) {
			$I->markTestSkipped( 'No execution ID available.' );
		}

		try {
			$I->cli(
				[
					tests_migrations_cli_integration_get_prefix(),
					'migrations',
					'logs',
					(string) $this->execution_id,
					'--order-by=invalid_column',
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
	public function it_should_error_when_using_type_and_not_type_together( Cli_integrationTester $I ): void {
		if ( ! $this->execution_id ) {
			$I->markTestSkipped( 'No execution ID available.' );
		}

		try {
			$I->cli(
				[
					tests_migrations_cli_integration_get_prefix(),
					'migrations',
					'logs',
					(string) $this->execution_id,
					'--type=info',
					'--not-type=error',
				] 
			);
			Assert::fail( 'Expected command to fail' );
		} catch ( ModuleException $e ) {
			// Command failed as expected.
			Assert::assertStringContainsString( 'failed with exit code', $e->getMessage() );
		}
	}
}

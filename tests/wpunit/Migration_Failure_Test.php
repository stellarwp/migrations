<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Enums\Status;
use StellarWP\Migrations\Tests\Migrations\Failing_Migration;
use StellarWP\Migrations\Tests\Migrations\Failing_At_Batch_Migration;
use StellarWP\Migrations\Models\Execution;
use StellarWP\Migrations\Tables\Migration_Logs;
use StellarWP\Migrations\Tables\Migration_Executions;
use StellarWP\Migrations\Tasks\Execute;
use StellarWP\Shepherd\Exceptions\ShepherdTaskFailWithoutRetryException;

class Migration_Failure_Test extends WPTestCase {
	/**
	 * @before
	 */
	public function reset_state(): void {
		Failing_Migration::reset();
		Failing_At_Batch_Migration::reset();
		tests_migrations_clear_calls_data();

		$container = Config::get_container();
		$container->get( Registry::class )->flush();
	}

	/**
	 * @test
	 */
	public function it_should_record_failed_log_on_failure(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_failing_migration', Failing_Migration::class );

		$prefix = Config::get_hook_prefix();

		// Act.
		try {
			do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );
		} catch ( ShepherdTaskFailWithoutRetryException $e ) {
			// Expected exception.
		}

		// Assert.
		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_failing_migration' );
		$this->assertNotNull( $execution );
		$this->assertInstanceOf( Execution::class, $execution );
		$this->assertEquals( Status::FAILED(), $execution->get_status() );

		// Verify error log was recorded.
		$logs = Migration_Logs::get_all_by( 'migration_execution_id', $execution->get_id() );

		$error_logs = array_filter(
			$logs,
			function ( $log ) {
				return $log['type']->getValue() === 'error';
			}
		);

		$this->assertNotEmpty( $error_logs, 'Should have error log entries' );
	}

	/**
	 * @test
	 */
	public function it_should_store_error_message_in_failed_log(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );

		Failing_Migration::$error_message = 'Custom test error message';

		$registry->register( 'tests_failing_migration', Failing_Migration::class );

		$prefix = Config::get_hook_prefix();

		// Act.
		try {
			do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );
		} catch ( ShepherdTaskFailWithoutRetryException $e ) {
			// Expected exception.
		}

		// Assert.
		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_failing_migration' );
		$this->assertInstanceOf( Execution::class, $execution );
		$logs      = Migration_Logs::get_all_by( 'migration_execution_id', $execution->get_id() );

		$error_logs = array_filter(
			$logs,
			fn( $log ) => $log['type']->getValue() === 'error'
		);

		$this->assertNotEmpty( $error_logs, 'Should have error log entries' );

		// Find the error log with our custom message.
		$custom_error_log = null;
		foreach ( $error_logs as $log ) {
			if ( strpos( $log['message'], 'Custom test error message' ) !== false ) {
				$custom_error_log = $log;
				break;
			}
		}

		$this->assertNotNull( $custom_error_log, 'Should find error log with custom message' );
	}

	/**
	 * @test
	 */
	public function it_should_trigger_rollback_on_up_failure(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_failing_migration', Failing_Migration::class );

		$prefix = Config::get_hook_prefix();

		try {
			do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );
		} catch ( ShepherdTaskFailWithoutRetryException $e ) {
			// Expected exception.
		}

		$this->assertTrue( Failing_Migration::$down_called );
	}

	/**
	 * @test
	 */
	public function it_should_fire_batch_failed_action_on_failure(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_failing_migration', Failing_Migration::class );

		$prefix             = Config::get_hook_prefix();
		$action_fired       = false;
		$received_exception = null;

		add_action(
			"stellarwp_migrations_{$prefix}_up_batch_failed",
			function ( $migration, $method, $batch, $batch_size, $execution_id, $e ) use ( &$action_fired, &$received_exception ) {
				$action_fired       = true;
				$received_exception = $e;
			},
			10,
			6
		);

		try {
			do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );
		} catch ( ShepherdTaskFailWithoutRetryException $e ) {
			// Expected exception.
		}

		$this->assertTrue( $action_fired );
		/** @var Exception $received_exception */
		$this->assertNotNull( $received_exception );
		$this->assertStringContainsString( 'intentionally', $received_exception->getMessage() );
	}

	/**
	 * @test
	 */
	public function it_should_handle_failure_at_specific_batch(): void {
		$registry = Config::get_container()->get( Registry::class );

		Failing_At_Batch_Migration::$fail_at_batch = 2;
		Failing_At_Batch_Migration::$total_batches = 5;

		$registry->register( 'tests_failing_at_batch_migration', Failing_At_Batch_Migration::class );

		$prefix = Config::get_hook_prefix();

		try {
			do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );
		} catch ( ShepherdTaskFailWithoutRetryException $e ) {
			// Expected exception.
		}

		$this->assertContains( 1, Failing_At_Batch_Migration::$up_batches );
		$this->assertNotContains( 2, Failing_At_Batch_Migration::$up_batches );
		$this->assertNotContains( 3, Failing_At_Batch_Migration::$up_batches );
	}

	/**
	 * @test
	 */
	public function it_should_schedule_down_after_up_failure(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );

		Failing_At_Batch_Migration::$fail_at_batch = 2;
		Failing_At_Batch_Migration::$total_batches = 5;

		$registry->register( 'tests_failing_at_batch_migration', Failing_At_Batch_Migration::class );

		$prefix = Config::get_hook_prefix();

		// Act.
		try {
			do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );
		} catch ( ShepherdTaskFailWithoutRetryException $e ) {
			// Expected exception.
		}

		// Assert.
		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_failing_at_batch_migration' );
		$this->assertInstanceOf( Execution::class, $execution );
		$logs      = Migration_Logs::get_all_by( 'migration_execution_id', $execution->get_id() );

		// Check for "Rollback scheduled" warning log.
		$rollback_scheduled = array_filter(
			$logs,
			function ( $log ) {
				return $log['type']->getValue() === 'warning'
					&& strpos( $log['message'], 'Rollback scheduled' ) !== false;
			}
		);

		$this->assertNotEmpty( $rollback_scheduled, 'Should have "Rollback scheduled" warning log' );

		// Verify rollback was actually executed.
		$this->assertNotEmpty( Failing_At_Batch_Migration::$down_batches, 'Rollback should have been executed' );
	}

	/**
	 * @test
	 */
	public function it_should_execute_rollback_after_failure(): void {
		$registry = Config::get_container()->get( Registry::class );

		Failing_At_Batch_Migration::$fail_at_batch = 2;
		Failing_At_Batch_Migration::$total_batches = 5;

		$registry->register( 'tests_failing_at_batch_migration', Failing_At_Batch_Migration::class );

		$prefix = Config::get_hook_prefix();

		try {
			do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );
		} catch ( ShepherdTaskFailWithoutRetryException $e ) {
			// Expected exception.
		}

		$this->assertNotEmpty( Failing_At_Batch_Migration::$down_batches );
	}

	/**
	 * @test
	 */
	public function it_should_throw_shepherd_fail_without_retry_exception(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_failing_migration', Failing_Migration::class );

		$execution_id = Migration_Executions::insert(
			[
				'migration_id'    => 'tests_failing_migration',
				'status'          => 'scheduled',
				'items_total'     => 1,
				'items_processed' => 0,
			]
		);

		$task = new Execute( 'up', 'tests_failing_migration', 1, 1, $execution_id );

		// Assert.
		$this->expectException( ShepherdTaskFailWithoutRetryException::class );

		// Act.
		$task->process();
	}

	/**
	 * @test
	 */
	public function it_should_not_trigger_rollback_on_down_failure(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );

		Failing_Migration::$should_fail = false;

		$registry->register( 'tests_failing_migration', Failing_Migration::class );

		$prefix = Config::get_hook_prefix();

		// Run the up migration first.
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		// Verify up was successful.
		$this->assertTrue( Failing_Migration::$up_attempted );

		// Now make down fail.
		Failing_Migration::$should_fail_down = true;

		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_failing_migration' );
		$this->assertInstanceOf( Execution::class, $execution );

		$task = new Execute( 'down', 'tests_failing_migration', 1, 1, $execution->get_id() );

		// Act.
		try {
			$task->process();
		} catch ( ShepherdTaskFailWithoutRetryException $e ) {
			// Expected exception.
		}

		// Assert.
		// Verify no infinite loop - only one down attempt should occur.
		$this->assertCount( 1, Failing_Migration::$down_batches );
	}

	/**
	 * @after
	 */
	public function cleanup(): void {
		Failing_Migration::reset();
		Failing_At_Batch_Migration::reset();
		tests_migrations_clear_calls_data();
	}
}

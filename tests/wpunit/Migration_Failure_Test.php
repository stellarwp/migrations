<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Migrations\Failing_Migration;
use StellarWP\Migrations\Tests\Migrations\Failing_At_Batch_Migration;
use StellarWP\Migrations\Tables\Migration_Events;
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
	public function it_should_record_failed_event_on_failure(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_failing_migration', Failing_Migration::class );

		$prefix = Config::get_hook_prefix();

		try {
			do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );
		} catch ( ShepherdTaskFailWithoutRetryException $e ) {
			// Expected exception.
		}

		$events = Migration_Events::get_all_by( 'migration_id', 'tests_failing_migration' );

		$failed_events = array_filter(
			$events,
			fn( $event ) => $event['type'] === Migration_Events::TYPE_FAILED
		);

		$this->assertNotEmpty( $failed_events );
	}

	/**
	 * @test
	 */
	public function it_should_store_error_message_in_failed_event(): void {
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
		$events = Migration_Events::get_all_by( 'migration_id', 'tests_failing_migration' );

		$failed_events = array_filter(
			$events,
			fn( $event ) => $event['type'] === Migration_Events::TYPE_FAILED
		);

		$this->assertNotEmpty( $failed_events, 'Failed event should exist' );

		$failed_event = reset( $failed_events );

		$this->assertNotFalse( $failed_event, 'Failed event should not be false' );
		$this->assertIsArray( $failed_event, 'Failed event should be an array' );
		$this->assertArrayHasKey( 'data', $failed_event );
		$this->assertArrayHasKey( 'message', $failed_event['data'] );
		$this->assertStringContainsString( 'Custom test error message', $failed_event['data']['message'] );
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

		$events = Migration_Events::get_all_by( 'migration_id', 'tests_failing_at_batch_migration' );

		$scheduled_events = array_filter(
			$events,
			function ( $event ) {
				if ( $event['type'] !== Migration_Events::TYPE_SCHEDULED ) {
					return false;
				}
				return isset( $event['data']['args'][0] ) && $event['data']['args'][0] === 'down';
			}
		);

		$this->assertNotEmpty( $scheduled_events );
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

		$task = new Execute( 'down', 'tests_failing_migration', 1, 1, $execution['id'] );

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

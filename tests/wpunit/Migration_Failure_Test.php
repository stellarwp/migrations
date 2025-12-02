<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Migrations\Failing_Migration;
use StellarWP\Migrations\Tests\Migrations\Failing_At_Batch_Migration;
use StellarWP\Migrations\Tables\Migration_Events;
use StellarWP\Migrations\Tasks\Execute;
use StellarWP\Shepherd\Exceptions\ShepherdTaskFailWithoutRetryException;
use function StellarWP\Shepherd\shepherd;

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
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Failing_Migration();

		$registry->register( $migration );

		$prefix = Config::get_hook_prefix();

		try {
			do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );
		} catch ( ShepherdTaskFailWithoutRetryException $e ) {
			// Expected exception.
		}

		$events = Migration_Events::get_all_by( 'migration_id', $migration->get_id() );

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
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Failing_Migration();

		Failing_Migration::$error_message = 'Custom test error message';

		$registry->register( $migration );

		$prefix = Config::get_hook_prefix();

		try {
			do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );
		} catch ( ShepherdTaskFailWithoutRetryException $e ) {
			// Expected exception.
		}

		$events = Migration_Events::get_all_by( 'migration_id', $migration->get_id() );

		$failed_event = array_filter(
			$events,
			fn( $event ) => $event['type'] === Migration_Events::TYPE_FAILED
		);

		$failed_event = reset( $failed_event );

		$this->assertNotNull( $failed_event );
		$this->assertStringContainsString( 'Custom test error message', $failed_event['data']['message'] );
	}

	/**
	 * @test
	 */
	public function it_should_trigger_rollback_on_up_failure(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Failing_Migration();

		$registry->register( $migration );

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
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Failing_Migration();

		$registry->register( $migration );

		$prefix       = Config::get_hook_prefix();
		$action_fired = false;
		$received_exception = null;

		add_action(
			"stellarwp_migrations_{$prefix}_up_batch_failed",
			function ( $mig, $batch, $e ) use ( &$action_fired, &$received_exception ) {
				$action_fired       = true;
				$received_exception = $e;
			},
			10,
			3
		);

		try {
			do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );
		} catch ( ShepherdTaskFailWithoutRetryException $e ) {
			// Expected exception.
		}

		$this->assertTrue( $action_fired );
		$this->assertNotNull( $received_exception );
		$this->assertStringContainsString( 'intentionally', $received_exception->getMessage() );
	}

	/**
	 * @test
	 */
	public function it_should_handle_failure_at_specific_batch(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Failing_At_Batch_Migration();

		Failing_At_Batch_Migration::$fail_at_batch = 2;
		Failing_At_Batch_Migration::$total_batches = 5;

		$registry->register( $migration );

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
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Failing_At_Batch_Migration();

		Failing_At_Batch_Migration::$fail_at_batch = 2;
		Failing_At_Batch_Migration::$total_batches = 5;

		$registry->register( $migration );

		$prefix = Config::get_hook_prefix();

		try {
			do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );
		} catch ( ShepherdTaskFailWithoutRetryException $e ) {
			// Expected exception.
		}

		$events = Migration_Events::get_all_by( 'migration_id', $migration->get_id() );

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
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Failing_At_Batch_Migration();

		Failing_At_Batch_Migration::$fail_at_batch = 2;
		Failing_At_Batch_Migration::$total_batches = 5;

		$registry->register( $migration );

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
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Failing_Migration();

		$registry->register( $migration );

		$task = new Execute( 'up', $migration->get_id(), 1 );

		$this->expectException( ShepherdTaskFailWithoutRetryException::class );

		$task->process();
	}

	/**
	 * @test
	 */
	public function it_should_not_trigger_rollback_on_down_failure(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Failing_Migration();

		Failing_Migration::$up_attempted     = true;
		Failing_Migration::$should_fail      = false;
		Failing_Migration::$should_fail_down = true;

		$registry->register( $migration );

		$task = new Execute( 'down', $migration->get_id(), 1 );

		try {
			$task->process();
		} catch ( ShepherdTaskFailWithoutRetryException $e ) {
			// Expected exception.
		}

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

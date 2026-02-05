<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Enums\Status;
use StellarWP\Migrations\Models\Execution;
use StellarWP\Migrations\Tables\Migration_Executions;
use StellarWP\Migrations\Tasks\Execute;
use StellarWP\Migrations\Tests\Migrations\Simple_Migration;
use StellarWP\Migrations\Tests\Migrations\Multi_Batch_Migration;
use StellarWP\Migrations\Tests\Migrations\Failing_Migration;
use StellarWP\Migrations\Tests\Migrations\Not_Applicable_Migration;

/**
 * Tests migration execution tracking throughout the lifecycle.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations
 */
class Migration_Executions_Test extends WPTestCase {
	/**
	 * @before
	 */
	public function reset_state(): void {
		Simple_Migration::reset();
		Multi_Batch_Migration::reset();
		Failing_Migration::reset();
		Not_Applicable_Migration::reset();
		tests_migrations_clear_calls_data();

		$container = Config::get_container();
		$container->get( Registry::class )->flush();
	}

	/**
	 * @test
	 */
	public function it_should_create_scheduled_execution_when_migration_is_scheduled(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );
		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$before = current_time( 'mysql' );

		// Act.
		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$after = current_time( 'mysql' );

		// Assert.
		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_simple_migration' );

		$this->assertNotNull( $execution );
		$this->assertInstanceOf( Execution::class, $execution );
		$this->assertEquals( 'tests_simple_migration', $execution->get_migration_id() );
		$this->assertEquals( Status::COMPLETED()->getValue(), $execution->get_status()->getValue() );
		$this->assertEquals( 1, $execution->get_items_total() );
		$this->assertEquals( 1, $execution->get_items_processed() );

		// Verify created_at timestamp.
		$created_at = $execution->get_created_at();
		$this->assertInstanceOf( \DateTimeInterface::class, $created_at );

		$created_at_string = $created_at->format( 'Y-m-d H:i:s' );

		$this->assertGreaterThanOrEqual( $before, $created_at_string );
		$this->assertLessThanOrEqual( $after, $created_at_string );
	}

	/**
	 * @test
	 */
	public function it_should_record_correct_total_items_for_migration(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );
		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );

		// Act.
		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		// Assert.
		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_multi_batch_migration' );

		$this->assertNotNull( $execution );
		$this->assertInstanceOf( Execution::class, $execution );
		$this->assertEquals( 3, $execution->get_items_total() );
	}

	/**
	 * @test
	 */
	public function it_should_update_status_from_scheduled_to_running_on_first_batch(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );
		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		// Act.
		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		// Assert.
		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_simple_migration' );

		$this->assertNotNull( $execution );
		$this->assertInstanceOf( Execution::class, $execution );
		$this->assertEquals( Status::COMPLETED()->getValue(), $execution->get_status()->getValue() );
	}

	/**
	 * @test
	 */
	public function it_should_record_start_date_when_migration_begins(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );
		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$before = current_time( 'mysql', true );

		// Act.
		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$after = current_time( 'mysql', true );

		// Assert.
		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_simple_migration' );

		$this->assertNotNull( $execution );
		$this->assertInstanceOf( Execution::class, $execution );

		$start_date = $execution->get_start_date();
		$this->assertInstanceOf( \DateTimeInterface::class, $start_date );

		$start_date_string = $start_date->format( 'Y-m-d H:i:s' );

		$this->assertGreaterThanOrEqual( $before, $start_date_string );
		$this->assertLessThanOrEqual( $after, $start_date_string );
	}

	/**
	 * @test
	 */
	public function it_should_update_items_processed_as_migration_progresses(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );
		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );

		// Act.
		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		// Assert.
		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_multi_batch_migration' );

		$this->assertNotNull( $execution );
		$this->assertInstanceOf( Execution::class, $execution );
		$this->assertEquals( 3, $execution->get_items_total() );
		$this->assertEquals( 3, $execution->get_items_processed() );
	}

	/**
	 * @test
	 */
	public function it_should_update_status_to_completed_when_migration_finishes(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );
		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		// Act.
		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		// Assert.
		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_simple_migration' );

		$this->assertNotNull( $execution );
		$this->assertInstanceOf( Execution::class, $execution );
		$this->assertEquals( Status::COMPLETED()->getValue(), $execution->get_status()->getValue() );
	}

	/**
	 * @test
	 */
	public function it_should_record_end_date_when_migration_completes(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );
		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$before = current_time( 'mysql', true );

		// Act.
		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$after = current_time( 'mysql', true );

		// Assert.
		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_simple_migration' );

		$this->assertNotNull( $execution );
		$this->assertInstanceOf( Execution::class, $execution );

		$end_date = $execution->get_end_date();
		$this->assertInstanceOf( \DateTimeInterface::class, $end_date );

		$end_date_string = $end_date->format( 'Y-m-d H:i:s' );

		$this->assertNotNull( $end_date_string );
		$this->assertGreaterThanOrEqual( $before, $end_date_string );
		$this->assertLessThanOrEqual( $after, $end_date_string );
	}

	/**
	 * @test
	 */
	public function it_should_have_start_date_before_end_date(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );
		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		// Act.
		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		// Assert.
		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_simple_migration' );

		$this->assertNotNull( $execution );
		$this->assertInstanceOf( Execution::class, $execution );

		$start_date = $execution->get_start_date();
		$this->assertInstanceOf( \DateTimeInterface::class, $start_date );
		$start_date_string = $start_date->format( 'Y-m-d H:i:s' );

		$end_date = $execution->get_end_date();
		$this->assertInstanceOf( \DateTimeInterface::class, $end_date );
		$end_date_string = $end_date->format( 'Y-m-d H:i:s' );

		$this->assertLessThanOrEqual( $end_date_string, $start_date_string );
	}

	/**
	 * @test
	 */
	public function it_should_track_multiple_batches_in_single_execution(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );
		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );

		// Act.
		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		// Assert.
		$executions = Migration_Executions::get_all_by( 'migration_id', 'tests_multi_batch_migration' );

		// Should have only one execution record for the entire migration.
		$this->assertCount( 1, $executions );
		$this->assertInstanceOf( Execution::class, $executions[0] );
		$this->assertEquals( 3, $executions[0]->get_items_total() );
		$this->assertEquals( 3, $executions[0]->get_items_processed() );
		$this->assertEquals( Status::COMPLETED()->getValue(), $executions[0]->get_status()->getValue() );
	}

	/**
	 * @test
	 */
	public function it_should_update_status_to_failed_when_migration_fails(): void {
		// Arrange.
		Failing_Migration::reset();
		Failing_Migration::$should_fail = true;

		$registry = Config::get_container()->get( Registry::class );
		$registry->register( 'tests_failing_migration', Failing_Migration::class );

		// Act.
		$prefix = Config::get_hook_prefix();

		try {
			do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );
		} catch ( \Exception $e ) {
			// Expected exception from migration failure.
		}

		// Assert.
		$executions = Migration_Executions::get_all_by( 'migration_id', 'tests_failing_migration' );

		$this->assertNotEmpty( $executions );

		// The failed execution is the one without parent_execution_id (created first).
		$failed_execution = null;
		foreach ( $executions as $exec ) {
			if ( $exec instanceof Execution && null === $exec->get_parent_execution_id() ) {
				$failed_execution = $exec;
				break;
			}
		}
		$this->assertInstanceOf( Execution::class, $failed_execution );
		$this->assertEquals( Status::FAILED()->getValue(), $failed_execution->get_status()->getValue() );
	}

	/**
	 * @test
	 */
	public function it_should_record_end_date_after_rollback_when_migration_fails(): void {
		// Arrange.
		Failing_Migration::reset();
		Failing_Migration::$should_fail = true;

		$registry = Config::get_container()->get( Registry::class );
		$registry->register( 'tests_failing_migration', Failing_Migration::class );

		$before = current_time( 'mysql', true );

		// Act.
		$prefix = Config::get_hook_prefix();

		try {
			do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );
		} catch ( \Exception $e ) {
			// Expected exception from migration failure.
		}

		$after = current_time( 'mysql', true );

		// Assert.
		$executions = Migration_Executions::get_all_by( 'migration_id', 'tests_failing_migration' );

		// The failed execution is the one without parent_execution_id.
		$failed_execution = null;
		foreach ( $executions as $exec ) {
			if ( $exec instanceof Execution && null === $exec->get_parent_execution_id() ) {
				$failed_execution = $exec;
				break;
			}
		}
		$this->assertNotNull( $failed_execution );
		$this->assertInstanceOf( Execution::class, $failed_execution );
		$this->assertEquals( Status::FAILED()->getValue(), $failed_execution->get_status()->getValue() );

		// End date should be set when the execution is marked failed.
		$end_date = $failed_execution->get_end_date();
		$this->assertInstanceOf( \DateTimeInterface::class, $end_date );
		$end_date_string = $end_date->format( 'Y-m-d H:i:s' );

		$this->assertNotNull( $end_date_string, 'End date should be set after rollback completes following a failure' );
		$this->assertGreaterThanOrEqual( $before, $end_date_string );
		$this->assertLessThanOrEqual( $after, $end_date_string );
	}

	/**
	 * @test
	 */
	public function it_should_create_rollback_execution_with_parent_execution_id_when_migration_fails(): void {
		// Arrange.
		Failing_Migration::reset();
		Failing_Migration::$should_fail = true;

		$registry = Config::get_container()->get( Registry::class );
		$registry->register( 'tests_failing_migration', Failing_Migration::class );

		$prefix = Config::get_hook_prefix();

		// Act.
		try {
			do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );
		} catch ( \Exception $e ) {
			// Expected exception from migration failure.
		}

		// Assert.
		$executions = Migration_Executions::get_all_by( 'migration_id', 'tests_failing_migration' );

		$this->assertCount( 2, $executions, 'Should have failed execution and rollback execution' );

		$failed_execution   = null;
		$rollback_execution = null;
		foreach ( $executions as $exec ) {
			if ( ! $exec instanceof Execution ) {
				continue;
			}
			if ( null === $exec->get_parent_execution_id() ) {
				$failed_execution = $exec;
			} else {
				$rollback_execution = $exec;
			}
		}

		$this->assertNotNull( $failed_execution );
		$this->assertNotNull( $rollback_execution );
		$this->assertEquals( $failed_execution->get_id(), $rollback_execution->get_parent_execution_id(), 'Rollback execution should link to the failed execution' );
		$this->assertEquals( Status::REVERTED()->getValue(), $rollback_execution->get_status()->getValue(), 'Rollback execution should end with REVERTED status after automatic rollback' );
	}

	/**
	 * @test
	 */
	public function it_should_record_end_date_when_migration_completes_successfully(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );
		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$before = current_time( 'mysql', true );

		// Act.
		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$after = current_time( 'mysql', true );

		// Assert.
		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_simple_migration' );

		$this->assertNotNull( $execution );
		$this->assertInstanceOf( Execution::class, $execution );
		$this->assertEquals( Status::COMPLETED()->getValue(), $execution->get_status()->getValue() );

		// Verify end_date was set after successful migration completion.
		$end_date = $execution->get_end_date();
		$this->assertInstanceOf( \DateTimeInterface::class, $end_date );
		$end_date_string = $end_date->format( 'Y-m-d H:i:s' );

		$this->assertNotNull( $end_date_string, 'End date should be set when migration completes successfully' );
		$this->assertGreaterThanOrEqual( $before, $end_date_string );
		$this->assertLessThanOrEqual( $after, $end_date_string );
	}

	/**
	 * @test
	 */
	public function it_should_record_end_date_when_manual_rollback_completes(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );

		Multi_Batch_Migration::$total_batches = 3;

		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );

		$prefix = Config::get_hook_prefix();

		// Run the up migration first.
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_multi_batch_migration' );
		$this->assertNotNull( $execution );
		$this->assertInstanceOf( Execution::class, $execution );
		$this->assertEquals( Status::COMPLETED()->getValue(), $execution->get_status()->getValue() );

		// Verify end_date was set after successful up migration.
		$original_end_date = $execution->get_end_date();
		$this->assertNotNull( $original_end_date );

		$before = current_time( 'mysql', true );

		// Act.
		// Manually trigger a rollback.
		$task = new Execute( 'down', 'tests_multi_batch_migration', 1, 1, $execution->get_id() );
		$task->process();

		$after = current_time( 'mysql', true );

		// Assert.
		// The execution status should be REVERTED after manual rollback completes.
		$execution_after = Migration_Executions::get_first_by( 'id', $execution->get_id() );
		$this->assertNotNull( $execution_after );
		$this->assertInstanceOf( Execution::class, $execution_after );
		$this->assertEquals( Status::REVERTED()->getValue(), $execution_after->get_status()->getValue(), 'Status should be REVERTED after manual rollback' );

		// The end_date should be updated after the rollback completes.
		$new_end_date = $execution_after->get_end_date();
		$this->assertInstanceOf( \DateTimeInterface::class, $new_end_date );
		$new_end_date_string = $new_end_date->format( 'Y-m-d H:i:s' );

		$this->assertNotNull( $new_end_date_string );
		$this->assertGreaterThanOrEqual( $before, $new_end_date_string );
		$this->assertLessThanOrEqual( $after, $new_end_date_string );
	}

	/**
	 * @test
	 */
	public function it_should_not_create_execution_for_non_applicable_migration(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );
		$registry->register( 'tests_not_applicable_migration', Not_Applicable_Migration::class );

		// Act.
		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		// Assert.
		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_not_applicable_migration' );

		$this->assertNull( $execution );
	}

	/**
	 * @after
	 */
	public function cleanup(): void {
		Simple_Migration::reset();
		Multi_Batch_Migration::reset();
		Failing_Migration::reset();
		Not_Applicable_Migration::reset();
		tests_migrations_clear_calls_data();
	}
}

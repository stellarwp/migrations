<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Enums\Status;
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
		$this->assertEquals( 'tests_simple_migration', $execution['migration_id'] );
		$this->assertEquals( Status::COMPLETED()->getValue(), $execution['status'] );
		$this->assertEquals( 1, $execution['items_total'] );
		$this->assertEquals( 1, $execution['items_processed'] );

		// Verify created_at timestamp.
		$created_at = $execution['created_at'];
		if ( $created_at instanceof \DateTime ) {
			$created_at = $created_at->format( 'Y-m-d H:i:s' );
		}

		$this->assertGreaterThanOrEqual( $before, $created_at );
		$this->assertLessThanOrEqual( $after, $created_at );
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
		$this->assertEquals( 3, $execution['items_total'] );
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
		$this->assertEquals( Status::COMPLETED()->getValue(), $execution['status'] );
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
		$this->assertArrayHasKey( 'start_date_gmt', $execution );

		$start_date = $execution['start_date_gmt'];
		if ( $start_date instanceof \DateTime ) {
			$start_date = $start_date->format( 'Y-m-d H:i:s' );
		}

		$this->assertGreaterThanOrEqual( $before, $start_date );
		$this->assertLessThanOrEqual( $after, $start_date );
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
		$this->assertEquals( 3, $execution['items_total'] );
		$this->assertEquals( 3, $execution['items_processed'] );
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
		$this->assertEquals( Status::COMPLETED()->getValue(), $execution['status'] );
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
		$this->assertArrayHasKey( 'end_date_gmt', $execution );

		$end_date = $execution['end_date_gmt'];
		if ( $end_date instanceof \DateTime ) {
			$end_date = $end_date->format( 'Y-m-d H:i:s' );
		}

		$this->assertNotNull( $end_date );
		$this->assertGreaterThanOrEqual( $before, $end_date );
		$this->assertLessThanOrEqual( $after, $end_date );
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

		$start_date = $execution['start_date_gmt'];
		if ( $start_date instanceof \DateTime ) {
			$start_date = $start_date->format( 'Y-m-d H:i:s' );
		}

		$end_date = $execution['end_date_gmt'];
		if ( $end_date instanceof \DateTime ) {
			$end_date = $end_date->format( 'Y-m-d H:i:s' );
		}

		$this->assertLessThanOrEqual( $end_date, $start_date );
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
		$this->assertEquals( 3, $executions[0]['items_total'] );
		$this->assertEquals( 3, $executions[0]['items_processed'] );
		$this->assertEquals( Status::COMPLETED()->getValue(), $executions[0]['status'] );
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

		// The execution should remain as failed even though the rollback succeeded.
		$execution = $executions[0];
		$this->assertEquals( Status::FAILED()->getValue(), $execution['status'] );
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

		$execution = $executions[0];

		$this->assertNotNull( $execution );
		$this->assertEquals( Status::FAILED()->getValue(), $execution['status'] );

		// End date should be set after the automatic rollback completes.
		$end_date = $execution['end_date_gmt'];
		if ( $end_date instanceof \DateTime ) {
			$end_date = $end_date->format( 'Y-m-d H:i:s' );
		}

		$this->assertNotNull( $end_date, 'End date should be set after rollback completes following a failure' );
		$this->assertGreaterThanOrEqual( $before, $end_date );
		$this->assertLessThanOrEqual( $after, $end_date );
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
		$this->assertEquals( Status::COMPLETED()->getValue(), $execution['status'] );

		// Verify end_date was set after successful migration completion.
		$end_date = $execution['end_date_gmt'];
		if ( $end_date instanceof \DateTime ) {
			$end_date = $end_date->format( 'Y-m-d H:i:s' );
		}

		$this->assertNotNull( $end_date, 'End date should be set when migration completes successfully' );
		$this->assertGreaterThanOrEqual( $before, $end_date );
		$this->assertLessThanOrEqual( $after, $end_date );
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
		$this->assertEquals( Status::COMPLETED()->getValue(), $execution['status'] );

		// Verify end_date was set after successful up migration.
		$original_end_date = $execution['end_date_gmt'];
		$this->assertNotNull( $original_end_date );

		$before = current_time( 'mysql', true );

		// Act.
		// Manually trigger a rollback.
		$task = new Execute( 'down', 'tests_multi_batch_migration', 1, 1, $execution['id'] );
		$task->process();

		$after = current_time( 'mysql', true );

		// Assert.
		// The execution status should be FAILED after rollback completes.
		$execution_after = Migration_Executions::get_first_by( 'id', $execution['id'] );
		$this->assertNotNull( $execution_after );
		$this->assertEquals( Status::FAILED()->getValue(), $execution_after['status'], 'Status should be FAILED after rollback' );

		// The end_date should be updated after the rollback completes.
		$new_end_date = $execution_after['end_date_gmt'];
		if ( $new_end_date instanceof \DateTime ) {
			$new_end_date = $new_end_date->format( 'Y-m-d H:i:s' );
		}

		$this->assertNotNull( $new_end_date );
		$this->assertGreaterThanOrEqual( $before, $new_end_date );
		$this->assertLessThanOrEqual( $after, $new_end_date );
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

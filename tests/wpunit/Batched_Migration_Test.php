<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Migrations\Multi_Batch_Migration;
use StellarWP\Migrations\Tables\Migration_Events;
use StellarWP\Migrations\Tasks\Execute;
use function StellarWP\Shepherd\shepherd;

class Batched_Migration_Test extends WPTestCase {
	/**
	 * @before
	 */
	public function reset_state(): void {
		Multi_Batch_Migration::reset();
		tests_migrations_clear_calls_data();

		$container = Config::get_container();
		$container->get( Registry::class )->flush();
	}

	/**
	 * @test
	 */
	public function it_should_execute_multiple_batches_until_done(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Multi_Batch_Migration();

		Multi_Batch_Migration::$total_batches = 3;

		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertTrue( $migration->is_up_done() );
		$this->assertEquals( 3, Multi_Batch_Migration::$up_batch_count );
	}

	/**
	 * @test
	 */
	public function it_should_track_batch_numbers_correctly(): void {
		$registry  = Config::get_container()->get( Registry::class );

		Multi_Batch_Migration::$total_batches = 3;

		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertEquals( [ 1, 2, 3 ], Multi_Batch_Migration::$up_batches );
	}

	/**
	 * @test
	 */
	public function it_should_dispatch_next_batch_task_after_each_batch(): void {
		$registry  = Config::get_container()->get( Registry::class );

		Multi_Batch_Migration::$total_batches = 3;

		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$calls = tests_migrations_get_calls_data();

		$this->assertCount( 3, $calls );

		foreach ( $calls as $call ) {
			$this->assertInstanceOf( Execute::class, $call );
		}

		$batches = array_map( fn( $task ) => $task->get_args()[2], $calls );
		$this->assertEquals( [ 1, 2, 3 ], $batches );
	}

	/**
	 * @test
	 */
	public function it_should_call_before_hook_for_each_batch(): void {
		$registry  = Config::get_container()->get( Registry::class );

		Multi_Batch_Migration::$total_batches = 3;

		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertCount( 3, Multi_Batch_Migration::$before_calls );

		foreach ( Multi_Batch_Migration::$before_calls as $call ) {
			$this->assertEquals( 'up', $call['context'] );
		}

		$batches = array_column( Multi_Batch_Migration::$before_calls, 'batch' );
		$this->assertEquals( [ 1, 2, 3 ], $batches );
	}

	/**
	 * @test
	 */
	public function it_should_call_after_hook_for_each_batch(): void {
		$registry  = Config::get_container()->get( Registry::class );

		Multi_Batch_Migration::$total_batches = 3;

		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertCount( 3, Multi_Batch_Migration::$after_calls );

		// The 'more' param reflects is_up_done() after each batch.
		$more_flags = array_column( Multi_Batch_Migration::$after_calls, 'more' );

		$this->assertFalse( $more_flags[0] );
		$this->assertFalse( $more_flags[1] );
		$this->assertTrue( $more_flags[2] );
	}

	/**
	 * @test
	 */
	public function it_should_record_batch_started_events_for_each_batch(): void {
		$registry  = Config::get_container()->get( Registry::class );

		Multi_Batch_Migration::$total_batches = 3;

		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$events = Migration_Events::get_all_by( 'migration_id', 'tests_multi_batch_migration' );

		$batch_started_events = array_values(
			array_filter(
				$events,
				fn( $event ) => $event['type'] === Migration_Events::TYPE_BATCH_STARTED
			)
		);

		$this->assertCount( 3, $batch_started_events );
	}

	/**
	 * @test
	 */
	public function it_should_record_batch_completed_events_for_intermediate_batches(): void {
		$registry  = Config::get_container()->get( Registry::class );

		Multi_Batch_Migration::$total_batches = 3;

		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$events = Migration_Events::get_all_by( 'migration_id', 'tests_multi_batch_migration' );

		$batch_completed_events = array_values(
			array_filter(
				$events,
				fn( $event ) => $event['type'] === Migration_Events::TYPE_BATCH_COMPLETED
			)
		);

		$this->assertCount( 2, $batch_completed_events );
	}

	/**
	 * @test
	 */
	public function it_should_record_single_completed_event_at_end(): void {
		$registry  = Config::get_container()->get( Registry::class );

		Multi_Batch_Migration::$total_batches = 3;

		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$events = Migration_Events::get_all_by( 'migration_id', 'tests_multi_batch_migration' );

		$completed_events = array_values(
			array_filter(
				$events,
				fn( $event ) => $event['type'] === Migration_Events::TYPE_COMPLETED
			)
		);

		$this->assertCount( 1, $completed_events );
	}

	/**
	 * @test
	 */
	public function it_should_execute_multiple_batches_for_down(): void {
		$registry  = Config::get_container()->get( Registry::class );

		Multi_Batch_Migration::$total_batches = 3;

		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );

		$migration = $registry->get( 'tests_multi_batch_migration' );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertTrue( $migration->is_up_done() );

		Multi_Batch_Migration::$before_calls = [];
		Multi_Batch_Migration::$after_calls  = [];

		$task = new Execute( 'down', 'tests_multi_batch_migration', 1 );
		shepherd()->dispatch( $task );

		$this->assertCount( 3, Multi_Batch_Migration::$down_batches );
		$this->assertEquals( [ 1, 2, 3 ], Multi_Batch_Migration::$down_batches );
	}

	/**
	 * @test
	 */
	public function it_should_handle_single_batch_migration(): void {
		$registry  = Config::get_container()->get( Registry::class );

		Multi_Batch_Migration::$total_batches = 1;

		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );

		$migration = $registry->get( 'tests_multi_batch_migration' );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertTrue( $migration->is_up_done() );
		$this->assertCount( 1, Multi_Batch_Migration::$up_batches );

		$calls = tests_migrations_get_calls_data();
		$this->assertCount( 1, $calls );
	}

	/**
	 * @after
	 */
	public function cleanup(): void {
		Multi_Batch_Migration::reset();
		tests_migrations_clear_calls_data();
	}
}

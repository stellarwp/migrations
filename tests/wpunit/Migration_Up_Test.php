<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Migrations\Simple_Migration;
use StellarWP\Migrations\Tests\Migrations\Multi_Batch_Migration;
use StellarWP\Migrations\Tests\Migrations\Not_Applicable_Migration;
use StellarWP\Migrations\Tables\Migration_Events;
use StellarWP\Migrations\Tasks\Execute;

class Migration_Up_Test extends WPTestCase {
	/**
	 * @before
	 */
	public function reset_state(): void {
		Simple_Migration::reset();
		Multi_Batch_Migration::reset();
		Not_Applicable_Migration::reset();
		tests_migrations_clear_calls_data();

		$container = Config::get_container();
		$container->get( Registry::class )->flush();
	}

	/**
	 * @test
	 */
	public function it_should_execute_a_simple_migration_up(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry->register( $migration );

		$this->assertFalse( Simple_Migration::$up_called );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertTrue( Simple_Migration::$up_called );
		$this->assertContains( 1, Simple_Migration::$up_batches );
	}

	/**
	 * @test
	 */
	public function it_should_dispatch_execute_task_for_migration(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry->register( $migration );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$calls = tests_migrations_get_calls_data();

		$this->assertNotEmpty( $calls );
		$this->assertInstanceOf( Execute::class, $calls[0] );

		$args = $calls[0]->get_args();
		$this->assertEquals( 'up', $args[0] );
		$this->assertEquals( $migration->get_id(), $args[1] );
		$this->assertEquals( 1, $args[2] );
	}

	/**
	 * @test
	 */
	public function it_should_record_scheduled_event_in_migration_events(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry->register( $migration );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$event = Migration_Events::get_first_by( 'migration_id', $migration->get_id() );

		$this->assertNotNull( $event );
		$this->assertEquals( $migration->get_id(), $event['migration_id'] );
		$this->assertEquals( Migration_Events::TYPE_SCHEDULED, $event['type'] );
	}

	/**
	 * @test
	 */
	public function it_should_record_completed_event_after_migration_finishes(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry->register( $migration );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		// Get all events for this migration.
		$events = Migration_Events::get_all_by( 'migration_id', $migration->get_id() );

		$types = array_column( $events, 'type' );

		$this->assertContains( Migration_Events::TYPE_SCHEDULED, $types );
		$this->assertContains( Migration_Events::TYPE_BATCH_STARTED, $types );
		$this->assertContains( Migration_Events::TYPE_COMPLETED, $types );
	}

	/**
	 * @test
	 */
	public function it_should_not_run_migration_twice(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry->register( $migration );

		$prefix = Config::get_hook_prefix();

		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );
		$this->assertTrue( Simple_Migration::$up_called );

		global $wp_actions;
		unset( $wp_actions[ "stellarwp_migrations_{$prefix}_schedule_migrations" ] );

		$call_count_before = count( tests_migrations_get_calls_data() );

		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$call_count_after = count( tests_migrations_get_calls_data() );

		$this->assertEquals( $call_count_before, $call_count_after );
	}

	/**
	 * @test
	 */
	public function it_should_execute_multiple_migrations(): void {
		$registry   = Config::get_container()->get( Registry::class );
		$migration1 = new Simple_Migration();
		$migration2 = new Multi_Batch_Migration();

		$registry->register( $migration1 );
		$registry->register( $migration2 );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertTrue( Simple_Migration::$up_called );
		$this->assertTrue( Multi_Batch_Migration::is_up_done() );
	}

	/**
	 * @test
	 */
	public function it_should_skip_migration_if_already_done(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		Simple_Migration::$up_called = true;

		$registry->register( $migration );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$calls = tests_migrations_get_calls_data();

		$this->assertNotEmpty( $calls );
		$this->assertEmpty( Simple_Migration::$up_batches );
	}

	/**
	 * @test
	 */
	public function it_should_fire_before_batch_processed_action(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry->register( $migration );

		$prefix        = Config::get_hook_prefix();
		$action_fired  = false;
		$received_args = [];

		add_action(
			"stellarwp_migrations_{$prefix}_before_up_batch_processed",
			function ( $mig, $batch, $method ) use ( &$action_fired, &$received_args ) {
				$action_fired  = true;
				$received_args = compact( 'mig', 'batch', 'method' );
			},
			10,
			3
		);

		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertTrue( $action_fired );
		$this->assertSame( $migration, $received_args['mig'] );
		$this->assertEquals( 1, $received_args['batch'] );
		$this->assertEquals( 'up', $received_args['method'] );
	}

	/**
	 * @test
	 */
	public function it_should_fire_post_batch_processed_action(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry->register( $migration );

		$prefix       = Config::get_hook_prefix();
		$action_fired = false;

		add_action(
			"stellarwp_migrations_{$prefix}_post_up_batch_processed",
			function () use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertTrue( $action_fired );
	}

	/**
	 * @after
	 */
	public function cleanup(): void {
		Simple_Migration::reset();
		Multi_Batch_Migration::reset();
		Not_Applicable_Migration::reset();
		tests_migrations_clear_calls_data();
	}
}

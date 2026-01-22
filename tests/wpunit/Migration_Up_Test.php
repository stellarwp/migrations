<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Migrations\Simple_Migration;
use StellarWP\Migrations\Tests\Migrations\Multi_Batch_Migration;
use StellarWP\Migrations\Tests\Migrations\Not_Applicable_Migration;
use StellarWP\Migrations\Models\Execution;
use StellarWP\Migrations\Tables\Migration_Executions;
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
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

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
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$calls = tests_migrations_get_calls_data();

		$this->assertNotEmpty( $calls );
		$this->assertInstanceOf( Execute::class, $calls[0] );

		$args = $calls[0]->get_args();
		$this->assertEquals( 'up', $args[0] );
		$this->assertEquals( 'tests_simple_migration', $args[1] );
		$this->assertEquals( 1, $args[2] );
	}

	/**
	 * @test
	 */
	public function it_should_record_scheduled_execution_in_migration_executions(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_simple_migration' );

		$this->assertNotNull( $execution );
		$this->assertInstanceOf( Execution::class, $execution );
		$this->assertEquals( 'tests_simple_migration', $execution->get_migration_id() );
		$this->assertContains( $execution->get_status()->getValue(), [ 'scheduled', 'running', 'completed' ] );
	}

	/**
	 * @test
	 */
	public function it_should_record_completed_status_after_migration_finishes(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		// Check execution status is completed.
		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_simple_migration' );

		$this->assertNotNull( $execution );
		$this->assertInstanceOf( Execution::class, $execution );
		$this->assertEquals( 'completed', $execution->get_status()->getValue() );
	}

	/**
	 * @test
	 */
	public function it_should_not_run_migration_twice(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

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
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );
		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$migration2 = $registry->get( 'tests_multi_batch_migration' );

		$this->assertTrue( Simple_Migration::$up_called );
		$this->assertTrue( $migration2->is_up_done() );
	}

	/**
	 * @test
	 */
	public function it_should_skip_migration_if_already_done(): void {
		$registry = Config::get_container()->get( Registry::class );

		Simple_Migration::$up_called = true;

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

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
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$prefix        = Config::get_hook_prefix();
		$action_fired  = false;
		$received_args = [];

		add_action(
			"stellarwp_migrations_{$prefix}_before_up_batch_processed",
			function ( $mig, $method, $batch, $batch_size, $execution_id ) use ( &$action_fired, &$received_args ) {
				$action_fired  = true;
				$received_args = compact( 'mig', 'method', 'batch', 'batch_size', 'execution_id' );
			},
			10,
			5
		);

		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertTrue( $action_fired );
		$this->assertInstanceOf( Simple_Migration::class, $received_args['mig'] );
		$this->assertEquals( 'up', $received_args['method'] );
		$this->assertEquals( 1, $received_args['batch'] );
	}

	/**
	 * @test
	 */
	public function it_should_fire_post_batch_processed_action(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

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

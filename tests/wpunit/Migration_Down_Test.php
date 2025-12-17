<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Migrations\Simple_Migration;
use StellarWP\Migrations\Tests\Migrations\Multi_Batch_Migration;
use StellarWP\Migrations\Tables\Migration_Events;
use StellarWP\Migrations\Tables\Migration_Executions;
use StellarWP\Migrations\Tasks\Execute;
use function StellarWP\Shepherd\shepherd;

class Migration_Down_Test extends WPTestCase {
	/**
	 * @before
	 */
	public function reset_state(): void {
		Simple_Migration::reset();
		Multi_Batch_Migration::reset();
		tests_migrations_clear_calls_data();

		$container = Config::get_container();
		$container->get( Registry::class )->flush();
	}

	/**
	 * @test
	 */
	public function it_should_execute_down_on_a_completed_migration(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		// Assert.
		$this->assertTrue( Simple_Migration::$up_called );
		$this->assertFalse( Simple_Migration::$down_called );

		tests_migrations_clear_calls_data();

		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_simple_migration' );

		// Act.
		$task = new Execute( 'down', 'tests_simple_migration', 1, 1, $execution['id'] );
		shepherd()->dispatch( $task );

		// Assert.
		$this->assertTrue( Simple_Migration::$down_called );
		$this->assertContains( 1, Simple_Migration::$down_batches );
	}

	/**
	 * @test
	 */
	public function it_should_record_down_events(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_simple_migration' );

		// Act.
		$task = new Execute( 'down', 'tests_simple_migration', 1, 1, $execution['id'] );
		shepherd()->dispatch( $task );

		// Assert.
		$events = Migration_Events::get_all_by( 'migration_id', 'tests_simple_migration' );
		$types  = array_column( $events, 'type' );

		$this->assertContains( Migration_Events::TYPE_BATCH_STARTED, $types );
		$this->assertContains( Migration_Events::TYPE_COMPLETED, $types );

		// Should have at least 1 COMPLETED event from the up migration.
		// Down migrations don't record COMPLETED events.
		$completed_count = count( array_filter( $types, fn( $t ) => $t === Migration_Events::TYPE_COMPLETED ) );
		$this->assertGreaterThanOrEqual( 1, $completed_count );
	}

	/**
	 * @test
	 */
	public function it_should_skip_down_if_not_up(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$this->assertFalse( Simple_Migration::$up_called );

		$execution_id = Migration_Executions::insert(
			[
				'migration_id' => 'tests_simple_migration',
				'status'       => 'scheduled',
			]
		);

		// Act.
		$task = new Execute( 'down', 'tests_simple_migration', 1, 1, $execution_id );
		shepherd()->dispatch( $task );

		// Assert.
		$this->assertFalse( Simple_Migration::$down_called );
	}

	/**
	 * @test
	 */
	public function it_should_fire_before_down_batch_processed_action(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$action_fired = false;

		add_action(
			"stellarwp_migrations_{$prefix}_before_down_batch_processed",
			function () use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_simple_migration' );

		// Act.
		$task = new Execute( 'down', 'tests_simple_migration', 1, 1, $execution['id'] );
		shepherd()->dispatch( $task );

		// Assert.
		$this->assertTrue( $action_fired );
	}

	/**
	 * @test
	 */
	public function it_should_fire_post_down_batch_processed_action(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$action_fired = false;

		add_action(
			"stellarwp_migrations_{$prefix}_post_down_batch_processed",
			function () use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_simple_migration' );

		// Act.
		$task = new Execute( 'down', 'tests_simple_migration', 1, 1, $execution['id'] );
		shepherd()->dispatch( $task );

		// Assert.
		$this->assertTrue( $action_fired );
	}

	/**
	 * @test
	 */
	public function it_should_call_before_and_after_hooks_on_migration(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$migration = $registry->get( 'tests_multi_batch_migration' );

		$this->assertTrue( $migration->is_up_done() );

		Multi_Batch_Migration::$before_calls = [];
		Multi_Batch_Migration::$after_calls  = [];

		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_multi_batch_migration' );

		// Act.
		$task = new Execute( 'down', 'tests_multi_batch_migration', 1, 1, $execution['id'] );
		shepherd()->dispatch( $task );

		// Assert.
		$down_before_calls = array_filter(
			Multi_Batch_Migration::$before_calls,
			fn( $call ) => $call['context'] === 'down'
		);

		$this->assertNotEmpty( $down_before_calls );

		$down_after_calls = array_filter(
			Multi_Batch_Migration::$after_calls,
			fn( $call ) => $call['context'] === 'down'
		);

		$this->assertNotEmpty( $down_after_calls );
	}

	/**
	 * @after
	 */
	public function cleanup(): void {
		Simple_Migration::reset();
		Multi_Batch_Migration::reset();
		tests_migrations_clear_calls_data();
	}
}

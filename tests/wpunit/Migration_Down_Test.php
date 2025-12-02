<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Migrations\Simple_Migration;
use StellarWP\Migrations\Tests\Migrations\Multi_Batch_Migration;
use StellarWP\Migrations\Tables\Migration_Events;
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
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry->register( $migration );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertTrue( Simple_Migration::$up_called );
		$this->assertFalse( Simple_Migration::$down_called );

		tests_migrations_clear_calls_data();

		$task = new Execute( 'down', $migration->get_id(), 1 );
		shepherd()->dispatch( $task );

		$this->assertTrue( Simple_Migration::$down_called );
		$this->assertContains( 1, Simple_Migration::$down_batches );
	}

	/**
	 * @test
	 */
	public function it_should_record_down_events(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry->register( $migration );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$task = new Execute( 'down', $migration->get_id(), 1 );
		shepherd()->dispatch( $task );

		$events = Migration_Events::get_all_by( 'migration_id', $migration->get_id() );
		$types  = array_column( $events, 'type' );

		$this->assertContains( Migration_Events::TYPE_BATCH_STARTED, $types );
		$this->assertContains( Migration_Events::TYPE_COMPLETED, $types );

		$completed_count = count( array_filter( $types, fn( $t ) => $t === Migration_Events::TYPE_COMPLETED ) );
		$this->assertGreaterThanOrEqual( 2, $completed_count );
	}

	/**
	 * @test
	 */
	public function it_should_skip_down_if_not_up(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry->register( $migration );

		$this->assertFalse( Simple_Migration::$up_called );

		$task = new Execute( 'down', $migration->get_id(), 1 );
		shepherd()->dispatch( $task );

		$this->assertFalse( Simple_Migration::$down_called );
	}

	/**
	 * @test
	 */
	public function it_should_fire_before_down_batch_processed_action(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry->register( $migration );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$action_fired = false;

		add_action(
			"stellarwp_migrations_{$prefix}_before_down_batch_processed",
			function () use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		$task = new Execute( 'down', $migration->get_id(), 1 );
		shepherd()->dispatch( $task );

		$this->assertTrue( $action_fired );
	}

	/**
	 * @test
	 */
	public function it_should_fire_post_down_batch_processed_action(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry->register( $migration );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$action_fired = false;

		add_action(
			"stellarwp_migrations_{$prefix}_post_down_batch_processed",
			function () use ( &$action_fired ) {
				$action_fired = true;
			}
		);

		$task = new Execute( 'down', $migration->get_id(), 1 );
		shepherd()->dispatch( $task );

		$this->assertTrue( $action_fired );
	}

	/**
	 * @test
	 */
	public function it_should_call_before_and_after_hooks_on_migration(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Multi_Batch_Migration();

		$registry->register( $migration );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertTrue( Multi_Batch_Migration::is_up_done() );

		Multi_Batch_Migration::$before_calls = [];
		Multi_Batch_Migration::$after_calls  = [];

		$task = new Execute( 'down', $migration->get_id(), 1 );
		shepherd()->dispatch( $task );

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

		global $wp_actions;
		$prefix = Config::get_hook_prefix();
		unset( $wp_actions[ "stellarwp_migrations_{$prefix}_schedule_migrations" ] );
	}
}

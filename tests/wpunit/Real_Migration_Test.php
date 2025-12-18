<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Migrations\Switch_Post_Meta_Key;
use StellarWP\Migrations\Tables\Migration_Logs;
use StellarWP\Migrations\Tables\Migration_Executions;
use StellarWP\Migrations\Tasks\Execute;
use function StellarWP\Shepherd\shepherd;

/**
 * Tests using Switch_Post_Meta_Key migration which operates on real database.
 *
 * These tests create actual dummy data, run the migration, and verify the results.
 */
class Real_Migration_Test extends WPTestCase {
	/**
	 * @before
	 */
	public function reset_state(): void {
		Switch_Post_Meta_Key::reset();
		Config::get_container()->get( Registry::class )->flush();
		tests_migrations_clear_calls_data();
	}

	/**
	 * @test
	 */
	public function it_should_migrate_single_post_meta_key_and_verify_results(): void {
		$data = Switch_Post_Meta_Key::create_dummy_data( 1 );

		$this->assertCount( 1, $data['post_ids'] );
		$this->assertCount( 1, $data['meta_values'] );

		$counts = Switch_Post_Meta_Key::get_counts();
		$this->assertEquals( 1, $counts['old_count'] );
		$this->assertEquals( 0, $counts['new_count'] );

		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_switch_post_meta_key', Switch_Post_Meta_Key::class );

		$migration = $registry->get( 'tests_switch_post_meta_key' );

		$this->assertFalse( $migration->is_up_done() );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$migration = $registry->get( 'tests_switch_post_meta_key' );

		$this->assertTrue( $migration->is_up_done() );

		$results = Switch_Post_Meta_Key::verify_up_results();

		$this->assertTrue( $results['success'], implode( "\n", $results['errors'] ) );
		$this->assertEquals( 0, $results['old_count'] );
		$this->assertEquals( 1, $results['new_count'] );
		$this->assertTrue( $results['values_preserved'] );
	}

	/**
	 * @test
	 */
	public function it_should_migrate_multiple_posts_and_verify_values_preserved(): void {
		$data = Switch_Post_Meta_Key::create_dummy_data( 5 );

		$this->assertCount( 5, $data['post_ids'] );
		$this->assertCount( 5, $data['meta_values'] );

		$counts = Switch_Post_Meta_Key::get_counts();
		$this->assertEquals( 5, $counts['old_count'] );
		$this->assertEquals( 0, $counts['new_count'] );

		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_switch_post_meta_key', Switch_Post_Meta_Key::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$results = Switch_Post_Meta_Key::verify_up_results();

		$this->assertTrue( $results['success'], implode( "\n", $results['errors'] ) );
		$this->assertEquals( 0, $results['old_count'] );
		$this->assertEquals( 5, $results['new_count'] );
		$this->assertTrue( $results['values_preserved'], 'Meta values should be preserved after migration' );
	}

	/**
	 * @test
	 */
	public function it_should_process_correct_number_of_batches(): void {
		Switch_Post_Meta_Key::create_dummy_data( 3 );

		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_switch_post_meta_key', Switch_Post_Meta_Key::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$calls = tests_migrations_get_calls_data();

		$this->assertCount( 3, $calls );

		foreach ( $calls as $index => $call ) {
			$args = $call->get_args();
			$this->assertEquals( 'up', $args[0] );
			$this->assertEquals( 'tests_switch_post_meta_key', $args[1] );
			$this->assertEquals( $index + 1, $args[2] ); // Batch numbers 1, 2, 3.
		}
	}

	/**
	 * @test
	 */
	public function it_should_rollback_and_restore_original_state(): void {
		$data = Switch_Post_Meta_Key::create_dummy_data( 3 );

		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_switch_post_meta_key', Switch_Post_Meta_Key::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$up_results = Switch_Post_Meta_Key::verify_up_results();
		$this->assertTrue( $up_results['success'], 'Migration should complete successfully' );

		tests_migrations_clear_calls_data();

		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_switch_post_meta_key' );

		$task = new Execute( 'down', 'tests_switch_post_meta_key', 1, 1, $execution['id'] );
		shepherd()->dispatch( $task );

		$migration = $registry->get( 'tests_switch_post_meta_key' );

		$this->assertTrue( $migration->is_down_done() );

		$down_results = Switch_Post_Meta_Key::verify_down_results();

		$this->assertTrue( $down_results['success'], implode( "\n", $down_results['errors'] ) );
		$this->assertEquals( 3, $down_results['old_count'] );
		$this->assertEquals( 0, $down_results['new_count'] );
		$this->assertTrue( $down_results['values_preserved'], 'Meta values should be restored after rollback' );
	}

	/**
	 * @test
	 */
	public function it_should_rollback_correct_number_of_batches(): void {
		Switch_Post_Meta_Key::create_dummy_data( 3 );

		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_switch_post_meta_key', Switch_Post_Meta_Key::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		tests_migrations_clear_calls_data();

		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_switch_post_meta_key' );

		$task = new Execute( 'down', 'tests_switch_post_meta_key', 1, 1, $execution['id'] );
		shepherd()->dispatch( $task );

		$calls = tests_migrations_get_calls_data();

		$this->assertCount( 3, $calls );

		foreach ( $calls as $call ) {
			$args = $call->get_args();
			$this->assertEquals( 'down', $args[0] );
			$this->assertEquals( 'tests_switch_post_meta_key', $args[1] );
		}
	}

	/**
	 * @test
	 */
	public function it_should_skip_migration_when_no_data_to_migrate(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_switch_post_meta_key', Switch_Post_Meta_Key::class );

		$migration = $registry->get( 'tests_switch_post_meta_key' );

		$this->assertTrue( $migration->is_up_done() );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$calls = tests_migrations_get_calls_data();

		$this->assertNotEmpty( $calls );

		$counts = Switch_Post_Meta_Key::get_counts();
		$this->assertEquals( 0, $counts['old_count'] );
		$this->assertEquals( 0, $counts['new_count'] );
	}

	/**
	 * @test
	 */
	public function it_should_record_all_migration_logs(): void {
		// Arrange.
		Switch_Post_Meta_Key::create_dummy_data( 2 );

		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_switch_post_meta_key', Switch_Post_Meta_Key::class );

		// Act.
		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		// Assert.
		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_switch_post_meta_key' );
		$this->assertNotNull( $execution );
		$this->assertEquals( 'completed', $execution['status'] );

		$logs = Migration_Logs::get_all_by( 'migration_execution_id', $execution['id'] );
		$this->assertNotEmpty( $logs, 'Should have log entries' );

		// Check for migration started log.
		$started_logs = array_filter(
			$logs,
			function ( $log ) {
				return strpos( $log['message'], 'started' ) !== false;
			}
		);
		$this->assertNotEmpty( $started_logs, 'Should have migration started log' );

		// Check for migration completed log.
		$completed_logs = array_filter(
			$logs,
			function ( $log ) {
				return strpos( $log['message'], 'completed' ) !== false;
			}
		);
		$this->assertNotEmpty( $completed_logs, 'Should have migration completed log' );

		// Verify log types are valid.
		foreach ( $logs as $log ) {
			$this->assertContains(
				$log['type'],
				[ 'info', 'warning', 'error', 'debug' ],
				'Log type should be valid'
			);
		}
	}

	/**
	 * @test
	 */
	public function it_should_handle_migration_then_rollback_then_migration_again(): void {
		Switch_Post_Meta_Key::create_dummy_data( 2 );

		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_switch_post_meta_key', Switch_Post_Meta_Key::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$up_results_1 = Switch_Post_Meta_Key::verify_up_results();
		$this->assertTrue( $up_results_1['success'] );

		tests_migrations_clear_calls_data();
		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_switch_post_meta_key' );

		$task = new Execute( 'down', 'tests_switch_post_meta_key', 1, 1, $execution['id'] );
		shepherd()->dispatch( $task );

		$down_results = Switch_Post_Meta_Key::verify_down_results();
		$this->assertTrue( $down_results['success'] );

		tests_migrations_clear_calls_data();
		$task = new Execute( 'up', 'tests_switch_post_meta_key', 1, 1, $execution['id'] );
		shepherd()->dispatch( $task );

		$up_results_2 = Switch_Post_Meta_Key::verify_up_results();
		$this->assertTrue( $up_results_2['success'] );
		$this->assertTrue( $up_results_2['values_preserved'] );
	}

	/**
	 * @after
	 */
	public function cleanup(): void {
		Switch_Post_Meta_Key::reset();
		tests_migrations_clear_calls_data();
	}
}

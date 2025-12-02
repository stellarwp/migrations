<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Migrations\Switch_Post_Meta_Key;
use StellarWP\Migrations\Tables\Migration_Events;
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

		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Switch_Post_Meta_Key();

		$registry->register( $migration );

		$this->assertFalse( $migration->is_up_done() );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

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

		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Switch_Post_Meta_Key();

		$registry->register( $migration );

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

		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Switch_Post_Meta_Key();

		$registry->register( $migration );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$calls = tests_migrations_get_calls_data();

		$this->assertCount( 3, $calls );

		foreach ( $calls as $index => $call ) {
			$args = $call->get_args();
			$this->assertEquals( 'up', $args[0] );
			$this->assertEquals( $migration->get_id(), $args[1] );
			$this->assertEquals( $index + 1, $args[2] ); // Batch numbers 1, 2, 3.
		}
	}

	/**
	 * @test
	 */
	public function it_should_rollback_and_restore_original_state(): void {
		$data = Switch_Post_Meta_Key::create_dummy_data( 3 );

		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Switch_Post_Meta_Key();

		$registry->register( $migration );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$up_results = Switch_Post_Meta_Key::verify_up_results();
		$this->assertTrue( $up_results['success'], 'Migration should complete successfully' );

		tests_migrations_clear_calls_data();

		$task = new Execute( 'down', $migration->get_id(), 1 );
		shepherd()->dispatch( $task );

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

		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Switch_Post_Meta_Key();

		$registry->register( $migration );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		tests_migrations_clear_calls_data();

		$task = new Execute( 'down', $migration->get_id(), 1 );
		shepherd()->dispatch( $task );

		$calls = tests_migrations_get_calls_data();

		$this->assertCount( 3, $calls );

		foreach ( $calls as $call ) {
			$args = $call->get_args();
			$this->assertEquals( 'down', $args[0] );
			$this->assertEquals( $migration->get_id(), $args[1] );
		}
	}

	/**
	 * @test
	 */
	public function it_should_skip_migration_when_no_data_to_migrate(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Switch_Post_Meta_Key();

		$registry->register( $migration );

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
	public function it_should_record_all_migration_events(): void {
		Switch_Post_Meta_Key::create_dummy_data( 2 );

		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Switch_Post_Meta_Key();

		$registry->register( $migration );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$events = Migration_Events::get_all_by( 'migration_id', $migration->get_id() );
		$types  = array_column( $events, 'type' );

		$this->assertContains( Migration_Events::TYPE_SCHEDULED, $types );

		$batch_started_count = count( array_filter( $types, fn( $t ) => $t === Migration_Events::TYPE_BATCH_STARTED ) );
		$this->assertEquals( 2, $batch_started_count );

		$this->assertContains( Migration_Events::TYPE_BATCH_COMPLETED, $types );
		$this->assertContains( Migration_Events::TYPE_COMPLETED, $types );
	}

	/**
	 * @test
	 */
	public function it_should_handle_migration_then_rollback_then_migration_again(): void {
		Switch_Post_Meta_Key::create_dummy_data( 2 );

		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Switch_Post_Meta_Key();

		$registry->register( $migration );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$up_results_1 = Switch_Post_Meta_Key::verify_up_results();
		$this->assertTrue( $up_results_1['success'] );

		tests_migrations_clear_calls_data();
		$task = new Execute( 'down', $migration->get_id(), 1 );
		shepherd()->dispatch( $task );

		$down_results = Switch_Post_Meta_Key::verify_down_results();
		$this->assertTrue( $down_results['success'] );

		tests_migrations_clear_calls_data();
		$task = new Execute( 'up', $migration->get_id(), 1 );
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

		global $wp_actions;
		$prefix = Config::get_hook_prefix();
		unset( $wp_actions[ "stellarwp_migrations_{$prefix}_schedule_migrations" ] );
	}
}

<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Migrations\Simple_Migration;
use StellarWP\Migrations\Tests\Migrations\Not_Applicable_Migration;
use StellarWP\Migrations\Tables\Migration_Events;

class Is_Applicable_Test extends WPTestCase {
	/**
	 * @before
	 */
	public function reset_state(): void {
		Simple_Migration::reset();
		Not_Applicable_Migration::reset();
		tests_migrations_clear_calls_data();

		$container = Config::get_container();
		$container->get( Registry::class )->flush();
	}

	/**
	 * @test
	 */
	public function it_should_skip_non_applicable_migrations(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_not_applicable_migration', Not_Applicable_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertFalse( Not_Applicable_Migration::$up_called );
	}

	/**
	 * @test
	 */
	public function it_should_not_schedule_event_for_non_applicable_migrations(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_not_applicable_migration', Not_Applicable_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$event = Migration_Events::get_first_by( 'migration_id', 'tests_not_applicable_migration' );

		$this->assertNull( $event );
	}

	/**
	 * @test
	 */
	public function it_should_not_dispatch_task_for_non_applicable_migrations(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_not_applicable_migration', Not_Applicable_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$calls = tests_migrations_get_calls_data();

		$this->assertEmpty( $calls );
	}

	/**
	 * @test
	 */
	public function it_should_run_applicable_and_skip_non_applicable(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );
		$registry->register( 'tests_not_applicable_migration', Not_Applicable_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertTrue( Simple_Migration::$up_called );
		$this->assertFalse( Not_Applicable_Migration::$up_called );
	}

	/**
	 * @test
	 */
	public function it_should_only_schedule_events_for_applicable_migrations(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );
		$registry->register( 'tests_not_applicable_migration', Not_Applicable_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$applicable_event = Migration_Events::get_first_by( 'migration_id', 'tests_simple_migration' );
		$this->assertNotNull( $applicable_event );

		$not_applicable_event = Migration_Events::get_first_by( 'migration_id', 'tests_not_applicable_migration' );
		$this->assertNull( $not_applicable_event );
	}

	/**
	 * @test
	 */
	public function it_should_only_dispatch_tasks_for_applicable_migrations(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );
		$registry->register( 'tests_not_applicable_migration', Not_Applicable_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$calls = tests_migrations_get_calls_data();

		$this->assertCount( 1, $calls );

		$args = $calls[0]->get_args();
		$this->assertEquals( 'tests_simple_migration', $args[1] );
	}

	/**
	 * @after
	 */
	public function cleanup(): void {
		Simple_Migration::reset();
		Not_Applicable_Migration::reset();
		tests_migrations_clear_calls_data();
	}
}

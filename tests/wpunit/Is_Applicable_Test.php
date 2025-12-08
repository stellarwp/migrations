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
		$registry      = Config::get_container()->get( Registry::class );
		$not_applicable = new Not_Applicable_Migration();

		$registry->register( $not_applicable );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertFalse( Not_Applicable_Migration::$up_called );
	}

	/**
	 * @test
	 */
	public function it_should_not_schedule_event_for_non_applicable_migrations(): void {
		$registry       = Config::get_container()->get( Registry::class );
		$not_applicable = new Not_Applicable_Migration();

		$registry->register( $not_applicable );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$event = Migration_Events::get_first_by( 'migration_id', $not_applicable->get_id() );

		$this->assertNull( $event );
	}

	/**
	 * @test
	 */
	public function it_should_not_dispatch_task_for_non_applicable_migrations(): void {
		$registry       = Config::get_container()->get( Registry::class );
		$not_applicable = new Not_Applicable_Migration();

		$registry->register( $not_applicable );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$calls = tests_migrations_get_calls_data();

		$this->assertEmpty( $calls );
	}

	/**
	 * @test
	 */
	public function it_should_run_applicable_and_skip_non_applicable(): void {
		$registry       = Config::get_container()->get( Registry::class );
		$applicable     = new Simple_Migration();
		$not_applicable = new Not_Applicable_Migration();

		$registry->register( $applicable );
		$registry->register( $not_applicable );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertTrue( Simple_Migration::$up_called );
		$this->assertFalse( Not_Applicable_Migration::$up_called );
	}

	/**
	 * @test
	 */
	public function it_should_only_schedule_events_for_applicable_migrations(): void {
		$registry       = Config::get_container()->get( Registry::class );
		$applicable     = new Simple_Migration();
		$not_applicable = new Not_Applicable_Migration();

		$registry->register( $applicable );
		$registry->register( $not_applicable );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$applicable_event = Migration_Events::get_first_by( 'migration_id', $applicable->get_id() );
		$this->assertNotNull( $applicable_event );

		$not_applicable_event = Migration_Events::get_first_by( 'migration_id', $not_applicable->get_id() );
		$this->assertNull( $not_applicable_event );
	}

	/**
	 * @test
	 */
	public function it_should_only_dispatch_tasks_for_applicable_migrations(): void {
		$registry       = Config::get_container()->get( Registry::class );
		$applicable     = new Simple_Migration();
		$not_applicable = new Not_Applicable_Migration();

		$registry->register( $applicable );
		$registry->register( $not_applicable );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$calls = tests_migrations_get_calls_data();

		$this->assertCount( 1, $calls );

		$args = $calls[0]->get_args();
		$this->assertEquals( $applicable->get_id(), $args[1] );
	}

	/**
	 * @test
	 */
	public function it_should_create_dynamic_applicable_migration(): void {
		$registry = Config::get_container()->get( Registry::class );

		$should_be_applicable = true;

		$migration = new class( $should_be_applicable ) extends \StellarWP\Migrations\Abstracts\Migration_Abstract {
			private static bool $applicable;
			public static bool $up_called = false;

			public function __construct( bool $applicable ) {
				self::$applicable = $applicable;
			}

			public function get_total_batches(): int {
				return 1;
			}

			public function get_label(): string {
				return 'Dynamic Applicable Migration';
			}

			public function get_description(): string {
				return 'This is a dynamic migration that is applicable if the $applicable property is true.';
			}

			public function get_id(): string {
				return 'tests_dynamic_applicable';
			}

			public function is_applicable(): bool {
				return self::$applicable;
			}

			public function is_up_done(): bool {
				return self::$up_called;
			}

			public function is_down_done(): bool {
				return ! self::$up_called;
			}

			public function up( int $batch ): void {
				self::$up_called = true;
			}

			public function down( int $batch ): void {
				self::$up_called = false;
			}
		};

		$registry->register( $migration );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$event = Migration_Events::get_first_by( 'migration_id', $migration->get_id() );
		$this->assertNotNull( $event );
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

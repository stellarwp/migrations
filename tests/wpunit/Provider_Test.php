<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use StellarWP\Shepherd\Provider as Shepherd_Provider;
use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Migrations\Simple_Migration;
use StellarWP\Migrations\Tests\Traits\With_Uopz;
use function StellarWP\Shepherd\shepherd;

class Provider_Test extends WPTestCase {
	use With_Uopz;

	/**
	 * @before
	 */
	public function reset_state(): void {
		Simple_Migration::reset();
		tests_migrations_clear_calls_data();

		$container = Config::get_container();
		$container->get( Registry::class )->flush();
	}

	/**
	 * @test
	 */
	public function it_should_register_the_provider(): void {
		$this->assertTrue( Shepherd_Provider::is_registered() );
		$this->assertTrue( Provider::is_registered() );
	}

	/**
	 * @test
	 */
	public function it_should_have_registry_as_singleton(): void {
		$container = Config::get_container();

		$registry1 = $container->get( Registry::class );
		$registry2 = $container->get( Registry::class );

		$this->assertSame( $registry1, $registry2 );
	}

	/**
	 * @test
	 */
	public function it_should_schedule_migrations(): void {
		$registry = Config::get_container()->get( Registry::class );

		$this->assertCount( 0, $registry );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$this->assertCount( 1, $registry );

		$retrieved_migration = $registry->get( 'tests_simple_migration' );

		$this->assertInstanceOf( Simple_Migration::class, $retrieved_migration );

		$last_scheduled_task_id = shepherd()->get_last_scheduled_task_id();

		$this->assertNull( $last_scheduled_task_id );

		$prefix = Config::get_hook_prefix();

		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertTrue( Simple_Migration::$up_called );
	}

	/**
	 * @test
	 */
	public function it_should_trigger_doing_it_wrong_if_registering_migration_after_schedule(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$triggered = false;
		$this->set_fn_return(
			'_doing_it_wrong',
			function () use ( &$triggered ) {
				$triggered = true;
			},
			true 
		);

		$registry->register( 'tests_simple_migration_2', Simple_Migration::class );
		$this->assertTrue( $triggered );
	}

	/**
	 * @test
	 */
	public function it_should_skip_scheduling_when_cli_only_filter_returns_true(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$prefix = Config::get_hook_prefix();

		add_filter( "stellarwp_migrations_{$prefix}_migrations_only_via_cli", '__return_true' );

		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		remove_filter( "stellarwp_migrations_{$prefix}_migrations_only_via_cli", '__return_true' );

		$this->assertFalse( Simple_Migration::$up_called );

		$calls = tests_migrations_get_calls_data();
		$this->assertEmpty( $calls );
	}

	/**
	 * @test
	 */
	public function it_should_fire_pre_and_post_schedule_migrations_actions(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$prefix     = Config::get_hook_prefix();
		$pre_fired  = false;
		$post_fired = false;

		add_action(
			"stellarwp_migrations_{$prefix}_pre_schedule_migrations",
			function () use ( &$pre_fired ) {
				$pre_fired = true;
			}
		);

		add_action(
			"stellarwp_migrations_{$prefix}_post_schedule_migrations",
			function () use ( &$post_fired ) {
				$post_fired = true;
			}
		);

		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertTrue( $pre_fired );
		$this->assertTrue( $post_fired );
	}

	/**
	 * @after
	 */
	public function cleanup(): void {
		Simple_Migration::reset();
		tests_migrations_clear_calls_data();
	}
}

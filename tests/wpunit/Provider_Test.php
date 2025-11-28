<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use StellarWP\Shepherd\Provider as Shepherd_Provider;
use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Migrations\Switch_Post_Meta_Key;
use RuntimeException;
use function StellarWP\Shepherd\shepherd;

class Provider_Test extends WPTestCase {
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
	public function it_should_schedule_migrations(): void {
		$registry = Config::get_container()->get( Registry::class );

		$this->assertCount( 0, $registry );

		$registry[] = new Switch_Post_Meta_Key();

		$this->assertCount( 1, $registry );

		$migration = $registry->get( 'tests_switch_post_meta_key' );

		$this->assertInstanceOf( Switch_Post_Meta_Key::class, $migration );

		$last_scheduled_task_id = shepherd()->get_last_scheduled_task_id();

		$this->assertNull( $last_scheduled_task_id );

		$prefix = Config::get_hook_prefix();

		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$last_scheduled_task_id = shepherd()->get_last_scheduled_task_id();

		$this->assertNotNull( $last_scheduled_task_id );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Too late to add a migration to the registry.' );

		$registry->register( new Switch_Post_Meta_Key() );
	}
}

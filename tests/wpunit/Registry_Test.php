<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Migrations\Simple_Migration;
use StellarWP\Migrations\Tests\Migrations\Multi_Batch_Migration;
use StellarWP\Migrations\Tests\Traits\With_Uopz;
use RuntimeException;

class Registry_Test extends WPTestCase {
	use With_Uopz;

	/**
	 * @before
	 */
	public function reset_registry(): void {
		$container = Config::get_container();
		$container->get( Registry::class )->flush();
	}

	/**
	 * @test
	 */
	public function it_should_start_empty(): void {
		$registry = Config::get_container()->get( Registry::class );

		$this->assertCount( 0, $registry );
	}

	/**
	 * @test
	 */
	public function it_should_register_a_migration(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$this->assertCount( 1, $registry );
		$this->assertTrue( isset( $registry['tests_simple_migration'] ) );
	}

	/**
	 * @test
	 */
	public function it_should_register_multiple_migrations(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );
		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );

		$this->assertCount( 2, $registry );
	}

	/**
	 * @test
	 */
	public function it_should_get_a_migration_by_id(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$retrieved = $registry->get( 'tests_simple_migration' );

		$this->assertInstanceOf( Simple_Migration::class, $retrieved );
	}

	/**
	 * @test
	 */
	public function it_should_return_null_for_non_existent_migration(): void {
		$registry = Config::get_container()->get( Registry::class );

		$retrieved = $registry->get( 'non_existent_migration' );

		$this->assertNull( $retrieved );
	}

	/**
	 * @test
	 */
	public function it_should_allow_array_access_set(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry['tests_simple_migration'] = Simple_Migration::class;

		$this->assertCount( 1, $registry );
		$this->assertInstanceOf( Simple_Migration::class, $registry['tests_simple_migration'] );
	}

	/**
	 * @test
	 */
	public function it_should_allow_array_access_set_with_explicit_key(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry['custom_key'] = Simple_Migration::class;

		$this->assertCount( 1, $registry );
		$this->assertInstanceOf( Simple_Migration::class, $registry['custom_key'] );
		$this->assertTrue( isset( $registry['custom_key'] ) );
	}

	/**
	 * @test
	 */
	public function it_should_allow_array_access_isset(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$this->assertTrue( isset( $registry['tests_simple_migration'] ) );
		$this->assertFalse( isset( $registry['non_existent'] ) );
	}

	/**
	 * @test
	 */
	public function it_should_allow_array_access_unset(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );
		$this->assertCount( 1, $registry );

		unset( $registry['tests_simple_migration'] );
		$this->assertCount( 0, $registry );
	}

	/**
	 * @test
	 */
	public function it_should_be_iterable(): void {
		$registry   = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );
		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );

		$ids = [];
		foreach ( $registry as $id => $migration ) {
			$ids[] = $id;
		}

		$this->assertCount( 2, $ids );
		$this->assertContains( 'tests_simple_migration', $ids );
		$this->assertContains( 'tests_multi_batch_migration', $ids );
	}

	/**
	 * @test
	 */
	public function it_should_accept_migration_id_at_max_length(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( str_repeat( 'a', 191 ), Simple_Migration::class );

		$this->assertCount( 1, $registry );
		$this->assertInstanceOf( Simple_Migration::class, $registry->get( str_repeat( 'a', 191 ) ) );
	}

	/**
	 * @test
	 */
	public function it_should_throw_if_migration_id_is_too_long(): void {
		$registry = Config::get_container()->get( Registry::class );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'too long' );

		$registry->register( str_repeat( 'a', 192 ), Simple_Migration::class );
	}

	/**
	 * @test
	 */
	public function it_should_accept_migrations_via_constructor(): void {
		$registry = new Registry( [
			'tests_simple_migration' => Simple_Migration::class,
			'tests_multi_batch_migration' => Multi_Batch_Migration::class,
		] );

		$this->assertCount( 2, $registry );
		$this->assertInstanceOf( Simple_Migration::class, $registry->get( 'tests_simple_migration' ) );
		$this->assertInstanceOf( Multi_Batch_Migration::class, $registry->get( 'tests_multi_batch_migration' ) );
	}

	/**
	 * @test
	 */
	public function it_should_throw_if_non_migration_passed_to_constructor(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'You should pass a map of Migration IDs to Migration class-strings to the Registry constructor.' );

		new Registry( [ 'not a migration' ] );
	}

	/**
	 * @test
	 */
	public function it_should_trigger_doing_it_wrong_if_registering_after_schedule_action(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$prefix    = Config::get_hook_prefix();

		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$triggered = false;
		$this->set_fn_return(
			'_doing_it_wrong',
			function () use ( &$triggered ) {
				$triggered = true;
			},
			true
		);

		$registry->register( 'tests_simple_migration', Simple_Migration::class );
		$this->assertTrue( $triggered );
	}

	/**
	 * @after
	 */
	public function cleanup(): void {
		Simple_Migration::reset();
		Multi_Batch_Migration::reset();
	}
}

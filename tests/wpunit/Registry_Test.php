<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Migrations\Simple_Migration;
use StellarWP\Migrations\Tests\Migrations\Multi_Batch_Migration;
use RuntimeException;

class Registry_Test extends WPTestCase {
	/**
	 * @before
	 */
	public function reset_registry(): void {
		$container = Config::get_container();
		$container->singleton( Registry::class, new Registry() );
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
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry->register( $migration );

		$this->assertCount( 1, $registry );
		$this->assertTrue( isset( $registry[ $migration->get_id() ] ) );
	}

	/**
	 * @test
	 */
	public function it_should_register_multiple_migrations(): void {
		$registry   = Config::get_container()->get( Registry::class );
		$migration1 = new Simple_Migration();
		$migration2 = new Multi_Batch_Migration();

		$registry->register( $migration1 );
		$registry->register( $migration2 );

		$this->assertCount( 2, $registry );
	}

	/**
	 * @test
	 */
	public function it_should_get_a_migration_by_id(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry->register( $migration );

		$retrieved = $registry->get( $migration->get_id() );

		$this->assertSame( $migration, $retrieved );
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
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry[] = $migration;

		$this->assertCount( 1, $registry );
		$this->assertSame( $migration, $registry[ $migration->get_id() ] );
	}

	/**
	 * @test
	 */
	public function it_should_allow_array_access_set_with_explicit_key(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry['custom_key'] = $migration;

		$this->assertCount( 1, $registry );
		$this->assertSame( $migration, $registry[ $migration->get_id() ] );
		$this->assertFalse( isset( $registry['custom_key'] ) );
		$this->assertTrue( isset( $registry['tests_simple_migration'] ) );
	}

	/**
	 * @test
	 */
	public function it_should_allow_array_access_isset(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry->register( $migration );

		$this->assertTrue( isset( $registry[ $migration->get_id() ] ) );
		$this->assertFalse( isset( $registry['non_existent'] ) );
	}

	/**
	 * @test
	 */
	public function it_should_allow_array_access_unset(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry->register( $migration );
		$this->assertCount( 1, $registry );

		unset( $registry[ $migration->get_id() ] );
		$this->assertCount( 0, $registry );
	}

	/**
	 * @test
	 */
	public function it_should_be_iterable(): void {
		$registry   = Config::get_container()->get( Registry::class );
		$migration1 = new Simple_Migration();
		$migration2 = new Multi_Batch_Migration();

		$registry->register( $migration1 );
		$registry->register( $migration2 );

		$ids = [];
		foreach ( $registry as $id => $migration ) {
			$ids[] = $id;
		}

		$this->assertCount( 2, $ids );
		$this->assertContains( $migration1->get_id(), $ids );
		$this->assertContains( $migration2->get_id(), $ids );
	}

	/**
	 * @test
	 */
	public function it_should_accept_migration_id_at_max_length(): void {
		$registry = Config::get_container()->get( Registry::class );

		$migration = new class extends \StellarWP\Migrations\Abstracts\Migration_Abstract {
			public function get_id(): string {
				return str_repeat( 'a', 191 );
			}

			public function is_applicable(): bool {
				return true;
			}

			public function is_up_done(): bool {
				return false;
			}

			public function is_down_done(): bool {
				return true;
			}

			public function up( int $batch ): void {}

			public function down( int $batch ): void {}
		};

		$registry->register( $migration );

		$this->assertCount( 1, $registry );
		$this->assertSame( $migration, $registry->get( str_repeat( 'a', 191 ) ) );
	}

	/**
	 * @test
	 */
	public function it_should_throw_if_migration_id_is_too_long(): void {
		$registry = Config::get_container()->get( Registry::class );

		$migration = new class extends \StellarWP\Migrations\Abstracts\Migration_Abstract {
			public function get_id(): string {
				return str_repeat( 'a', 192 );
			}

			public function is_applicable(): bool {
				return true;
			}

			public function is_up_done(): bool {
				return false;
			}

			public function is_down_done(): bool {
				return true;
			}

			public function up( int $batch ): void {}

			public function down( int $batch ): void {}
		};

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'too long' );

		$registry->register( $migration );
	}

	/**
	 * @test
	 */
	public function it_should_accept_migrations_via_constructor(): void {
		$migration1 = new Simple_Migration();
		$migration2 = new Multi_Batch_Migration();

		$registry = new Registry( [ $migration1, $migration2 ] );

		$this->assertCount( 2, $registry );
		$this->assertSame( $migration1, $registry->get( $migration1->get_id() ) );
		$this->assertSame( $migration2, $registry->get( $migration2->get_id() ) );
	}

	/**
	 * @test
	 */
	public function it_should_throw_if_non_migration_passed_to_constructor(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Not a migration' );

		new Registry( [ 'not a migration' ] );
	}

	/**
	 * @test
	 */
	public function it_should_throw_if_registering_after_schedule_action(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();
		$prefix    = Config::get_hook_prefix();

		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Too late to add a migration' );

		$registry->register( $migration );
	}

	/**
	 * @after
	 */
	public function cleanup(): void {
		Simple_Migration::reset();
		Multi_Batch_Migration::reset();

		global $wp_actions;
		$prefix = Config::get_hook_prefix();
		unset( $wp_actions[ "stellarwp_migrations_{$prefix}_schedule_migrations" ] );
	}
}

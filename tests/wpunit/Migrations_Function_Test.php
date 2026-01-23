<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Traits\With_Uopz;

use function StellarWP\Migrations\migrations;

class Migrations_Function_Test extends WPTestCase {
	use With_Uopz;

	private $stored_static_value = null;

	/**
	 * Reset the static variable in the migrations function before each test.
	 *
	 * @before
	 */
	public function reset_migrations_static(): void {
		$this->stored_static_value = uopz_get_static( 'StellarWP\\Migrations\\migrations' );
		uopz_set_static( 'StellarWP\\Migrations\\migrations', [ 'migrations' => null ] );
	}

	/**
	 * Reset the static variable after each test.
	 *
	 * @after
	 */
	public function cleanup_migrations_static(): void {
		uopz_set_static( 'StellarWP\\Migrations\\migrations', $this->stored_static_value );
	}

	/**
	 * @test
	 */
	public function it_should_return_the_same_provider_instance(): void {
		$provider = migrations();

		$this->assertInstanceOf( Provider::class, $provider );

		$provider1 = migrations();
		$provider2 = migrations();

		$this->assertSame( $provider1, $provider2 );
		$this->assertSame( $provider, $provider1 );
	}

	/**
	 * @test
	 */
	public function it_should_have_access_to_registry(): void {
		$provider = migrations();

		$registry = $provider->get_registry();

		$this->assertInstanceOf( Registry::class, $registry );
	}
}

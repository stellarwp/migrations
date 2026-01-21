<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use RuntimeException;
use StellarWP\ContainerContract\ContainerInterface;

class Config_Test extends WPTestCase {
	/**
	 * @var ContainerInterface|null
	 */
	private ?ContainerInterface $original_container = null;

	/**
	 * @var string
	 */
	private string $original_prefix = '';

	/**
	 * @before
	 */
	public function store_original_config(): void {
		$this->original_container = Config::get_container();
		$this->original_prefix    = Config::get_hook_prefix();
	}

	/**
	 * @test
	 */
	public function it_should_throw_when_getting_container_before_set(): void {
		Config::reset();

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'You must provide a container' );

		Config::get_container();
	}

	/**
	 * @test
	 */
	public function it_should_throw_when_getting_hook_prefix_before_set(): void {
		Config::reset();

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'You must specify a hook prefix' );

		Config::get_hook_prefix();
	}

	/**
	 * @test
	 */
	public function it_should_throw_when_setting_empty_hook_prefix(): void {
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'hook prefix cannot be empty' );

		Config::set_hook_prefix( '' );
	}

	/**
	 * @test
	 */
	public function it_should_set_and_get_container(): void {
		Config::reset();

		$container = $this->original_container;
		Config::set_container( $container );

		$this->assertSame( $container, Config::get_container() );
	}

	/**
	 * @test
	 */
	public function it_should_set_and_get_hook_prefix(): void {
		Config::reset();
		Config::set_container( $this->original_container );

		Config::set_hook_prefix( 'test_prefix' );

		$this->assertEquals( 'test_prefix', Config::get_hook_prefix() );
	}

	/**
	 * @test
	 */
	public function it_should_reset_config(): void {
		Config::set_container( $this->original_container );
		Config::set_hook_prefix( 'test_prefix' );

		Config::reset();

		$this->expectException( RuntimeException::class );
		Config::get_container();
	}

	/**
	 * @test
	 */
	public function it_should_set_and_get_assets_url(): void {
		Config::reset();

		$assets_url = 'https://example.com/assets/';
		Config::set_assets_url( $assets_url );

		$this->assertEquals( $assets_url, Config::get_assets_url() );
	}

	/**
	 * @test
	 */
	public function it_should_return_null_when_assets_url_not_set(): void {
		Config::reset();

		$this->assertNull( Config::get_assets_url() );
	}

	/**
	 * @test
	 */
	public function it_should_reset_assets_url(): void {
		Config::set_assets_url( 'https://example.com/assets/' );

		Config::reset();

		$this->assertNull( Config::get_assets_url() );
	}

	/**
	 * @after
	 */
	public function restore_original_config(): void {
		Config::reset();

		if ( $this->original_container ) {
			Config::set_container( $this->original_container );
		}

		if ( $this->original_prefix ) {
			Config::set_hook_prefix( $this->original_prefix );
		}
	}
}

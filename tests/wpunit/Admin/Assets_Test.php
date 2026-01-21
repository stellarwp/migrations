<?php
/**
 * Admin Assets Tests.
 *
 * Tests the Admin Assets class for asset registration and URL handling.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Admin
 */

declare(strict_types=1);

namespace StellarWP\Migrations\Tests\Admin;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Admin\Assets;
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Provider;

/**
 * Admin Assets Tests.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Admin
 */
class Assets_Test extends WPTestCase {
	/**
	 * The original assets URL.
	 *
	 * @var string|null
	 */
	protected ?string $original_assets_url = null;

	/**
	 * Set up before each test.
	 *
	 * @before
	 *
	 * @return void
	 */
	public function set_up_assets(): void {
		// Store the original assets URL.
		$this->original_assets_url = Config::get_assets_url();

		// Deregister any assets from previous tests.
		$handle = Config::get_hook_prefix() . '-migrations-admin';
		wp_deregister_script( $handle );
		wp_deregister_style( $handle );

		$select2_handle = Config::get_hook_prefix() . '-migrations-select2';
		wp_deregister_script( $select2_handle );
		wp_deregister_style( $select2_handle );

		// Set a valid test assets URL.
		Config::set_assets_url( site_url( '/wp-content/plugins/migrations/assets/' ) );
	}

	/**
	 * Tear down after each test.
	 *
	 * @after
	 *
	 * @return void
	 */
	public function tear_down_assets(): void {
		// Restore the original assets URL.
		if ( $this->original_assets_url ) {
			Config::set_assets_url( $this->original_assets_url );
		}

		// Deregister any assets registered during tests.
		$handle = Config::get_hook_prefix() . '-migrations-admin';
		wp_deregister_script( $handle );
		wp_deregister_style( $handle );

		$select2_handle = Config::get_hook_prefix() . '-migrations-select2';
		wp_deregister_script( $select2_handle );
		wp_deregister_style( $select2_handle );
	}

	/**
	 * @test
	 */
	public function it_should_register_assets(): void {
		$assets = new Assets();
		$assets->register_assets();

		$handle = Config::get_hook_prefix() . '-migrations-admin';

		$this->assertTrue( wp_script_is( $handle, 'registered' ) );
		$this->assertTrue( wp_style_is( $handle, 'registered' ) );
	}

	/**
	 * @test
	 */
	public function it_should_register_select2_assets(): void {
		$assets = new Assets();
		$assets->register_assets();

		$select2_handle = Config::get_hook_prefix() . '-migrations-select2';

		$this->assertTrue( wp_script_is( $select2_handle, 'registered' ) );
		$this->assertTrue( wp_style_is( $select2_handle, 'registered' ) );
	}

	/**
	 * @test
	 */
	public function it_should_not_register_assets_twice(): void {
		$assets = new Assets();

		// Register assets first time.
		$assets->register_assets();

		$handle = Config::get_hook_prefix() . '-migrations-admin';

		// Get the script URL after first registration.
		$scripts         = wp_scripts();
		$original_script = $scripts->registered[ $handle ] ?? null;
		$this->assertNotNull( $original_script );

		// Register assets second time.
		$assets->register_assets();

		// Should still be the same script object (not re-registered).
		$scripts_after = wp_scripts();
		$this->assertSame(
			$original_script,
			$scripts_after->registered[ $handle ]
		);
	}

	/**
	 * @test
	 */
	public function it_should_enqueue_assets(): void {
		$assets = new Assets();
		$assets->enqueue_assets();

		$handle = Config::get_hook_prefix() . '-migrations-admin';

		$this->assertTrue( wp_script_is( $handle, 'enqueued' ) );
		$this->assertTrue( wp_style_is( $handle, 'enqueued' ) );
	}

	/**
	 * @test
	 */
	public function it_should_not_enqueue_assets_twice(): void {
		$assets = new Assets();

		// Enqueue assets first time.
		$assets->enqueue_assets();

		$handle = Config::get_hook_prefix() . '-migrations-admin';

		$this->assertTrue( wp_script_is( $handle, 'enqueued' ) );

		// Enqueue assets second time (should be a no-op).
		$assets->enqueue_assets();

		// Should still be enqueued (no error).
		$this->assertTrue( wp_script_is( $handle, 'enqueued' ) );
	}

	/**
	 * @test
	 */
	public function it_should_use_configured_assets_url(): void {
		$custom_url = 'https://cdn.example.com/assets/';
		Config::set_assets_url( $custom_url );

		$assets = new Assets();
		$assets->register_assets();

		$handle  = Config::get_hook_prefix() . '-migrations-admin';
		$scripts = wp_scripts();
		$script  = $scripts->registered[ $handle ] ?? null;

		$this->assertNotNull( $script );
		$this->assertStringStartsWith( $custom_url, $script->src );
	}

	/**
	 * @test
	 */
	public function it_should_use_correct_path_for_admin_js(): void {
		$assets = new Assets();
		$assets->register_assets();

		$handle  = Config::get_hook_prefix() . '-migrations-admin';
		$scripts = wp_scripts();
		$script  = $scripts->registered[ $handle ] ?? null;

		$this->assertNotNull( $script );
		// The URL should contain 'assets' and the correct file path.
		$this->assertStringContainsString( 'assets', $script->src );
		$this->assertStringContainsString( 'js/admin.js', $script->src );
	}

	/**
	 * @test
	 */
	public function it_should_use_correct_version_for_library_assets(): void {
		$assets = new Assets();
		$assets->register_assets();

		$handle  = Config::get_hook_prefix() . '-migrations-admin';
		$scripts = wp_scripts();
		$script  = $scripts->registered[ $handle ] ?? null;

		$this->assertNotNull( $script );
		$this->assertEquals( Provider::VERSION, $script->ver );
	}

	/**
	 * @test
	 */
	public function it_should_use_select2_version_for_select2_assets(): void {
		$assets = new Assets();
		$assets->register_assets();

		$select2_handle = Config::get_hook_prefix() . '-migrations-select2';
		$scripts        = wp_scripts();
		$script         = $scripts->registered[ $select2_handle ] ?? null;

		$this->assertNotNull( $script );
		$this->assertEquals( '4.0.13', $script->ver );
	}

	/**
	 * @test
	 */
	public function it_should_have_correct_dependencies_for_admin_js(): void {
		$assets = new Assets();
		$assets->register_assets();

		$handle  = Config::get_hook_prefix() . '-migrations-admin';
		$scripts = wp_scripts();
		$script  = $scripts->registered[ $handle ] ?? null;

		$this->assertNotNull( $script );
		$this->assertContains( 'wp-dom-ready', $script->deps );
		$this->assertContains( 'wp-api-fetch', $script->deps );
		$this->assertContains( 'wp-i18n', $script->deps );
		$this->assertContains( 'jquery', $script->deps );
		$this->assertContains( Config::get_hook_prefix() . '-migrations-select2', $script->deps );
	}

	/**
	 * @test
	 */
	public function it_should_have_select2_dependency_for_admin_css(): void {
		$assets = new Assets();
		$assets->register_assets();

		$handle = Config::get_hook_prefix() . '-migrations-admin';
		$styles = wp_styles();
		$style  = $styles->registered[ $handle ] ?? null;

		$this->assertNotNull( $style );
		$this->assertContains( Config::get_hook_prefix() . '-migrations-select2', $style->deps );
	}
}

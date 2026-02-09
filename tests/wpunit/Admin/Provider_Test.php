<?php
/**
 * Admin Provider Tests.
 *
 * Tests the Admin Provider's parent page sidebar highlighting
 * and list URL (back link) features.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Admin
 */

declare(strict_types=1);

namespace StellarWP\Migrations\Tests\Admin;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Admin\Provider;
use StellarWP\Migrations\Tests\Traits\With_Uopz;

/**
 * Admin Provider Tests.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Admin
 */
class Provider_Test extends WPTestCase {
	use With_Uopz;

	/**
	 * The Provider instance.
	 *
	 * @var Provider
	 */
	protected Provider $provider;

	/**
	 * Set up before each test.
	 *
	 * @before
	 *
	 * @return void
	 */
	public function set_up_provider(): void {
		Provider::reset();
		$this->provider = new Provider();
	}

	/**
	 * Tear down after each test.
	 *
	 * @after
	 *
	 * @return void
	 */
	public function tear_down_provider(): void {
		Provider::reset();
		unset( $_GET['page'] );
	}

	/**
	 * @test
	 */
	public function it_should_not_hijack_parent_file_when_no_parent_page_set(): void {
		global $parent_file;

		$original_parent_file = $parent_file;
		$submenu_file         = 'some-submenu-file.php';

		$result = $this->provider->hijack_current_parent_file( $submenu_file );

		$this->assertSame( $submenu_file, $result, 'The submenu_file should be returned unchanged.' );
		$this->assertSame( $original_parent_file, $parent_file, 'The global $parent_file should not be modified.' );
	}

	/**
	 * @test
	 */
	public function it_should_not_hijack_parent_file_when_not_on_single_page(): void {
		global $parent_file;

		Provider::set_parent_page( 'edit.php?post_type=page' );

		$original_parent_file = $parent_file;
		$submenu_file         = 'some-submenu-file.php';

		// Simulate being on a different admin page.
		$_GET['page'] = 'some-other-page';

		$result = $this->provider->hijack_current_parent_file( $submenu_file );

		$this->assertSame( $submenu_file, $result, 'The submenu_file should be returned unchanged.' );
		$this->assertSame( $original_parent_file, $parent_file, 'The global $parent_file should not be modified when not on the single page.' );
	}

	/**
	 * @test
	 */
	public function it_should_hijack_parent_file_when_on_single_page(): void {
		global $parent_file;

		$target_parent = 'edit.php?post_type=page';
		Provider::set_parent_page( $target_parent );

		$parent_file  = 'options-general.php';
		$submenu_file = 'some-submenu-file.php';

		// Simulate being on the single migration page.
		$_GET['page'] = 'stellarwp-migrations-foobar-single';

		$result = $this->provider->hijack_current_parent_file( $submenu_file );

		$this->assertSame( $submenu_file, $result, 'The submenu_file return value should remain unchanged.' );
		$this->assertSame( $target_parent, $parent_file, 'The global $parent_file should be overridden to the configured parent page.' );
	}

	/**
	 * @test
	 */
	public function it_should_restore_parent_file_after_hijack(): void {
		global $parent_file;

		$original_parent_file = 'options-general.php';
		$target_parent        = 'edit.php?post_type=page';

		$parent_file = $original_parent_file;
		Provider::set_parent_page( $target_parent );

		// Simulate being on the single migration page and trigger hijack.
		$_GET['page'] = 'stellarwp-migrations-foobar-single';
		$this->provider->hijack_current_parent_file( 'submenu.php' );

		// Verify hijack occurred.
		$this->assertSame( $target_parent, $parent_file, 'The parent_file should be hijacked at this point.' );

		// Restore the parent file.
		$this->provider->restore_current_parent_file();

		$this->assertSame( $original_parent_file, $parent_file, 'The global $parent_file should be restored to its original value.' );
	}

	/**
	 * @test
	 */
	public function it_should_not_restore_when_no_hijack_occurred(): void {
		global $parent_file;

		$parent_file = 'options-general.php';

		// Call restore without any prior hijack.
		$this->provider->restore_current_parent_file();

		$this->assertSame( 'options-general.php', $parent_file, 'The global $parent_file should remain unchanged when no hijack occurred.' );
	}

	/**
	 * @test
	 */
	public function it_should_return_null_when_list_url_not_set(): void {
		$this->assertNull( Provider::get_list_url(), 'The list URL should be null by default.' );
	}

	/**
	 * @test
	 */
	public function it_should_return_configured_list_url(): void {
		$url = 'https://example.com/wp-admin/admin.php?page=my-migrations';

		Provider::set_list_url( $url );

		$this->assertSame( $url, Provider::get_list_url(), 'The list URL should match the configured value.' );
	}

	/**
	 * @test
	 */
	public function it_should_reset_parent_page_and_list_url(): void {
		global $parent_file;

		// Set up state.
		Provider::set_parent_page( 'edit.php?post_type=page' );
		Provider::set_list_url( 'https://example.com/wp-admin/admin.php?page=my-migrations' );

		// Trigger a hijack to populate stored_parent_file.
		$parent_file  = 'options-general.php';
		$_GET['page'] = 'stellarwp-migrations-foobar-single';
		$this->provider->hijack_current_parent_file( 'submenu.php' );

		// Reset all state.
		Provider::reset();

		$this->assertNull( Provider::get_list_url(), 'The list URL should be null after reset.' );

		// After reset, hijack should be a no-op (parent_page is cleared).
		$parent_file = 'some-file.php';
		$result      = $this->provider->hijack_current_parent_file( 'submenu.php' );

		$this->assertSame( 'some-file.php', $parent_file, 'The parent_file should not be hijacked after reset.' );

		// After reset, restore should be a no-op (stored_parent_file is cleared).
		$this->provider->restore_current_parent_file();

		$this->assertSame( 'some-file.php', $parent_file, 'The parent_file should remain unchanged after restore when reset was called.' );
	}

	/**
	 * @test
	 */
	public function it_should_register_submenu_file_filter(): void {
		$this->provider->register();

		$this->assertIsInt(
			has_filter( 'submenu_file', [ $this->provider, 'hijack_current_parent_file' ] ),
			'The submenu_file filter should be registered.'
		);
	}

	/**
	 * @test
	 */
	public function it_should_register_adminmenu_action(): void {
		$this->provider->register();

		$this->assertIsInt(
			has_action( 'adminmenu', [ $this->provider, 'restore_current_parent_file' ] ),
			'The adminmenu action should be registered.'
		);
	}
}

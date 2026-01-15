<?php
/**
 * Admin Assets.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Admin
 */

declare(strict_types=1);

namespace StellarWP\Migrations\Admin;

use StellarWP\Migrations\Config;
use StellarWP\Migrations\Provider;

/**
 * Admin Assets.
 *
 * Handles registration and enqueuing of CSS and JavaScript assets
 * for the migrations admin interface.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Admin
 */
class Assets {
	/**
	 * Whether assets have been registered.
	 *
	 * @since 0.0.1
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Whether assets have been enqueued.
	 *
	 * @since 0.0.1
	 *
	 * @var bool
	 */
	private static bool $enqueued = false;

	/**
	 * Select2 version to use from CDN.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	private const SELECT2_VERSION = '4.0.13';

	/**
	 * Get the CSS handle.
	 *
	 * @since 0.0.1
	 *
	 * @return string The CSS handle.
	 */
	private static function get_css_handle(): string {
		return Config::get_hook_prefix() . '-migrations-admin';
	}

	/**
	 * Get the JS handle.
	 *
	 * @since 0.0.1
	 *
	 * @return string The JS handle.
	 */
	private static function get_js_handle(): string {
		return Config::get_hook_prefix() . '-migrations-admin';
	}

	/**
	 * Get the Select2 CSS handle.
	 *
	 * @since 0.0.1
	 *
	 * @return string The Select2 CSS handle.
	 */
	private static function get_select2_css_handle(): string {
		return Config::get_hook_prefix() . '-migrations-select2';
	}

	/**
	 * Get the Select2 JS handle.
	 *
	 * @since 0.0.1
	 *
	 * @return string The Select2 JS handle.
	 */
	private static function get_select2_js_handle(): string {
		return Config::get_hook_prefix() . '-migrations-select2';
	}

	/**
	 * Register Select2 from CDN.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	private function register_select2(): void {
		$select2_version = self::SELECT2_VERSION;

		wp_register_style(
			self::get_select2_css_handle(),
			"https://cdn.jsdelivr.net/npm/select2@{$select2_version}/dist/css/select2.min.css",
			[],
			$select2_version
		);

		wp_register_script(
			self::get_select2_js_handle(),
			"https://cdn.jsdelivr.net/npm/select2@{$select2_version}/dist/js/select2.min.js",
			[ 'jquery' ],
			$select2_version,
			true
		);
	}

	/**
	 * Get the assets directory URL.
	 *
	 * @since 0.0.1
	 *
	 * @return string The assets URL.
	 */
	private function get_assets_url(): string {
		// Get the path to the assets directory.
		$assets_path = dirname( __DIR__, 2 ) . '/assets/';

		// Convert to URL.
		return str_replace(
			wp_normalize_path( ABSPATH ),
			trailingslashit( site_url() ),
			wp_normalize_path( $assets_path )
		);
	}

	/**
	 * Register the admin assets.
	 *
	 * Call this method to register the CSS and JS assets. They will not be
	 * enqueued until `enqueue_assets()` is called.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function register_assets(): void {
		if ( self::$registered ) {
			return;
		}

		$assets_url = $this->get_assets_url();

		$this->register_select2();

		wp_register_style(
			self::get_css_handle(),
			$assets_url . 'css/admin.css',
			[ self::get_select2_css_handle() ],
			Provider::VERSION
		);

		wp_register_script(
			self::get_js_handle(),
			$assets_url . 'js/admin.js',
			[ 'jquery', self::get_select2_js_handle() ],
			Provider::VERSION,
			true
		);

		self::$registered = true;
	}

	/**
	 * Enqueue the admin assets.
	 *
	 * Call this method to enqueue the CSS and JS assets. Assets will be
	 * registered automatically if not already registered.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( self::$enqueued ) {
			return;
		}

		if ( ! self::$registered ) {
			$this->register_assets();
		}

		wp_enqueue_style( self::get_css_handle() );
		wp_enqueue_script( self::get_js_handle() );

		self::$enqueued = true;
	}

	/**
	 * Reset the assets state.
	 *
	 * Useful for testing.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$registered = false;
		self::$enqueued   = false;
	}
}

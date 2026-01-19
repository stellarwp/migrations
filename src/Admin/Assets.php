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
	 * Select2 version bundled with this library.
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
	 * Register the Select2 library assets.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	private function register_select2(): void {
		$assets_url = $this->get_assets_url();

		wp_register_style(
			self::get_select2_css_handle(),
			$assets_url . 'css/select2.min.css',
			[],
			self::SELECT2_VERSION
		);

		wp_register_script(
			self::get_select2_js_handle(),
			$assets_url . 'js/select2.min.js',
			[ 'jquery' ],
			self::SELECT2_VERSION,
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
		$assets_url = Config::get_assets_url();

		if ( $assets_url ) {
			return $assets_url;
		}

		// Get the path to the assets directory.
		$assets_path = dirname( __DIR__, 2 ) . '/assets/';

		// Convert to URL.
		$assets_url = str_replace(
			wp_normalize_path( ABSPATH ),
			trailingslashit( site_url() ),
			wp_normalize_path( $assets_path )
		);

		Config::set_assets_url( $assets_url );

		return $assets_url;
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
		if ( wp_script_is( self::get_js_handle(), 'registered' ) ) {
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
			[ 'wp-dom-ready', 'wp-api-fetch', 'jquery', self::get_select2_js_handle() ],
			Provider::VERSION,
			true
		);
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
		if ( wp_script_is( self::get_js_handle(), 'enqueued' ) ) {
			return;
		}

		if ( ! wp_script_is( self::get_js_handle(), 'registered' ) ) {
			$this->register_assets();
		}

		wp_enqueue_style( self::get_css_handle() );
		wp_enqueue_script( self::get_js_handle() );
	}
}

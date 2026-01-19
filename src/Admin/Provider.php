<?php
/**
 * Admin Service Provider.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Admin
 */

declare(strict_types=1);

namespace StellarWP\Migrations\Admin;

use StellarWP\Migrations\Config;

/**
 * Admin Service Provider.
 *
 * Registers hidden admin pages for the migrations UI.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Admin
 */
class Provider {
	/**
	 * Whether the provider has been registered.
	 *
	 * @since 0.0.1
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Register the admin provider.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function register(): void {
		if ( self::$registered ) {
			return;
		}

		self::$registered = true;

		add_action( 'admin_menu', [ $this, 'register_hidden_pages' ] );
	}

	/**
	 * Register hidden admin pages.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function register_hidden_pages(): void {
		add_submenu_page(
			'',
			__( 'Migration Details', 'stellarwp-migrations' ),
			__( 'Migration Details', 'stellarwp-migrations' ),
			'manage_options',
			self::get_single_page_slug(),
			[ $this, 'render_single_page' ]
		);
	}

	/**
	 * Render the single migration page.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function render_single_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only, no state change.
		$migration_id = isset( $_GET['migration_id'] ) && is_string( $_GET['migration_id'] ) ? sanitize_text_field( wp_unslash( $_GET['migration_id'] ) ) : '';

		if ( empty( $migration_id ) ) {
			Config::get_template_engine()->template(
				'single-not-found',
				[ 'migration_id' => '' ]
			);
			return;
		}

		Config::get_container()->get( UI::class )->render_single( $migration_id );
	}

	/**
	 * Get the single page slug.
	 *
	 * @since 0.0.1
	 *
	 * @return string The page slug.
	 */
	public static function get_single_page_slug(): string {
		return 'stellarwp-migrations-' . Config::get_hook_prefix() . '-single';
	}

	/**
	 * Get the URL for a single migration page.
	 *
	 * @since 0.0.1
	 *
	 * @param string $migration_id The migration ID.
	 *
	 * @return string The URL to the single migration page.
	 */
	public static function get_single_url( string $migration_id ): string {
		return admin_url(
			add_query_arg(
				[ 'migration_id' => rawurlencode( $migration_id ) ],
				'admin.php?page=' . self::get_single_page_slug()
			)
		);
	}

	/**
	 * Reset registration status. Primarily for testing.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$registered = false;
	}
}

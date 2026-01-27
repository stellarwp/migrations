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

		add_action( 'admin_menu', [ $this, 'register_hidden_page' ] );
		add_filter( 'set-screen-option', [ $this, 'save_screen_option' ], 10, 3 );
	}

	/**
	 * Register hidden admin pages.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function register_hidden_page(): void {
		$single_page_hook = add_submenu_page(
			'',
			__( 'Migration Details', 'stellarwp-migrations' ),
			__( 'Migration Details', 'stellarwp-migrations' ),
			'manage_options',
			self::get_single_page_slug(),
			[ $this, 'render_single_page' ]
		);

		if ( $single_page_hook && is_string( $single_page_hook ) ) {
			add_action( 'load-' . $single_page_hook, [ $this, 'add_screen_options' ] );
			add_action( 'load-' . $single_page_hook, [ $this, 'set_page_title' ] );
		}
	}

	/**
	 * Add screen options for the single migration page.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function add_screen_options(): void {
		add_screen_option(
			'per_page',
			[
				'label'   => __( 'Logs per page', 'stellarwp-migrations' ),
				'default' => 10,
				'option'  => Assets::get_logs_per_page_option(),
			]
		);
	}

	/**
	 * Set the page title for the single migration page.
	 *
	 * Prevents WordPress from passing null to strip_tags() when rendering
	 * the admin header for hidden submenu pages.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function set_page_title(): void {
			global $title;

		if ( empty( $title ) ) {
			$title = __( 'Migration Details', 'stellarwp-migrations' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- It's intended to override the global title.
		}
	}

	/**
	 * Save the screen option value.
	 *
	 * @since 0.0.1
	 *
	 * @param mixed  $status The current status (false by default).
	 * @param string $option The option name.
	 * @param mixed  $value  The option value.
	 *
	 * @return mixed The value to save, or false to not save.
	 */
	public function save_screen_option( $status, string $option, $value ) {
		if ( Assets::get_logs_per_page_option() === $option && is_numeric( $value ) ) {
			return max( 1, (int) $value );
		}

		return $status;
	}

	/**
	 * Render the single migration page.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function render_single_page(): void {
		$raw_migration_id = filter_input( INPUT_GET, 'migration_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$migration_id     = sanitize_key( is_string( $raw_migration_id ) ? $raw_migration_id : '' );

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

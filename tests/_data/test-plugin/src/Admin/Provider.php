<?php
/**
 * Admin Provider for the Test Plugin.
 *
 * @since 0.0.1
 *
 * @package Test_Plugin\Admin
 */

namespace Test_Plugin\Admin;

use StellarWP\Shepherd\Abstracts\Provider_Abstract;
use StellarWP\Migrations\Admin\UI;
use StellarWP\Migrations\Admin\Assets;

/**
 * Registers admin pages for testing the migrations UI.
 *
 * @since 0.0.1
 */
class Provider extends Provider_Abstract {
	/**
	 * Register the admin page.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_page' ] );
	}

	/**
	 * Add the migrations admin page.
	 *
	 * @return void
	 */
	public function add_admin_page(): void {
		add_menu_page(
			__( 'Migrations', 'test-plugin' ),
			__( 'Migrations', 'test-plugin' ),
			'manage_options',
			'test-plugin-migrations',
			[ $this, 'render_page' ],
			'dashicons-database',
			80
		);
	}

	/**
	 * Render the migrations admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		// Enqueue the migrations admin assets.
		$this->container->get( Assets::class )->enqueue_assets();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Migrations', 'test-plugin' ); ?></h1>
			<p><?php esc_html_e( 'Manage your database migrations below.', 'test-plugin' ); ?></p>

			<?php
			// Render the migrations list UI.
			$this->container->get( UI::class )->render_list();
			?>
		</div>
		<?php
	}
}

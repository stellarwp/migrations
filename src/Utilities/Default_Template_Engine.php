<?php
/**
 * Default Template Engine.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Utilities
 */

declare(strict_types=1);

namespace StellarWP\Migrations\Utilities;

use StellarWP\Migrations\Contracts\Template_Engine;

/**
 * Default Template Engine.
 *
 * A simple PHP-based template engine that loads template files from the views directory.
 * Consumers can use this implementation or provide their own that implements the
 * Template_Engine interface.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Utilities
 */
class Default_Template_Engine implements Template_Engine {
	/**
	 * Render a template.
	 *
	 * @since 0.0.1
	 *
	 * @param string              $name    Template name (e.g., 'list', 'components/progress-bar').
	 * @param array<string,mixed> $context Variables to pass to the template.
	 * @param bool                $output  Whether to echo or return the output.
	 *
	 * @return string|void The rendered template if $echo is false, void otherwise.
	 */
	public function template( string $name, array $context = [], bool $output = true ) {
		$file = $this->get_template_path( $name );

		if ( ! file_exists( $file ) ) {
			if ( $output ) {
				return;
			}
			return '';
		}

		// Extract context variables into the local scope.
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Intentional for template variables.
		extract( $context );

		if ( $output ) {
			include $file;
			return;
		}

		ob_start();
		include $file;
		return ob_get_clean() ?: '';
	}

	/**
	 * Get the full path to a template file.
	 *
	 * @since 0.0.1
	 *
	 * @param string $name Template name.
	 *
	 * @return string Full path to the template file.
	 */
	private function get_template_path( string $name ): string {
		return dirname( __DIR__ ) . '/views/' . $name . '.php';
	}
}

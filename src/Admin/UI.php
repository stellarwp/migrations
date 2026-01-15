<?php
/**
 * Admin UI.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Admin
 */

declare(strict_types=1);

namespace StellarWP\Migrations\Admin;

use StellarWP\Migrations\Config;
use StellarWP\Migrations\Registry;
use StellarWP\Migrations\Contracts\Migration;
use StellarWP\Migrations\REST\Provider as REST_Provider;

/**
 * Admin UI.
 *
 * Provides methods to render the migrations admin interface.
 * Consumers call these methods to display the UI wherever they want.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Admin
 */
class UI {
	/**
	 * Render the migrations list page.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function render_list(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$container = Config::get_container();

		$container->get( Assets::class )->enqueue_assets();

		$filters = $this->parse_filters();

		$registry = $container->get( Registry::class );

		$all_migrations = $registry->all();
		$all_tags       = array_map( static fn( Migration $migration ) => $migration->get_tags(), $all_migrations );
		$all_tags       = array_unique( array_merge( ...array_values( $all_tags ) ) );

		sort( $all_tags );

		$filtered_migrations = $this->get_filtered_migrations( $filters );

		Config::get_template_engine()->template(
			'list',
			[
				'migrations'    => $filtered_migrations,
				'all_tags'      => $all_tags,
				'filters'       => $filters,
				'rest_base_url' => rest_url( REST_Provider::get_namespace() ),
			]
		);
	}

	/**
	 * Parse filters from request or provided array.
	 *
	 * @since 0.0.1
	 *
	 * @return array<string,mixed> Parsed filters.
	 */
	private function parse_filters(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce not required for display filters.
		$tags = [];

		if ( isset( $_GET['tags'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitization happens in array_map.
			$raw_tags = $_GET['tags'];
			if ( is_array( $raw_tags ) ) {
				$tags = array_map( static fn( $tag ): string => is_string( $tag ) ? sanitize_text_field( $tag ) : '', $raw_tags );
				$tags = array_filter( $tags );
			} elseif ( is_string( $raw_tags ) && strstr( $raw_tags, ',' ) ) {
				$tags = array_map( 'sanitize_text_field', explode( ',', $raw_tags ) );
			} elseif ( is_string( $raw_tags ) ) {
				$tags = [ sanitize_text_field( $raw_tags ) ];
			} else {
				$tags = [];
			}
		}

		return [
			'tags'                => $tags,
			'show_completed'      => ! empty( $_GET['show_completed'] ),
			'show_non_applicable' => ! empty( $_GET['show_non_applicable'] ),
		];
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Apply filters to migrations.
	 *
	 * @since 0.0.1
	 *
	 * @param array<string,mixed> $filters Filters to apply.
	 *
	 * @return array<string,Migration> Filtered migrations.
	 */
	private function get_filtered_migrations( array $filters ): array {
		$show_completed      = ! empty( $filters['show_completed'] );
		$show_non_applicable = ! empty( $filters['show_non_applicable'] );
		$filter_tags         = ! empty( $filters['tags'] ) ? (array) $filters['tags'] : [];

		$registry = Config::get_container()->get( Registry::class );

		return $registry->filter(
			static function ( Migration $migration ) use ( $show_completed, $show_non_applicable, $filter_tags ): bool {
				if ( ! $show_non_applicable && ! $migration->is_applicable() ) {
					return false;
				}

				if ( ! $show_completed && $migration->is_up_done() ) {
					return false;
				}

				if ( ! empty( $filter_tags ) ) {
					if ( empty( array_intersect( $filter_tags, $migration->get_tags() ) ) ) {
						return false;
					}
				}

				return true;
			}
		)->all();
	}
}

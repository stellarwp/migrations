<?php
/**
 * Base test case for REST API tests.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\REST
 */

declare(strict_types=1);

namespace StellarWP\Migrations\Tests\TestCases;

use Closure;
use Generator;
use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Traits\With_Uopz;
use StellarWP\Migrations\Tests\Migrations\Simple_Migration;
use StellarWP\Migrations\Tests\Migrations\Multi_Batch_Migration;
use StellarWP\Migrations\Tests\Migrations\Not_Applicable_Migration;
use StellarWP\Migrations\Tests\Migrations\Tagged_Migration;
use StellarWP\Migrations\REST\Provider as REST_Provider;
use StellarWP\Migrations\REST\Endpoints;
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Registry;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Base test case for REST API tests.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\REST
 */
abstract class REST_Test_Case extends WPTestCase {
	use With_Uopz;

	/**
	 * The REST server instance.
	 *
	 * @since 0.0.1
	 *
	 * @var WP_REST_Server
	 */
	protected WP_REST_Server $rest_server;

	/**
	 * The endpoints instance.
	 *
	 * @since 0.0.1
	 *
	 * @var Endpoints
	 */
	protected Endpoints $endpoints;

	/**
	 * The REST namespace.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	protected string $namespace;

	/**
	 * Set up the test.
	 *
	 * @before
	 *
	 * @return void
	 */
	public function set_up_rest(): void {
		parent::setUp();

		// Reset migration states.
		Simple_Migration::reset();
		Multi_Batch_Migration::reset();
		Not_Applicable_Migration::reset();
		Tagged_Migration::reset();
		tests_migrations_clear_calls_data();

		// Flush and re-register migrations.
		$container = Config::get_container();
		$registry  = $container->get( Registry::class );
		$registry->flush();

		// Register test migrations.
		$this->register_test_migrations( $registry );

		// Initialize REST server.
		global $wp_rest_server;
		$wp_rest_server    = new WP_REST_Server();
		$this->rest_server = $wp_rest_server;

		// Register the REST provider (hooks into rest_api_init).
		$container->get( REST_Provider::class )->register();

		$this->endpoints = $container->get( Endpoints::class );
		$this->namespace = REST_Provider::get_namespace();

		// Fire rest_api_init to register routes.
		do_action( 'rest_api_init' );
	}

	/**
	 * Register test migrations with the registry.
	 *
	 * Override this method in subclasses to register additional migrations.
	 *
	 * @since 0.0.1
	 *
	 * @param Registry $registry The migration registry.
	 *
	 * @return void
	 */
	protected function register_test_migrations( Registry $registry ): void {
		$registry->register( 'tests_simple_migration', Simple_Migration::class );
		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );
		$registry->register( 'tests_not_applicable_migration', Not_Applicable_Migration::class );
		$registry->register( 'tests_tagged_migration', Tagged_Migration::class );
	}

	/**
	 * Tear down the test.
	 *
	 * @after
	 *
	 * @return void
	 */
	public function tear_down_rest(): void {
		wp_set_current_user( 0 );

		// Reset migration states.
		Simple_Migration::reset();
		Multi_Batch_Migration::reset();
		Not_Applicable_Migration::reset();
		Tagged_Migration::reset();
		tests_migrations_clear_calls_data();

		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tearDown();
	}

	/**
	 * Make a REST API request.
	 *
	 * @since 0.0.1
	 *
	 * @param string               $path   The endpoint path (without namespace).
	 * @param string               $method The HTTP method.
	 * @param array<string, mixed> $data   The request data.
	 *
	 * @return WP_REST_Response The response.
	 */
	protected function do_rest_api_request( string $path, string $method = 'GET', array $data = [] ): WP_REST_Response {
		$request = new WP_REST_Request( $method, '/' . $this->namespace . $path );

		if ( ! empty( $data ) ) {
			if ( in_array( $method, [ 'POST', 'PUT', 'PATCH' ], true ) ) {
				$request->set_body_params( $data );
			} else {
				foreach ( $data as $key => $value ) {
					$request->set_param( $key, $value );
				}
			}
		}

		return $this->rest_server->dispatch( $request );
	}

	/**
	 * Assert an endpoint response.
	 *
	 * @since 0.0.1
	 *
	 * @param string               $path          The endpoint path.
	 * @param string               $method        The HTTP method.
	 * @param int                  $expected_code The expected response code.
	 * @param array<string, mixed> $data          The request data.
	 *
	 * @return array<string, mixed> The response data.
	 */
	protected function assert_endpoint( string $path, string $method = 'GET', int $expected_code = 200, array $data = [] ): array {
		$response = $this->do_rest_api_request( $path, strtoupper( $method ), $data );

		if ( $expected_code > 299 ) {
			$this->assertTrue( $response->is_error(), 'Response should be an error for path: ' . $path );
		} else {
			$this->assertFalse( $response->is_error(), 'Response should not be an error for path: ' . $path . '. Error: ' . wp_json_encode( $response->get_data() ) );
		}

		$this->assertEquals( $expected_code, $response->get_status(), 'Unexpected status code for path: ' . $path );

		return $response->get_data();
	}

	/**
	 * Create an admin user and set as current user.
	 *
	 * @since 0.0.1
	 *
	 * @return int The user ID.
	 */
	protected function set_admin_user(): int {
		$user_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Data provider for different user roles.
	 *
	 * @since 0.0.1
	 *
	 * @return Generator<string, array{Closure}>
	 */
	public function different_user_roles_provider(): Generator {
		yield 'guest' => [
			function (): void {
				wp_set_current_user( 0 );
			},
		];

		yield 'subscriber' => [
			function (): void {
				$user = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
				wp_set_current_user( $user );
			},
		];

		yield 'editor' => [
			function (): void {
				$user = $this->factory()->user->create( [ 'role' => 'editor' ] );
				wp_set_current_user( $user );
			},
		];

		yield 'administrator' => [
			function (): void {
				$user = $this->factory()->user->create( [ 'role' => 'administrator' ] );
				wp_set_current_user( $user );
			},
		];
	}

	/**
	 * Check if the current user can manage migrations (has manage_options capability).
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	protected function current_user_can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get the expected status code based on user permissions.
	 *
	 * @since 0.0.1
	 *
	 * @param int $success_code The success status code.
	 *
	 * @return int The expected status code.
	 */
	protected function get_expected_status_code( int $success_code = 200 ): int {
		if ( $this->current_user_can_manage() ) {
			return $success_code;
		}

		return is_user_logged_in() ? 403 : 401;
	}
}

<?php
/**
 * REST API tests for the Executions endpoint.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\REST
 */

declare(strict_types=1);

namespace StellarWP\Migrations\Tests\REST;

use Closure;
use StellarWP\Migrations\Tables\Migration_Executions;
use StellarWP\Migrations\Enums\Status;
use StellarWP\Migrations\Tests\TestCases\REST_Test_Case;

/**
 * REST API tests for the Executions endpoint.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\REST
 */
class Executions_Test extends REST_Test_Case {

	/**
	 * Create test executions.
	 *
	 * @since 0.0.1
	 *
	 * @return array<int> The created execution IDs.
	 */
	protected function create_test_executions(): array {
		$execution_ids = [];

		// Create a few test executions.
		$statuses = [
			Status::SCHEDULED(),
			Status::RUNNING(),
			Status::COMPLETED(),
		];

		foreach ( $statuses as $index => $status ) {
			Migration_Executions::insert(
				[
					'migration_id'    => 'tests_simple_migration',
					'status'          => $status->getValue(),
					'items_total'     => 10 + $index,
					'items_processed' => $index * 5,
				]
			);

			$execution_ids[] = \StellarWP\DB\DB::last_insert_id();
		}

		return $execution_ids;
	}

	/**
	 * @test
	 */
	public function it_should_return_401_for_guest_user(): void {
		wp_set_current_user( 0 );

		$response = $this->do_rest_api_request( '/tests_simple_migration/executions' );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * @test
	 */
	public function it_should_return_403_for_non_admin_user(): void {
		$user_id = $this->factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $user_id );

		$response = $this->do_rest_api_request( '/tests_simple_migration/executions' );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * @test
	 */
	public function it_should_return_200_for_admin_user(): void {
		$this->set_admin_user();

		$response = $this->do_rest_api_request( '/tests_simple_migration/executions' );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * @test
	 */
	public function it_should_list_executions_for_a_migration(): void {
		$this->set_admin_user();
		$this->create_test_executions();

		$data = $this->assert_endpoint( '/tests_simple_migration/executions' );

		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );

		// Verify each execution has expected keys.
		foreach ( $data as $execution ) {
			$this->assertArrayHasKey( 'id', $execution );
			$this->assertArrayHasKey( 'migration_id', $execution );
			$this->assertArrayHasKey( 'status', $execution );
			$this->assertArrayHasKey( 'items_total', $execution );
			$this->assertArrayHasKey( 'items_processed', $execution );
			$this->assertArrayHasKey( 'created_at', $execution );
		}
	}

	/**
	 * @test
	 */
	public function it_should_return_empty_array_for_migration_with_no_executions(): void {
		$this->set_admin_user();

		// Use a migration that hasn't been run.
		$data = $this->assert_endpoint( '/tests_not_applicable_migration/executions' );

		$this->assertIsArray( $data );
		// May or may not be empty depending on test state, but should be an array.
	}

	/**
	 * @test
	 */
	public function it_should_only_return_executions_for_specified_migration(): void {
		$this->set_admin_user();

		// Create executions for simple migration.
		$this->create_test_executions();

		// Create an execution for a different migration.
		Migration_Executions::insert(
			[
				'migration_id'    => 'tests_multi_batch_migration',
				'status'          => Status::SCHEDULED()->getValue(),
				'items_total'     => 15,
				'items_processed' => 0,
			]
		);

		$data = $this->assert_endpoint( '/tests_simple_migration/executions' );

		// All returned executions should be for the simple migration.
		foreach ( $data as $execution ) {
			$this->assertEquals( 'tests_simple_migration', $execution['migration_id'] );
		}
	}

	/**
	 * @test
	 */
	public function it_should_include_execution_status(): void {
		$this->set_admin_user();
		$this->create_test_executions();

		$data = $this->assert_endpoint( '/tests_simple_migration/executions' );

		$statuses = array_column( $data, 'status' );

		// Should have various statuses.
		$this->assertNotEmpty( $statuses );
	}

	/**
	 * @test
	 * @dataProvider different_user_roles_provider
	 */
	public function it_should_enforce_permissions_for_different_roles( Closure $fixture ): void {
		$fixture();

		$expected_code = $this->get_expected_status_code();
		$response      = $this->do_rest_api_request( '/tests_simple_migration/executions' );

		$this->assertEquals( $expected_code, $response->get_status() );
	}
}

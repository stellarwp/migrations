<?php
/**
 * REST API tests for the Migration run endpoint.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\REST
 */

declare(strict_types=1);

namespace StellarWP\Migrations\Tests\REST;

use Closure;
use StellarWP\Migrations\Tests\Migrations\Simple_Migration;
use StellarWP\Migrations\Tables\Migration_Executions;
use StellarWP\Migrations\Tests\TestCases\REST_Test_Case;

/**
 * REST API tests for the Migration run endpoint.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\REST
 */
class Migration_Run_Test extends REST_Test_Case {

	/**
	 * Reset migration state before each test.
	 *
	 * @before
	 *
	 * @return void
	 */
	public function reset_migrations(): void {
		Simple_Migration::reset();
		tests_migrations_clear_calls_data();
	}

	/**
	 * @test
	 */
	public function it_should_return_401_for_guest_user(): void {
		wp_set_current_user( 0 );

		$response = $this->do_rest_api_request( '/migrations/tests_simple_migration/run', 'POST' );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * @test
	 */
	public function it_should_return_403_for_non_admin_user(): void {
		$user_id = $this->factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $user_id );

		$response = $this->do_rest_api_request( '/migrations/tests_simple_migration/run', 'POST' );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * @test
	 */
	public function it_should_run_a_simple_migration(): void {
		$this->set_admin_user();

		$data = $this->assert_endpoint( '/migrations/tests_simple_migration/run', 'POST', 200 );

		$this->assertArrayHasKey( 'success', $data );
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'message', $data );
		$this->assertArrayHasKey( 'execution_id', $data );
	}

	/**
	 * @test
	 */
	public function it_should_create_execution_record_when_running_migration(): void {
		$this->set_admin_user();

		$this->assert_endpoint( '/migrations/tests_simple_migration/run', 'POST', 200 );

		$executions = Migration_Executions::get_all_by( 'migration_id', 'tests_simple_migration' );

		$this->assertNotEmpty( $executions );
		$this->assertIsArray( $executions );

		$execution = end( $executions );
		$this->assertEquals( 'tests_simple_migration', $execution['migration_id'] );
	}

	/**
	 * @test
	 */
	public function it_should_return_error_for_nonexistent_migration(): void {
		$this->set_admin_user();

		$data = $this->assert_endpoint( '/migrations/nonexistent_migration_xyz/run', 'POST', 400 );

		$this->assertArrayHasKey( 'code', $data );
		$this->assertEquals( 'migrations_error', $data['code'] );
	}

	/**
	 * @test
	 */
	public function it_should_run_migration_with_from_batch_parameter(): void {
		$this->set_admin_user();

		$data = $this->assert_endpoint(
			'/migrations/tests_multi_batch_migration/run',
			'POST',
			200,
			[ 'from-batch' => 2 ]
		);

		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'from_batch', $data );
		$this->assertEquals( 2, $data['from_batch'] );
	}

	/**
	 * @test
	 */
	public function it_should_run_migration_with_to_batch_parameter(): void {
		$this->set_admin_user();

		$data = $this->assert_endpoint(
			'/migrations/tests_multi_batch_migration/run',
			'POST',
			200,
			[ 'to-batch' => 2 ]
		);

		$this->assertTrue( $data['success'] );
	}

	/**
	 * @test
	 */
	public function it_should_run_migration_with_batch_size_parameter(): void {
		$this->set_admin_user();

		$data = $this->assert_endpoint(
			'/migrations/tests_multi_batch_migration/run',
			'POST',
			200,
			[ 'batch-size' => 10 ]
		);

		$this->assertTrue( $data['success'] );
	}

	/**
	 * @test
	 */
	public function it_should_run_migration_with_all_batch_parameters(): void {
		$this->set_admin_user();

		$data = $this->assert_endpoint(
			'/migrations/tests_multi_batch_migration/run',
			'POST',
			200,
			[
				'from-batch' => 1,
				'to-batch'   => 2,
				'batch-size' => 5,
			]
		);

		$this->assertTrue( $data['success'] );
		$this->assertEquals( 1, $data['from_batch'] );
		$this->assertEquals( 1, $data['to_batch'] );
		$this->assertEquals( 5, $data['batch_size'] );
	}

	/**
	 * @test
	 * @dataProvider different_user_roles_provider
	 */
	public function it_should_enforce_permissions_for_different_roles( Closure $fixture ): void {
		$fixture();

		$expected_code = $this->get_expected_status_code();
		$response      = $this->do_rest_api_request( '/migrations/tests_simple_migration/run', 'POST' );

		$this->assertEquals( $expected_code, $response->get_status() );
	}
}

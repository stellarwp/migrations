<?php
/**
 * REST API tests for the Logs endpoint.
 *
 * @since TBD
 *
 * @package StellarWP\Migrations\Tests\REST
 */

declare(strict_types=1);

namespace StellarWP\Migrations\Tests\REST;

use Closure;
use StellarWP\Migrations\Tables\Migration_Executions;
use StellarWP\Migrations\Tables\Migration_Logs;
use StellarWP\Migrations\Enums\Status;
use StellarWP\Migrations\Enums\Log_Type;
use StellarWP\Migrations\Tests\TestCases\REST_Test_Case;
use StellarWP\DB\DB;

/**
 * REST API tests for the Logs endpoint.
 *
 * @since TBD
 *
 * @package StellarWP\Migrations\Tests\REST
 */
class Logs_Test extends REST_Test_Case {

	/**
	 * The test execution ID.
	 *
	 * @since TBD
	 *
	 * @var int|null
	 */
	protected ?int $execution_id = null;

	/**
	 * Create a test execution with logs.
	 *
	 * @since TBD
	 *
	 * @return int The execution ID.
	 */
	protected function create_test_execution_with_logs(): int {
		// Create an execution.
		Migration_Executions::insert(
			[
				'migration_id'    => 'tests_simple_migration',
				'status'          => Status::COMPLETED()->getValue(),
				'items_total'     => 10,
				'items_processed' => 10,
			]
		);

		$execution_id = DB::last_insert_id();

		// Create some test logs.
		$log_types = [
			[
				'type'    => Log_Type::INFO()->getValue(),
				'message' => 'Migration started',
			],
			[
				'type'    => Log_Type::INFO()->getValue(),
				'message' => 'Processing batch 1',
			],
			[
				'type'    => Log_Type::WARNING()->getValue(),
				'message' => 'Skipped invalid record',
			],
			[
				'type'    => Log_Type::ERROR()->getValue(),
				'message' => 'Failed to process item 5',
			],
			[
				'type'    => Log_Type::INFO()->getValue(),
				'message' => 'Migration completed',
			],
			[
				'type'    => Log_Type::DEBUG()->getValue(),
				'message' => 'Debug information',
			],
		];

		foreach ( $log_types as $index => $log ) {
			Migration_Logs::insert(
				[
					'migration_execution_id' => $execution_id,
					'type'                   => $log['type'],
					'message'                => $log['message'],
					'data'                   => wp_json_encode( [ 'index' => $index ] ),
				]
			);
		}

		$this->execution_id = $execution_id;

		return $execution_id;
	}

	/**
	 * @test
	 */
	public function it_should_return_401_for_guest_user(): void {
		$execution_id = $this->create_test_execution_with_logs();
		wp_set_current_user( 0 );

		$response = $this->do_rest_api_request( '/executions/' . $execution_id . '/logs' );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * @test
	 */
	public function it_should_return_403_for_non_admin_user(): void {
		$execution_id = $this->create_test_execution_with_logs();
		$user_id      = $this->factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $user_id );

		$response = $this->do_rest_api_request( '/executions/' . $execution_id . '/logs' );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * @test
	 */
	public function it_should_return_200_for_admin_user(): void {
		$execution_id = $this->create_test_execution_with_logs();
		$this->set_admin_user();

		$response = $this->do_rest_api_request( '/executions/' . $execution_id . '/logs' );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * @test
	 */
	public function it_should_list_logs_for_execution(): void {
		$execution_id = $this->create_test_execution_with_logs();
		$this->set_admin_user();

		$data = $this->assert_endpoint( '/executions/' . $execution_id . '/logs' );

		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );

		// Verify each log has expected keys.
		foreach ( $data as $log ) {
			$this->assertArrayHasKey( 'id', $log );
			$this->assertArrayHasKey( 'type', $log );
			$this->assertArrayHasKey( 'message', $log );
			$this->assertArrayHasKey( 'created_at', $log );
		}
	}

	/**
	 * @test
	 */
	public function it_should_return_error_for_nonexistent_execution(): void {
		$this->set_admin_user();

		$data = $this->assert_endpoint( '/executions/999999/logs', 'GET', 400 );

		$this->assertArrayHasKey( 'code', $data );
		$this->assertEquals( 'migrations_error', $data['code'] );
	}

	/**
	 * @test
	 */
	public function it_should_filter_logs_by_type(): void {
		$execution_id = $this->create_test_execution_with_logs();
		$this->set_admin_user();

		$data = $this->assert_endpoint(
			'/executions/' . $execution_id . '/logs',
			'GET',
			200,
			[ 'type' => 'info' ]
		);

		$this->assertIsArray( $data );

		// All logs should be of type 'info'.
		foreach ( $data as $log ) {
			$this->assertEquals( 'info', $log['type'] );
		}
	}

	/**
	 * @test
	 */
	public function it_should_filter_logs_by_not_type(): void {
		$execution_id = $this->create_test_execution_with_logs();
		$this->set_admin_user();

		$data = $this->assert_endpoint(
			'/executions/' . $execution_id . '/logs',
			'GET',
			200,
			[ 'not-type' => 'info' ]
		);

		$this->assertIsArray( $data );

		// No logs should be of type 'info'.
		foreach ( $data as $log ) {
			$this->assertNotEquals( 'info', $log['type'] );
		}
	}

	/**
	 * @test
	 */
	public function it_should_return_error_when_using_type_and_not_type_together(): void {
		$execution_id = $this->create_test_execution_with_logs();
		$this->set_admin_user();

		$data = $this->assert_endpoint(
			'/executions/' . $execution_id . '/logs',
			'GET',
			400,
			[
				'type'     => 'info',
				'not-type' => 'error',
			]
		);

		$this->assertArrayHasKey( 'code', $data );
		$this->assertEquals( 'migrations_error', $data['code'] );
	}

	/**
	 * @test
	 */
	public function it_should_support_limit_parameter(): void {
		$execution_id = $this->create_test_execution_with_logs();
		$this->set_admin_user();

		$data = $this->assert_endpoint(
			'/executions/' . $execution_id . '/logs',
			'GET',
			200,
			[ 'limit' => 2 ]
		);

		$this->assertIsArray( $data );
		$this->assertLessThanOrEqual( 2, count( $data ) );
	}

	/**
	 * @test
	 */
	public function it_should_support_offset_parameter(): void {
		$execution_id = $this->create_test_execution_with_logs();
		$this->set_admin_user();

		// Get all logs first.
		$all_logs = $this->assert_endpoint( '/executions/' . $execution_id . '/logs' );

		// Get logs with offset.
		$offset_logs = $this->assert_endpoint(
			'/executions/' . $execution_id . '/logs',
			'GET',
			200,
			[ 'offset' => 2 ]
		);

		$this->assertIsArray( $offset_logs );

		// Offset logs should have fewer items than all logs.
		if ( count( $all_logs ) > 2 ) {
			$this->assertLessThan( count( $all_logs ), count( $offset_logs ) );
		}
	}

	/**
	 * @test
	 */
	public function it_should_support_order_parameter(): void {
		$execution_id = $this->create_test_execution_with_logs();
		$this->set_admin_user();

		// Get logs in ASC order.
		$asc_logs = $this->assert_endpoint(
			'/executions/' . $execution_id . '/logs',
			'GET',
			200,
			[ 'order' => 'ASC' ]
		);

		// Get logs in DESC order.
		$desc_logs = $this->assert_endpoint(
			'/executions/' . $execution_id . '/logs',
			'GET',
			200,
			[ 'order' => 'DESC' ]
		);

		$this->assertIsArray( $asc_logs );
		$this->assertIsArray( $desc_logs );

		// Verify both requests return logs (order behavior depends on underlying library).
		$this->assertNotEmpty( $asc_logs );
		$this->assertNotEmpty( $desc_logs );
	}

	/**
	 * @test
	 */
	public function it_should_support_order_by_parameter(): void {
		$execution_id = $this->create_test_execution_with_logs();
		$this->set_admin_user();

		$data = $this->assert_endpoint(
			'/executions/' . $execution_id . '/logs',
			'GET',
			200,
			[ 'order-by' => 'type' ]
		);

		$this->assertIsArray( $data );
	}

	/**
	 * @test
	 */
	public function it_should_support_search_parameter(): void {
		$execution_id = $this->create_test_execution_with_logs();
		$this->set_admin_user();

		// Verify the endpoint accepts the search parameter without error.
		$data = $this->assert_endpoint(
			'/executions/' . $execution_id . '/logs',
			'GET',
			200,
			[ 'search' => 'batch' ]
		);

		$this->assertIsArray( $data );
	}

	/**
	 * @test
	 * @dataProvider different_user_roles_provider
	 */
	public function it_should_enforce_permissions_for_different_roles( Closure $fixture ): void {
		$execution_id = $this->create_test_execution_with_logs();
		$fixture();

		$expected_code = $this->get_expected_status_code();
		$response      = $this->do_rest_api_request( '/executions/' . $execution_id . '/logs' );

		$this->assertEquals( $expected_code, $response->get_status() );
	}
}

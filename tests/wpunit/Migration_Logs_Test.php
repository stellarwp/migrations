<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Enums\Log_Type;
use StellarWP\Migrations\Tables\Migration_Logs;
use StellarWP\Migrations\Utilities\Logger;

class Migration_Logs_Test extends WPTestCase {
	/**
	 * @test
	 */
	public function it_should_insert_a_migration_log(): void {
		$result = Migration_Logs::insert(
			[
				'migration_execution_id' => 1,
				'type'                   => Log_Type::INFO()->getValue(),
				'message'                => 'Test log message',
				'data'                   => [
					'test' => 'value',
				],
			]
		);

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );
	}

	/**
	 * @test
	 */
	public function it_should_retrieve_log_by_execution_id(): void {
		$execution_id = 123;

		Migration_Logs::insert(
			[
				'migration_execution_id' => $execution_id,
				'type'                   => Log_Type::INFO()->getValue(),
				'message'                => 'Test retrieval',
				'data'                   => [ 'foo' => 'bar' ],
			]
		);

		$log = Migration_Logs::get_first_by( 'migration_execution_id', $execution_id );

		$this->assertNotNull( $log );
		$this->assertEquals( $execution_id, $log['migration_execution_id'] );
		$this->assertEquals( Log_Type::INFO()->getValue(), $log['type'] );
		$this->assertEquals( 'Test retrieval', $log['message'] );
	}

	/**
	 * @test
	 */
	public function it_should_retrieve_all_logs_by_execution_id(): void {
		$execution_id = 456;

		Migration_Logs::insert(
			[
				'migration_execution_id' => $execution_id,
				'type'                   => Log_Type::INFO()->getValue(),
				'message'                => 'First log',
			]
		);

		Migration_Logs::insert(
			[
				'migration_execution_id' => $execution_id,
				'type'                   => Log_Type::WARNING()->getValue(),
				'message'                => 'Second log',
			]
		);

		Migration_Logs::insert(
			[
				'migration_execution_id' => $execution_id,
				'type'                   => Log_Type::ERROR()->getValue(),
				'message'                => 'Third log',
			]
		);

		$logs = Migration_Logs::get_all_by( 'migration_execution_id', $execution_id );

		$this->assertCount( 3, $logs );
	}

	/**
	 * @test
	 */
	public function it_should_store_json_data(): void {
		$execution_id = 789;
		$data         = [
			'args'    => [ 'up', 'test_migration', 1 ],
			'message' => 'Test message',
			'nested'  => [
				'foo' => 'bar',
			],
		];

		Migration_Logs::insert(
			[
				'migration_execution_id' => $execution_id,
				'type'                   => Log_Type::DEBUG()->getValue(),
				'message'                => 'Test JSON storage',
				'data'                   => $data,
			]
		);

		$log = Migration_Logs::get_first_by( 'migration_execution_id', $execution_id );

		$this->assertNotNull( $log );
		$this->assertIsArray( $log['data'] );
		$this->assertEquals( $data['args'], $log['data']['args'] );
		$this->assertEquals( $data['message'], $log['data']['message'] );
		$this->assertEquals( $data['nested']['foo'], $log['data']['nested']['foo'] );
	}

	/**
	 * @test
	 */
	public function it_should_have_correct_log_type_enum_values(): void {
		$this->assertEquals( 'info', Log_Type::INFO()->getValue() );
		$this->assertEquals( 'warning', Log_Type::WARNING()->getValue() );
		$this->assertEquals( 'error', Log_Type::ERROR()->getValue() );
		$this->assertEquals( 'debug', Log_Type::DEBUG()->getValue() );
	}

	/**
	 * @test
	 */
	public function it_should_record_created_at_timestamp(): void {
		$execution_id = 101112;

		$before = current_time( 'mysql' );

		Migration_Logs::insert(
			[
				'migration_execution_id' => $execution_id,
				'type'                   => Log_Type::INFO()->getValue(),
				'message'                => 'Timestamp test',
			]
		);

		$after = current_time( 'mysql' );

		$log = Migration_Logs::get_first_by( 'migration_execution_id', $execution_id );

		$this->assertNotNull( $log );
		$this->assertArrayHasKey( 'created_at', $log );

		// Convert created_at to string for comparison if it's a DateTime object.
		$created_at = $log['created_at'];
		if ( $created_at instanceof \DateTime ) {
			$created_at = $created_at->format( 'Y-m-d H:i:s' );
		}

		$this->assertGreaterThanOrEqual( $before, $created_at );
		$this->assertLessThanOrEqual( $after, $created_at );
	}

	/**
	 * @test
	 */
	public function it_should_return_null_for_non_existent_execution(): void {
		$log = Migration_Logs::get_first_by( 'migration_execution_id', 999999 );

		$this->assertNull( $log );
	}

	/**
	 * @test
	 */
	public function it_should_return_empty_array_for_non_existent_execution_all(): void {
		$logs = Migration_Logs::get_all_by( 'migration_execution_id', 999998 );

		$this->assertIsArray( $logs );
		$this->assertEmpty( $logs );
	}

	/**
	 * @test
	 */
	public function it_should_log_info_using_logger_utility(): void {
		$execution_id = 2001;
		$logger       = Logger::for_execution( $execution_id );

		// Arrange.

		// Act.
		$result = $logger->info( 'Info message test', [ 'key' => 'value' ] );

		// Assert.
		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$log = Migration_Logs::get_first_by( 'migration_execution_id', $execution_id );
		$this->assertNotNull( $log );
		$this->assertEquals( 'info', $log['type'] );
		$this->assertEquals( 'Info message test', $log['message'] );
		$this->assertEquals( [ 'key' => 'value' ], $log['data'] );
	}

	/**
	 * @test
	 */
	public function it_should_log_warning_using_logger_utility(): void {
		$execution_id = 2002;
		$logger       = Logger::for_execution( $execution_id );

		// Arrange.

		// Act.
		$result = $logger->warning( 'Warning message test' );

		// Assert.
		$this->assertIsInt( $result );

		$log = Migration_Logs::get_first_by( 'migration_execution_id', $execution_id );
		$this->assertNotNull( $log );
		$this->assertEquals( 'warning', $log['type'] );
		$this->assertEquals( 'Warning message test', $log['message'] );
	}

	/**
	 * @test
	 */
	public function it_should_log_error_using_logger_utility(): void {
		$execution_id = 2003;
		$logger       = Logger::for_execution( $execution_id );

		// Arrange.

		// Act.
		$result = $logger->error( 'Error message test', [ 'error_code' => 500 ] );

		// Assert.
		$this->assertIsInt( $result );

		$log = Migration_Logs::get_first_by( 'migration_execution_id', $execution_id );
		$this->assertNotNull( $log );
		$this->assertEquals( 'error', $log['type'] );
		$this->assertEquals( 'Error message test', $log['message'] );
		$this->assertArrayHasKey( 'error_code', $log['data'] );
		$this->assertEquals( 500, $log['data']['error_code'] );
	}

	/**
	 * @test
	 */
	public function it_should_log_debug_using_logger_utility(): void {
		$execution_id = 2004;
		$logger       = Logger::for_execution( $execution_id );

		// Arrange.

		// Act.
		$result = $logger->debug( 'Debug message test' );

		// Assert.
		$this->assertIsInt( $result );

		$log = Migration_Logs::get_first_by( 'migration_execution_id', $execution_id );
		$this->assertNotNull( $log );
		$this->assertEquals( 'debug', $log['type'] );
		$this->assertEquals( 'Debug message test', $log['message'] );
	}

	/**
	 * @test
	 */
	public function it_should_get_execution_id_from_logger(): void {
		$execution_id = 3001;
		$logger       = Logger::for_execution( $execution_id );

		// Arrange.

		// Act.

		// Assert.
		$this->assertEquals( $execution_id, $logger->get_execution_id() );
	}

	/**
	 * @test
	 */
	public function it_should_log_without_data(): void {
		$execution_id = 4001;
		$logger       = Logger::for_execution( $execution_id );

		// Arrange.

		// Act.
		$result = $logger->info( 'Message without data' );

		// Assert.
		$this->assertIsInt( $result );

		$log = Migration_Logs::get_first_by( 'migration_execution_id', $execution_id );
		$this->assertNotNull( $log );
		$this->assertEquals( 'Message without data', $log['message'] );
		$this->assertNull( $log['data'] );
	}
}

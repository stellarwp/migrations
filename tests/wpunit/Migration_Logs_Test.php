<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Enums\Log_Type;
use StellarWP\Migrations\Tables\Migration_Logs;
use StellarWP\Migrations\Tests\Traits\With_Uopz;
use StellarWP\Migrations\Utilities\Logger;

class Migration_Logs_Test extends WPTestCase {
	use With_Uopz;

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
		$logger       = new Logger( $execution_id );

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
		$logger       = new Logger( $execution_id );

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
		$logger       = new Logger( $execution_id );

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
		$logger       = new Logger( $execution_id );

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
		$logger       = new Logger( $execution_id );

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
		$logger       = new Logger( $execution_id );

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

	/**
	 * @test
	 */
	public function it_should_not_log_debug_messages_when_wp_debug_is_false(): void {
		// Arrange.
		$execution_id = 5001;

		// Explicitly set WP_DEBUG to false.
		$this->set_const_value( 'WP_DEBUG', false );

		// Act.
		$logger       = new Logger( $execution_id );
		$debug_result = $logger->debug( 'Debug message' );
		$info_result  = $logger->info( 'Info message' );

		// Assert.
		$this->assertFalse( $debug_result, 'Debug should not be logged when WP_DEBUG is false' );
		$this->assertIsInt( $info_result, 'Info should be logged when WP_DEBUG is false' );

		$logs = Migration_Logs::get_all_by( 'migration_execution_id', $execution_id );
		$this->assertCount( 1, $logs, 'Only info message should be logged' );
		$this->assertEquals( 'info', $logs[0]['type'] );
	}

	/**
	 * @test
	 */
	public function it_should_log_debug_messages_when_wp_debug_is_true(): void {
		// Arrange.
		$execution_id = 5002;

		// Explicitly set WP_DEBUG to true.
		$this->set_const_value( 'WP_DEBUG', true );

		// Act.
		$logger       = new Logger( $execution_id );
		$debug_result = $logger->debug( 'Debug message' );
		$info_result  = $logger->info( 'Info message' );

		// Assert.
		$this->assertIsInt( $debug_result, 'Debug should be logged when WP_DEBUG is true' );
		$this->assertIsInt( $info_result, 'Info should be logged when WP_DEBUG is true' );

		$logs = Migration_Logs::get_all_by( 'migration_execution_id', $execution_id );
		$this->assertCount( 2, $logs, 'Both debug and info messages should be logged' );

		$log_types = array_map( fn( $log ) => $log['type']->getValue(), $logs );
		$this->assertContains( 'debug', $log_types );
		$this->assertContains( 'info', $log_types );
	}

	/**
	 * @test
	 */
	public function it_should_respect_custom_min_log_level_from_filter(): void {
		// Arrange.
		$execution_id = 5005;
		$prefix       = Config::get_hook_prefix();

		add_filter(
			"stellarwp_migrations_{$prefix}_minimum_log_level",
			function () {
				return Log_Type::WARNING();
			}
		);

		$logger = new Logger( $execution_id );

		// Act.
		$debug_result   = $logger->debug( 'Debug message' );
		$info_result    = $logger->info( 'Info message' );
		$warning_result = $logger->warning( 'Warning message' );

		// Assert.
		$this->assertFalse( $debug_result, 'Debug should not be logged' );
		$this->assertFalse( $info_result, 'Info should not be logged' );
		$this->assertIsInt( $warning_result, 'Warning should be logged' );

		$logs = Migration_Logs::get_all_by( 'migration_execution_id', $execution_id );
		$this->assertCount( 1, $logs, 'Only warning should be logged' );
		$this->assertEquals( 'warning', $logs[0]['type'] );

		// Clean up.
		remove_all_filters( "stellarwp_migrations_{$prefix}_minimum_log_level" );
	}

	/**
	 * @test
	 */
	public function it_should_only_log_errors_when_min_level_is_error(): void {
		// Arrange.
		$execution_id = 5006;
		$prefix       = Config::get_hook_prefix();

		add_filter(
			"stellarwp_migrations_{$prefix}_minimum_log_level",
			function () {
				return Log_Type::ERROR();
			}
		);

		$logger = new Logger( $execution_id );

		// Act.
		$debug_result   = $logger->debug( 'Debug message' );
		$info_result    = $logger->info( 'Info message' );
		$warning_result = $logger->warning( 'Warning message' );
		$error_result   = $logger->error( 'Error message' );

		// Assert.
		$this->assertFalse( $debug_result );
		$this->assertFalse( $info_result );
		$this->assertFalse( $warning_result );
		$this->assertIsInt( $error_result );

		$logs = Migration_Logs::get_all_by( 'migration_execution_id', $execution_id );
		$this->assertCount( 1, $logs );
		$this->assertEquals( 'error', $logs[0]['type'] );

		// Clean up.
		remove_all_filters( "stellarwp_migrations_{$prefix}_minimum_log_level" );
	}

	/**
	 * @test
	 */
	public function it_should_log_all_levels_when_min_level_is_debug(): void {
		// Arrange.
		$execution_id = 5007;
		$prefix       = Config::get_hook_prefix();

		add_filter(
			"stellarwp_migrations_{$prefix}_minimum_log_level",
			function () {
				return Log_Type::DEBUG();
			}
		);

		$logger = new Logger( $execution_id );

		// Act.
		$debug_result   = $logger->debug( 'Debug message' );
		$info_result    = $logger->info( 'Info message' );
		$warning_result = $logger->warning( 'Warning message' );
		$error_result   = $logger->error( 'Error message' );

		// Assert.
		$this->assertIsInt( $debug_result );
		$this->assertIsInt( $info_result );
		$this->assertIsInt( $warning_result );
		$this->assertIsInt( $error_result );

		$logs = Migration_Logs::get_all_by( 'migration_execution_id', $execution_id );
		$this->assertCount( 4, $logs );

		$log_types = array_map( fn( $log ) => $log['type']->getValue(), $logs );
		$this->assertContains( 'debug', $log_types );
		$this->assertContains( 'info', $log_types );
		$this->assertContains( 'warning', $log_types );
		$this->assertContains( 'error', $log_types );

		// Clean up.
		remove_all_filters( "stellarwp_migrations_{$prefix}_minimum_log_level" );
	}

	/**
	 * @test
	 */
	public function it_should_set_min_log_level_once_per_logger_instance(): void {
		// Arrange.
		$execution_id = 5008;
		$prefix       = Config::get_hook_prefix();

		$filter_call_count = 0;
		add_filter(
			"stellarwp_migrations_{$prefix}_minimum_log_level",
			function () use ( &$filter_call_count ) {
				$filter_call_count++;
				return Log_Type::INFO();
			}
		);

		$logger = new Logger( $execution_id );

		// Act.
		$logger->info( 'First log' );
		$logger->warning( 'Second log' );
		$logger->error( 'Third log' );

		// Assert.
		$this->assertEquals( 1, $filter_call_count, 'Filter should only be called once per logger instance' );

		// Clean up.
		remove_all_filters( "stellarwp_migrations_{$prefix}_minimum_log_level" );
	}
}

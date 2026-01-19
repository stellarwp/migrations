<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\DB\DB;
use StellarWP\Migrations\Enums\Log_Type;
use StellarWP\Migrations\Enums\Status;
use StellarWP\Migrations\Models\Execution;
use StellarWP\Migrations\Tables\Migration_Executions;
use StellarWP\Migrations\Tables\Migration_Logs;
use StellarWP\Migrations\Tasks\Clear_Logs;

/**
 * Tests for Clear_Logs task.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations
 */
class Clear_Logs_Test extends WPTestCase {
	/**
	 * @test
	 */
	public function it_should_have_correct_task_prefix(): void {
		// Arrange.

		// Act.
		$task = new Clear_Logs();

		// Assert.
		$this->assertEquals( 'mig_clear_logs_', $task->get_task_prefix() );
	}

	/**
	 * @test
	 */
	public function it_should_have_correct_group(): void {
		// Arrange.
		$prefix = Config::get_hook_prefix();

		// Act.
		$task = new Clear_Logs();

		// Assert.
		$this->assertEquals( "{$prefix}_migrations", $task->get_group() );
	}

	/**
	 * @test
	 */
	public function it_should_return_default_retention_days(): void {
		// Arrange.

		// Act.
		$retention_days = Clear_Logs::get_retention_days();

		// Assert.
		$this->assertEquals( 180, $retention_days );
	}

	/**
	 * @test
	 */
	public function it_should_respect_retention_days_filter(): void {
		// Arrange.
		$prefix = Config::get_hook_prefix();

		add_filter(
			"stellarwp_migrations_{$prefix}_log_retention_days",
			function () {
				return 90;
			}
		);

		// Act.
		$retention_days = Clear_Logs::get_retention_days();

		// Assert.
		$this->assertEquals( 90, $retention_days );
	}

	/**
	 * @test
	 */
	public function it_should_fallback_to_default_when_filter_returns_invalid_value(): void {
		// Arrange.
		$prefix = Config::get_hook_prefix();

		add_filter(
			"stellarwp_migrations_{$prefix}_log_retention_days",
			function () {
				return 0;
			}
		);

		// Act.
		$retention_days = Clear_Logs::get_retention_days();

		// Assert.
		$this->assertEquals( 180, $retention_days, 'Should fallback to default when filter returns 0' );
	}

	/**
	 * @test
	 */
	public function it_should_clear_logs_for_old_executions(): void {
		// Arrange.
		$old_date = gmdate( 'Y-m-d H:i:s', strtotime( '-200 days' ) );

		// Create an old execution.
		Migration_Executions::insert(
			[
				'migration_id'    => 'test_migration',
				'status'          => Status::COMPLETED()->getValue(),
				'end_date_gmt'    => $old_date,
				'items_total'     => 10,
				'items_processed' => 10,
			]
		);
		$old_execution_id = (int) DB::last_insert_id();

		// Create logs for the old execution.
		Migration_Logs::insert(
			[
				'migration_execution_id' => $old_execution_id,
				'type'                   => Log_Type::INFO()->getValue(),
				'message'                => 'Old log 1',
			]
		);

		Migration_Logs::insert(
			[
				'migration_execution_id' => $old_execution_id,
				'type'                   => Log_Type::WARNING()->getValue(),
				'message'                => 'Old log 2',
			]
		);

		// Create a recent execution that should not be affected.
		Migration_Executions::insert(
			[
				'migration_id'    => 'test_migration_2',
				'status'          => Status::COMPLETED()->getValue(),
				'end_date_gmt'    => current_time( 'mysql', true ),
				'items_total'     => 5,
				'items_processed' => 5,
			]
		);
		$recent_execution_id = (int) DB::last_insert_id();

		// Create logs for the recent execution.
		Migration_Logs::insert(
			[
				'migration_execution_id' => $recent_execution_id,
				'type'                   => Log_Type::INFO()->getValue(),
				'message'                => 'Recent log',
			]
		);

		// Act.
		$task = new Clear_Logs();
		$task->process();

		// Assert.
		$old_logs = Migration_Logs::get_all_by( 'migration_execution_id', $old_execution_id );

		codecept_debug( $old_logs );

		$this->assertCount( 0, $old_logs, 'Old logs should be deleted' );

		$recent_logs = Migration_Logs::get_all_by( 'migration_execution_id', $recent_execution_id );
		$this->assertCount( 1, $recent_logs, 'Recent logs should not be deleted' );
		$this->assertEquals( 'Recent log', $recent_logs[0]['message'] );
	}

	/**
	 * @test
	 */
	public function it_should_delete_all_old_logs(): void {
		// Arrange.
		$old_date = gmdate( 'Y-m-d H:i:s', strtotime( '-200 days' ) );

		Migration_Executions::insert(
			[
				'migration_id'    => 'test_migration',
				'status'          => Status::COMPLETED()->getValue(),
				'end_date_gmt'    => $old_date,
				'items_total'     => 10,
				'items_processed' => 10,
			]
		);
		$execution_id = (int) DB::last_insert_id();

		Migration_Logs::insert(
			[
				'migration_execution_id' => $execution_id,
				'type'                   => Log_Type::INFO()->getValue(),
				'message'                => 'Old log',
			]
		);

		// Act.
		$task = new Clear_Logs();
		$task->process();

		// Assert.
		$logs = Migration_Logs::get_all_by( 'migration_execution_id', $execution_id );

		$this->assertCount( 0, $logs, 'All old logs should be deleted' );
	}

	/**
	 * @test
	 */
	public function it_should_not_delete_logs_when_no_old_executions_exist(): void {
		// Arrange.
		Migration_Executions::insert(
			[
				'migration_id'    => 'test_migration',
				'status'          => Status::COMPLETED()->getValue(),
				'end_date_gmt'    => current_time( 'mysql', true ),
				'items_total'     => 5,
				'items_processed' => 5,
			]
		);
		$recent_execution_id = (int) DB::last_insert_id();

		Migration_Logs::insert(
			[
				'migration_execution_id' => $recent_execution_id,
				'type'                   => Log_Type::INFO()->getValue(),
				'message'                => 'Recent log',
			]
		);

		// Act.
		$task = new Clear_Logs();
		$task->process();

		// Assert.
		$logs = Migration_Logs::get_all_by( 'migration_execution_id', $recent_execution_id );
		$this->assertCount( 1, $logs, 'Recent logs should not be deleted' );
	}

	/**
	 * @test
	 */
	public function it_should_handle_executions_without_logs(): void {
		// Arrange.
		$old_date = gmdate( 'Y-m-d H:i:s', strtotime( '-200 days' ) );

		Migration_Executions::insert(
			[
				'migration_id'    => 'test_migration',
				'status'          => Status::COMPLETED()->getValue(),
				'end_date_gmt'    => $old_date,
				'items_total'     => 10,
				'items_processed' => 10,
			]
		);
		$execution_id = (int) DB::last_insert_id();

		// Act.
		$task = new Clear_Logs();
		$task->process();

		// Assert.
		$logs = Migration_Logs::get_all_by( 'migration_execution_id', $execution_id );

		$this->assertCount( 0, $logs, 'Should have no logs when execution had no logs' );
	}

	/**
	 * @test
	 */
	public function it_should_clear_logs_for_multiple_old_executions(): void {
		// Arrange.
		$old_date = gmdate( 'Y-m-d H:i:s', strtotime( '-200 days' ) );

		Migration_Executions::insert(
			[
				'migration_id'    => 'test_migration_1',
				'status'          => Status::COMPLETED()->getValue(),
				'end_date_gmt'    => $old_date,
				'items_total'     => 10,
				'items_processed' => 10,
			]
		);
		$execution_id_1 = (int) DB::last_insert_id();

		Migration_Executions::insert(
			[
				'migration_id'    => 'test_migration_2',
				'status'          => Status::FAILED()->getValue(),
				'end_date_gmt'    => $old_date,
				'items_total'     => 5,
				'items_processed' => 3,
			]
		);
		$execution_id_2 = (int) DB::last_insert_id();

		Migration_Logs::insert(
			[
				'migration_execution_id' => $execution_id_1,
				'type'                   => Log_Type::INFO()->getValue(),
				'message'                => 'Log for execution 1',
			]
		);

		Migration_Logs::insert(
			[
				'migration_execution_id' => $execution_id_2,
				'type'                   => Log_Type::ERROR()->getValue(),
				'message'                => 'Log for execution 2',
			]
		);

		// Act.
		$task = new Clear_Logs();
		$task->process();

		// Assert.
		$logs_1 = Migration_Logs::get_all_by( 'migration_execution_id', $execution_id_1 );

		$this->assertCount( 0, $logs_1, 'All logs should be deleted for execution 1' );

		$logs_2 = Migration_Logs::get_all_by( 'migration_execution_id', $execution_id_2 );

		$this->assertCount( 0, $logs_2, 'All logs should be deleted for execution 2' );
	}

	/**
	 * @test
	 */
	public function it_should_respect_custom_retention_period(): void {
		// Arrange.
		$prefix = Config::get_hook_prefix();

		// Set retention to 100 days.
		add_filter(
			"stellarwp_migrations_{$prefix}_log_retention_days",
			function () {
				return 100;
			}
		);

		// Create execution that is 150 days old (should be deleted).
		Migration_Executions::insert(
			[
				'migration_id'    => 'test_migration_old',
				'status'          => Status::COMPLETED()->getValue(),
				'end_date_gmt'    => gmdate( 'Y-m-d H:i:s', strtotime( '-150 days' ) ),
				'items_total'     => 10,
				'items_processed' => 10,
			]
		);
		$old_execution_id = (int) DB::last_insert_id();

		// Create execution that is 50 days old (should NOT be deleted).
		Migration_Executions::insert(
			[
				'migration_id'    => 'test_migration_recent',
				'status'          => Status::COMPLETED()->getValue(),
				'end_date_gmt'    => gmdate( 'Y-m-d H:i:s', strtotime( '-50 days' ) ),
				'items_total'     => 5,
				'items_processed' => 5,
			]
		);
		$recent_execution_id = (int) DB::last_insert_id();

		Migration_Logs::insert(
			[
				'migration_execution_id' => $old_execution_id,
				'type'                   => Log_Type::INFO()->getValue(),
				'message'                => 'Old log',
			]
		);

		Migration_Logs::insert(
			[
				'migration_execution_id' => $recent_execution_id,
				'type'                   => Log_Type::INFO()->getValue(),
				'message'                => 'Recent log',
			]
		);

		// Act.
		$task = new Clear_Logs();
		$task->process();

		// Assert.
		$old_logs = Migration_Logs::get_all_by( 'migration_execution_id', $old_execution_id );

		$this->assertCount( 0, $old_logs, 'Old logs should be deleted' );

		$recent_logs = Migration_Logs::get_all_by( 'migration_execution_id', $recent_execution_id );
		$this->assertCount( 1, $recent_logs, 'Recent logs should not be deleted' );
		$this->assertEquals( 'Recent log', $recent_logs[0]['message'] );
	}

	/**
	 * @test
	 */
	public function it_should_respect_retention_period_of_one_day(): void {
		// Arrange.
		$prefix = Config::get_hook_prefix();

		// Set retention to 1 day.
		add_filter(
			"stellarwp_migrations_{$prefix}_log_retention_days",
			function () {
				return 1;
			}
		);

		// Create execution that is 2 days old (should be deleted).
		Migration_Executions::insert(
			[
				'migration_id'    => 'test_migration_old',
				'status'          => Status::COMPLETED()->getValue(),
				'end_date_gmt'    => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days' ) ),
				'items_total'     => 10,
				'items_processed' => 10,
			]
		);
		$old_execution_id = (int) DB::last_insert_id();

		// Create execution that is 12 hours old (should NOT be deleted).
		Migration_Executions::insert(
			[
				'migration_id'    => 'test_migration_recent',
				'status'          => Status::COMPLETED()->getValue(),
				'end_date_gmt'    => gmdate( 'Y-m-d H:i:s', strtotime( '-12 hours' ) ),
				'items_total'     => 5,
				'items_processed' => 5,
			]
		);
		$recent_execution_id = (int) DB::last_insert_id();

		Migration_Logs::insert(
			[
				'migration_execution_id' => $old_execution_id,
				'type'                   => Log_Type::INFO()->getValue(),
				'message'                => 'Old log',
			]
		);

		Migration_Logs::insert(
			[
				'migration_execution_id' => $recent_execution_id,
				'type'                   => Log_Type::INFO()->getValue(),
				'message'                => 'Recent log',
			]
		);

		// Act.
		$task = new Clear_Logs();
		$task->process();

		// Assert.
		$old_logs = Migration_Logs::get_all_by( 'migration_execution_id', $old_execution_id );

		$this->assertCount( 0, $old_logs, 'Old logs should be deleted with 1 day retention' );

		$recent_logs = Migration_Logs::get_all_by( 'migration_execution_id', $recent_execution_id );
		$this->assertCount( 1, $recent_logs, 'Recent logs should not be deleted with 1 day retention' );
		$this->assertEquals( 'Recent log', $recent_logs[0]['message'] );
	}
}

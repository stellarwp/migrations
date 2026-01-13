<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\DB\DB;
use StellarWP\Migrations\Enums\Log_Type;
use StellarWP\Migrations\Enums\Status;
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

		// Clean up.
		remove_all_filters( "stellarwp_migrations_{$prefix}_log_retention_days" );
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

		// Clean up.
		remove_all_filters( "stellarwp_migrations_{$prefix}_log_retention_days" );
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


		// Original logs should be deleted, only summary log should remain.
		$this->assertCount( 1, $old_logs, 'Should have only one summary log entry after deletion' );
		$this->assertStringContainsString( 'Old logs deleted on', $old_logs[0]['message'] );

		$recent_logs = Migration_Logs::get_all_by( 'migration_execution_id', $recent_execution_id );
		$this->assertCount( 1, $recent_logs, 'Recent logs should not be deleted' );
		$this->assertEquals( 'Recent log', $recent_logs[0]['message'] );
	}

	/**
	 * @test
	 */
	public function it_should_create_summary_log_entry_after_deleting_logs(): void {
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
		// Original log should be deleted, only summary log should remain.
		$this->assertCount( 1, $logs, 'Should have only one summary log entry' );

		$summary_log = $logs[0];
		$this->assertEquals( Log_Type::INFO()->getValue(), $summary_log['type']->getValue() );
		$this->assertStringContainsString( 'Old logs deleted on', $summary_log['message'] );
		$this->assertStringContainsString( 'Migration execution status: completed', $summary_log['message'] );
		$this->assertArrayHasKey( 'deletion_date', $summary_log['data'] );
		$this->assertArrayHasKey( 'migration_status', $summary_log['data'] );
		$this->assertArrayHasKey( 'retention_days', $summary_log['data'] );
		$this->assertEquals( Status::COMPLETED()->getValue(), $summary_log['data']['migration_status'] );
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
		// Summary log should be created even when no logs existed.
		$this->assertCount( 1, $logs, 'Should create summary log even when no logs existed' );
		$this->assertStringContainsString( 'Old logs deleted on', $logs[0]['message'] );
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
		// Original log should be deleted, only summary log should remain.
		$this->assertCount( 1, $logs_1, 'Should have only summary log for execution 1' );
		$this->assertStringContainsString( 'Old logs deleted on', $logs_1[0]['message'] );
		$this->assertStringContainsString( 'completed', $logs_1[0]['message'] );

		$logs_2 = Migration_Logs::get_all_by( 'migration_execution_id', $execution_id_2 );
		// Original log should be deleted, only summary log should remain.
		$this->assertCount( 1, $logs_2, 'Should have only summary log for execution 2' );
		$this->assertStringContainsString( 'Old logs deleted on', $logs_2[0]['message'] );
		$this->assertStringContainsString( 'failed', $logs_2[0]['message'] );
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
		// Original log should be deleted, only summary log should remain.
		$this->assertCount( 1, $old_logs, 'Should have only summary log for old execution' );
		$this->assertStringContainsString( 'Old logs deleted on', $old_logs[0]['message'] );

		$recent_logs = Migration_Logs::get_all_by( 'migration_execution_id', $recent_execution_id );
		$this->assertCount( 1, $recent_logs, 'Recent logs should not be deleted' );
		$this->assertEquals( 'Recent log', $recent_logs[0]['message'] );

		// Clean up.
		remove_all_filters( "stellarwp_migrations_{$prefix}_log_retention_days" );
	}
}

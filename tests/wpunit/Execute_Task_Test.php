<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\DB\DB;
use StellarWP\Migrations\Tests\Migrations\Simple_Migration;
use StellarWP\Migrations\Tasks\Execute;
use StellarWP\Shepherd\Exceptions\ShepherdTaskFailWithoutRetryException;
use InvalidArgumentException;
use StellarWP\Migrations\Models\Execution;
use StellarWP\Migrations\Tables\Migration_Executions;
use StellarWP\Migrations\Enums\Status;

class Execute_Task_Test extends WPTestCase {
	/**
	 * @before
	 */
	public function reset_state(): void {
		Simple_Migration::reset();
		tests_migrations_clear_calls_data();

		$container = Config::get_container();
		$container->get( Registry::class )->flush();
	}

	/**
	 * @test
	 */
	public function it_should_create_execute_task_with_correct_arguments(): void {
		// Arrange.
		Migration_Executions::insert(
			[
				'migration_id' => 'test_migration',
				'status'       => Status::SCHEDULED()->getValue(),
			]
		);
		$execution_id = (int) DB::last_insert_id();

		// Act.
		$task = new Execute( 'up', 'test_migration', 1, 1, $execution_id );
		$args = $task->get_args();

		// Assert.
		$this->assertEquals( 'up', $args[0] );
		$this->assertEquals( 'test_migration', $args[1] );
		$this->assertEquals( 1, $args[2] );
		$this->assertEquals( 1, $args[3] );
		$this->assertEquals( $execution_id, $args[4] );
	}

	/**
	 * @test
	 */
	public function it_should_have_correct_task_prefix_for_up(): void {
		// Arrange.
		Migration_Executions::insert(
			[
				'migration_id' => 'test_migration',
				'status'       => Status::SCHEDULED()->getValue(),
			]
		);
		$execution_id = (int) DB::last_insert_id();

		// Act.
		$task = new Execute( 'up', 'test_migration', 1, 1, $execution_id );

		// Assert.
		$this->assertEquals( 'mig_up_', $task->get_task_prefix() );
	}

	/**
	 * @test
	 */
	public function it_should_have_correct_task_prefix_for_down(): void {
		// Arrange.
		Migration_Executions::insert(
			[
				'migration_id' => 'test_migration',
				'status'       => Status::SCHEDULED()->getValue(),
			]
		);
		$execution_id = (int) DB::last_insert_id();

		// Act.
		$task = new Execute( 'down', 'test_migration', 1, 1, $execution_id );

		// Assert.
		$this->assertEquals( 'mig_down_', $task->get_task_prefix() );
	}

	/**
	 * @test
	 */
	public function it_should_have_correct_group(): void {
		// Arrange.
		Migration_Executions::insert(
			[
				'migration_id' => 'test_migration',
				'status'       => Status::SCHEDULED()->getValue(),
			]
		);
		$execution_id = (int) DB::last_insert_id();

		$prefix = Config::get_hook_prefix();

		// Act.
		$task = new Execute( 'up', 'test_migration', 1, 1, $execution_id );

		// Assert.
		$this->assertEquals( "{$prefix}_migrations", $task->get_group() );
	}

	/**
	 * @test
	 */
	public function it_should_not_allow_retries(): void {
		// Arrange.
		Migration_Executions::insert(
			[
				'migration_id' => 'test_migration',
				'status'       => Status::SCHEDULED()->getValue(),
			]
		);
		$execution_id = (int) DB::last_insert_id();

		// Act.
		$task = new Execute( 'up', 'test_migration', 1, 1, $execution_id );

		// Assert.
		$this->assertEquals( 0, $task->get_max_retries() );
	}

	/**
	 * @test
	 */
	public function it_should_throw_if_method_is_invalid(): void {
		// Arrange.
		Migration_Executions::insert(
			[
				'migration_id' => 'test_migration',
				'status'       => Status::SCHEDULED()->getValue(),
			]
		);
		$execution_id = (int) DB::last_insert_id();

		// Assert.
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Execute task method must be `up` or `down`.' );

		// Act.
		new Execute( 'invalid', 'test_migration', 1, 1, $execution_id );
	}

	/**
	 * @test
	 */
	public function it_should_throw_if_batch_is_zero(): void {
		// Arrange.
		Migration_Executions::insert(
			[
				'migration_id' => 'test_migration',
				'status'       => Status::SCHEDULED()->getValue(),
			]
		);
		$execution_id = (int) DB::last_insert_id();

		// Assert.
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'batch must be greater than 0' );

		// Act.
		new Execute( 'up', 'test_migration', 0, 1, $execution_id );
	}

	/**
	 * @test
	 */
	public function it_should_throw_if_batch_is_negative(): void {
		// Arrange.
		Migration_Executions::insert(
			[
				'migration_id' => 'test_migration',
				'status'       => Status::SCHEDULED()->getValue(),
			]
		);
		$execution_id = (int) DB::last_insert_id();

		// Assert.
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'batch must be greater than 0' );

		// Act.
		new Execute( 'up', 'test_migration', -1, 1, $execution_id );
	}

	/**
	 * @test
	 */
	public function it_should_throw_if_migration_not_found(): void {
		// Arrange.
		Migration_Executions::insert(
			[
				'migration_id' => 'non_existent_migration',
				'status'       => Status::SCHEDULED()->getValue(),
			]
		);
		$execution_id = (int) DB::last_insert_id();

		$task = new Execute( 'up', 'non_existent_migration', 1, 1, $execution_id );

		// Assert.
		$this->expectException( ShepherdTaskFailWithoutRetryException::class );
		$this->expectExceptionMessage( 'not found' );

		// Act.
		$task->process();
	}

	/**
	 * @test
	 */
	public function it_should_process_migration_up(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$prefix = Config::get_hook_prefix();

		// Act.
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		// Assert.
		$this->assertTrue( Simple_Migration::$up_called );
	}

	/**
	 * @test
	 */
	public function it_should_process_migration_down(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$prefix = Config::get_hook_prefix();

		// Run up migration first.
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertTrue( Simple_Migration::$up_called );

		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_simple_migration' );
		$this->assertInstanceOf( Execution::class, $execution );

		// Act.
		$task = new Execute( 'down', 'tests_simple_migration', 1, 1, $execution->get_id() );
		$task->process();

		// Assert.
		$this->assertTrue( Simple_Migration::$down_called );
	}

	/**
	 * @test
	 */
	public function it_should_skip_if_already_done(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );

		Simple_Migration::$up_called = true;

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$prefix = Config::get_hook_prefix();

		// Act.
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		// Assert.
		$this->assertEmpty( Simple_Migration::$up_batches );
	}

	/**
	 * @test
	 */
	public function it_should_set_reverted_status_on_manual_rollback(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		$prefix = Config::get_hook_prefix();

		// Run up migration first.
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertTrue( Simple_Migration::$up_called );

		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_simple_migration' );
		$this->assertInstanceOf( Execution::class, $execution );
		$this->assertEquals( Status::COMPLETED()->getValue(), $execution->get_status()->getValue() );

		// Act.
		$task = new Execute( 'down', 'tests_simple_migration', 1, 1, $execution->get_id() );
		$task->process();

		// Assert.
		$this->assertTrue( Simple_Migration::$down_called );

		$execution = Migration_Executions::get_first_by( 'id', $execution->get_id() );
		$this->assertInstanceOf( Execution::class, $execution );
		$this->assertEquals( Status::REVERTED()->getValue(), $execution->get_status()->getValue() );
	}

	/**
	 * @test
	 */
	public function it_should_have_parent_execution_id_on_automatic_rollback_execution(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		Simple_Migration::$up_called = true;

		// Create the failed execution (original migration that failed).
		Migration_Executions::insert(
			[
				'migration_id' => 'tests_simple_migration',
				'status'       => Status::FAILED()->getValue(),
			]
		);

		$failed_execution_id = (int) DB::last_insert_id();

		// Create the rollback execution (linked to the failed one via parent_execution_id).
		Migration_Executions::insert(
			[
				'migration_id'        => 'tests_simple_migration',
				'status'              => Status::SCHEDULED()->getValue(),
				'items_total'         => 1,
				'items_processed'     => 0,
				'parent_execution_id' => $failed_execution_id,
			]
		);

		$rollback_execution_id = (int) DB::last_insert_id();

		// Act.
		$task = new Execute( 'down', 'tests_simple_migration', 1, 1, $rollback_execution_id );
		$task->process();

		// Assert.
		$this->assertTrue( Simple_Migration::$down_called );

		$rollback_execution = Migration_Executions::get_first_by( 'id', $rollback_execution_id );
		$this->assertInstanceOf( Execution::class, $rollback_execution );
		$this->assertEquals( $failed_execution_id, $rollback_execution->get_parent_execution_id(), 'Rollback execution should have parent_execution_id pointing to the failed execution' );
		$this->assertEquals( Status::REVERTED()->getValue(), $rollback_execution->get_status()->getValue(), 'Automatic rollback execution should end with REVERTED status' );
	}

	/**
	 * @test
	 */
	public function it_should_set_reverted_status_on_manual_rollback_from_running_status(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		// Simulate that migration was run.
		Simple_Migration::$up_called = true;

		// Create an execution with RUNNING status to simulate manual rollback during execution.
		Migration_Executions::insert(
			[
				'migration_id' => 'tests_simple_migration',
				'status'       => Status::RUNNING()->getValue(),
			]
		);

		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_simple_migration' );
		$this->assertInstanceOf( Execution::class, $execution );
		$this->assertEquals( Status::RUNNING()->getValue(), $execution->get_status()->getValue() );

		// Act.
		$task = new Execute( 'down', 'tests_simple_migration', 1, 1, $execution->get_id() );
		$task->process();

		// Assert.
		$this->assertTrue( Simple_Migration::$down_called );

		$execution = Migration_Executions::get_first_by( 'id', $execution->get_id() );
		$this->assertInstanceOf( Execution::class, $execution );
		$this->assertEquals( Status::REVERTED()->getValue(), $execution->get_status()->getValue() );
	}

	/**
	 * @test
	 */
	public function it_should_set_reverted_status_on_manual_rollback_from_pending_status(): void {
		// Arrange.
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		// Simulate that migration was run.
		Simple_Migration::$up_called = true;

		// Create an execution with PENDING status to simulate manual rollback before execution.
		Migration_Executions::insert(
			[
				'migration_id' => 'tests_simple_migration',
				'status'       => Status::PENDING()->getValue(),
			]
		);

		$execution = Migration_Executions::get_first_by( 'migration_id', 'tests_simple_migration' );
		$this->assertInstanceOf( Execution::class, $execution );
		$this->assertEquals( Status::PENDING()->getValue(), $execution->get_status()->getValue() );

		// Act.
		$task = new Execute( 'down', 'tests_simple_migration', 1, 1, $execution->get_id() );
		$task->process();

		// Assert.
		$this->assertTrue( Simple_Migration::$down_called );

		$execution = Migration_Executions::get_first_by( 'id', $execution->get_id() );
		$this->assertInstanceOf( Execution::class, $execution );
		$this->assertEquals( Status::REVERTED()->getValue(), $execution->get_status()->getValue() );
	}

	/**
	 * @after
	 */
	public function cleanup(): void {
		Simple_Migration::reset();
		tests_migrations_clear_calls_data();
	}
}

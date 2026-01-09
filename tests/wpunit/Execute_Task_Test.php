<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Migrations\Simple_Migration;
use StellarWP\Migrations\Tasks\Execute;
use StellarWP\Shepherd\Exceptions\ShepherdTaskFailWithoutRetryException;
use InvalidArgumentException;
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
		$execution_id = Migration_Executions::insert(
			[
				'migration_id' => 'test_migration',
				'status'       => Status::SCHEDULED()->getValue(),
			]
		);

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
		$execution_id = Migration_Executions::insert(
			[
				'migration_id' => 'test_migration',
				'status'       => Status::SCHEDULED()->getValue(),
			]
		);

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
		$execution_id = Migration_Executions::insert(
			[
				'migration_id' => 'test_migration',
				'status'       => Status::SCHEDULED()->getValue(),
			]
		);

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
		$execution_id = Migration_Executions::insert(
			[
				'migration_id' => 'test_migration',
				'status'       => Status::SCHEDULED()->getValue(),
			]
		);

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
		$execution_id = Migration_Executions::insert(
			[
				'migration_id' => 'test_migration',
				'status'       => Status::SCHEDULED()->getValue(),
			]
		);

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
		$execution_id = Migration_Executions::insert(
			[
				'migration_id' => 'test_migration',
				'status'       => Status::SCHEDULED()->getValue(),
			]
		);

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
		$execution_id = Migration_Executions::insert(
			[
				'migration_id' => 'test_migration',
				'status'       => Status::SCHEDULED()->getValue(),
			]
		);

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
		$execution_id = Migration_Executions::insert(
			[
				'migration_id' => 'test_migration',
				'status'       => Status::SCHEDULED()->getValue(),
			]
		);

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
		$execution_id = Migration_Executions::insert(
			[
				'migration_id' => 'non_existent_migration',
				'status'       => Status::SCHEDULED()->getValue(),
			]
		);

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

		// Act.
		$task = new Execute( 'down', 'tests_simple_migration', 1, 1, $execution['id'] );
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
	 * @after
	 */
	public function cleanup(): void {
		Simple_Migration::reset();
		tests_migrations_clear_calls_data();
	}
}

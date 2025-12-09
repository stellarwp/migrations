<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Migrations\Simple_Migration;
use StellarWP\Migrations\Tasks\Execute;
use StellarWP\Shepherd\Exceptions\ShepherdTaskFailWithoutRetryException;
use InvalidArgumentException;

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
		$task = new Execute( 'up', 'test_migration', 1 );
		$args = $task->get_args();

		$this->assertEquals( 'up', $args[0] );
		$this->assertEquals( 'test_migration', $args[1] );
		$this->assertEquals( 1, $args[2] );
	}

	/**
	 * @test
	 */
	public function it_should_have_correct_task_prefix_for_up(): void {
		$task = new Execute( 'up', 'test_migration', 1 );

		$this->assertEquals( 'mig_up_', $task->get_task_prefix() );
	}

	/**
	 * @test
	 */
	public function it_should_have_correct_task_prefix_for_down(): void {
		$task = new Execute( 'down', 'test_migration', 1 );

		$this->assertEquals( 'mig_down_', $task->get_task_prefix() );
	}

	/**
	 * @test
	 */
	public function it_should_have_correct_group(): void {
		$task   = new Execute( 'up', 'test_migration', 1 );
		$prefix = Config::get_hook_prefix();

		$this->assertEquals( "{$prefix}_migrations", $task->get_group() );
	}

	/**
	 * @test
	 */
	public function it_should_not_allow_retries(): void {
		$task = new Execute( 'up', 'test_migration', 1 );

		$this->assertEquals( 0, $task->get_max_retries() );
	}

	/**
	 * @test
	 */
	public function it_should_throw_if_method_is_invalid(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'method must be either "up" or "down"' );

		new Execute( 'invalid', 'test_migration', 1 );
	}

	/**
	 * @test
	 */
	public function it_should_throw_if_batch_is_zero(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'batch must be greater than 0' );

		new Execute( 'up', 'test_migration', 0 );
	}

	/**
	 * @test
	 */
	public function it_should_throw_if_batch_is_negative(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'batch must be greater than 0' );

		new Execute( 'up', 'test_migration', -1 );
	}

	/**
	 * @test
	 */
	public function it_should_throw_if_migration_not_found(): void {
		$task = new Execute( 'up', 'non_existent_migration', 1 );

		$this->expectException( ShepherdTaskFailWithoutRetryException::class );
		$this->expectExceptionMessage( 'not found' );

		$task->process();
	}

	/**
	 * @test
	 */
	public function it_should_process_migration_up(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry->register( $migration );

		$task = new Execute( 'up', $migration->get_id(), 1 );
		$task->process();

		$this->assertTrue( Simple_Migration::$up_called );
	}

	/**
	 * @test
	 */
	public function it_should_process_migration_down(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry->register( $migration );

		Simple_Migration::$up_called = true;

		$task = new Execute( 'down', $migration->get_id(), 1 );
		$task->process();

		$this->assertTrue( Simple_Migration::$down_called );
	}

	/**
	 * @test
	 */
	public function it_should_skip_if_already_done(): void {
		$registry  = Config::get_container()->get( Registry::class );
		$migration = new Simple_Migration();

		$registry->register( $migration );

		Simple_Migration::$up_called = true;

		$task = new Execute( 'up', $migration->get_id(), 1 );
		$task->process();

		$this->assertEmpty( Simple_Migration::$up_batches );
	}

	/**
	 * @after
	 */
	public function cleanup(): void {
		Simple_Migration::reset();
		tests_migrations_clear_calls_data();

		global $wp_actions;
		$prefix = Config::get_hook_prefix();
		unset( $wp_actions[ "stellarwp_migrations_{$prefix}_schedule_migrations" ] );
	}
}

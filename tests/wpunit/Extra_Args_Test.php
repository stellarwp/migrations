<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Migrations\Extra_Args_Migration;
use StellarWP\Migrations\Tables\Migration_Events;
use StellarWP\Migrations\Tasks\Execute;
use function StellarWP\Shepherd\shepherd;

class Extra_Args_Test extends WPTestCase {
	/**
	 * @before
	 * @after
	 */
	public function reset_state(): void {
		Extra_Args_Migration::reset();
		tests_migrations_clear_calls_data();

		$container = Config::get_container();
		$container->get( Registry::class )->flush();
	}

	/**
	 * @test
	 */
	public function it_should_create_execute_task_with_extra_arguments(): void {
		$migration = new Extra_Args_Migration();
		$registry = Config::get_container()->get( Registry::class );
		$registry->register( $migration );

		$up_spy = [];
		$down_spy = [];

		add_action( 'stellarwp_migrations_tests_extra_args_migration_up_batch_processed', function( $batch, $extra_args ) use ( &$up_spy ) {
			$up_spy[] = compact( 'batch', 'extra_args' );
		}, 10, 2 );

		add_action( 'stellarwp_migrations_tests_extra_args_migration_down_batch_processed', function( $batch, $extra_args ) use ( &$down_spy ) {
			$down_spy[] = compact( 'batch', 'extra_args' );
		}, 10, 2 );

		$prefix = Config::get_hook_prefix();

		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );

		$this->assertEquals( 4, count( $up_spy ) );
		$this->assertEquals( 4, count( $down_spy ) );

		foreach ( $up_spy as $offset => $data ) {
			$batch_number = ( (int) $offset ) + 1;
			$this->assertEquals( "arg1_batch_up_{$batch_number}", $data['extra_args'][0] );
			$this->assertEquals( "arg2_batch_up_{$batch_number}", $data['extra_args'][1] );
			$this->assertEquals( $batch_number, $data['batch'] );
		}

		foreach ( $down_spy as $offset => $data ) {
			$batch_number = ( (int) $offset ) + 1;
			$this->assertEquals( "arg1_batch_down_{$batch_number}", $data['extra_args'][0] );
			$this->assertEquals( "arg2_batch_down_{$batch_number}", $data['extra_args'][1] );
			$this->assertEquals( $batch_number, $data['batch'] );
		}
	}
}

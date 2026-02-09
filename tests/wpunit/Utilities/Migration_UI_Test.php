<?php
/**
 * Migration UI Utility Tests.
 *
 * Tests for the Migration_UI utility class.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Utilities
 */

declare( strict_types=1 );

namespace StellarWP\Migrations\Tests\Utilities;

use DateTime;
use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Contracts\Migration;
use StellarWP\Migrations\Enums\Operation;
use StellarWP\Migrations\Enums\Status;
use StellarWP\Migrations\Models\Execution;
use StellarWP\Migrations\Utilities\Migration_UI;
use StellarWP\Schema\Tables\Contracts\Table;

/**
 * Migration UI Utility Tests.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Utilities
 */
class Migration_UI_Test extends WPTestCase {

	/**
	 * Creates a mock Migration with the given behavior.
	 *
	 * @param array<string, mixed> $config Configuration for the mock (get_status, get_total_items, get_latest_execution, is_applicable, can_run).
	 *
	 * @return Migration
	 */
	private function create_migration_mock( array $config = [] ): Migration {
		$migration = $this->createMock( Migration::class );

		$status = $config['get_status'] ?? Status::PENDING();
		$migration->method( 'get_status' )->willReturn( $status );

		$total_items = $config['get_total_items'] ?? 10;
		$migration->method( 'get_total_items' )->willReturnCallback(
			static function ( ?Operation $op = null ) use ( $total_items, $config ) {
				if ( $op !== null && $op->getValue() === Operation::DOWN()->getValue() ) {
					return $config['get_total_items_down'] ?? $total_items;
				}

				return $total_items;
			}
		);

		$migration->method( 'get_latest_execution' )->willReturn( $config['get_latest_execution'] ?? null );
		$migration->method( 'is_applicable' )->willReturn( $config['is_applicable'] ?? true );
		$migration->method( 'can_run' )->willReturn( $config['can_run'] ?? true );

		return $migration;
	}

	/**
	 * Creates a test Execution with optional overrides.
	 *
	 * @param array<string, mixed> $overrides Attributes to override defaults.
	 *
	 * @return Execution
	 */
	private function create_execution( array $overrides = [] ): Execution {
		$defaults = [
			'id'                  => 1,
			'migration_id'        => 'test_migration',
			'start_date_gmt'      => new DateTime( '2024-01-15 10:00:00' ),
			'end_date_gmt'        => new DateTime( '2024-01-15 10:05:00' ),
			'status'              => Status::COMPLETED()->getValue(),
			'items_total'         => 100,
			'items_processed'     => 100,
			'parent_execution_id' => null,
			'created_at'          => new DateTime( '2024-01-15 09:55:00' ),
		];

		return new Execution( array_merge( $defaults, $overrides ) );
	}

	// -------------------------------------------------------------------------
	// get_display_status()
	// -------------------------------------------------------------------------

	/**
	 * @test
	 */
	public function it_should_return_not_applicable_when_pending_and_zero_items(): void {
		$migration = $this->create_migration_mock(
			[
				'get_status'      => Status::PENDING(),
				'get_total_items' => 0,
			]
		);
		$ui        = new Migration_UI( $migration );

		$result = $ui->get_display_status();

		$this->assertTrue( $result->equals( Status::NOT_APPLICABLE() ) );
	}

	/**
	 * @test
	 */
	public function it_should_return_pending_when_pending_and_has_items(): void {
		$migration = $this->create_migration_mock(
			[
				'get_status'      => Status::PENDING(),
				'get_total_items' => 5,
			]
		);
		$ui        = new Migration_UI( $migration );

		$result = $ui->get_display_status();

		$this->assertTrue( $result->equals( Status::PENDING() ) );
	}

	/**
	 * @test
	 */
	public function it_should_return_failed_when_reverted_and_parent_execution_failed(): void {
		$parent_execution = $this->create_execution(
			[
				'id'     => 100,
				'status' => Status::FAILED()->getValue(),
			]
		);
		$latest_execution = $this->create_execution(
			[
				'id'                  => 101,
				'parent_execution_id' => 100,
				'status'              => Status::REVERTED()->getValue(),
			]
		);

		$migration = $this->create_migration_mock(
			[
				'get_status'           => Status::REVERTED(),
				'get_latest_execution' => $latest_execution,
			]
		);
		$ui        = new Migration_UI( $migration );

		if ( function_exists( 'uopz_set_return' ) ) {
			uopz_set_return( Table::class, 'get_by_id', $parent_execution, false );
		}

		try {
			$result = $ui->get_display_status();
			$this->assertTrue( $result->equals( Status::FAILED() ) );
		} finally {
			if ( function_exists( 'uopz_unset_return' ) ) {
				uopz_unset_return( Table::class, 'get_by_id' );
			}
		}
	}

	/**
	 * @test
	 */
	public function it_should_return_reverted_when_reverted_and_no_parent_execution(): void {
		$migration = $this->create_migration_mock(
			[
				'get_status'           => Status::REVERTED(),
				'get_latest_execution' => null,
			]
		);
		$ui        = new Migration_UI( $migration );

		$result = $ui->get_display_status();

		$this->assertTrue( $result->equals( Status::REVERTED() ) );
	}

	/**
	 * @test
	 */
	public function it_should_return_status_as_is_when_completed(): void {
		$migration = $this->create_migration_mock(
			[
				'get_status'      => Status::COMPLETED(),
				'get_total_items' => 10,
			]
		);
		$ui        = new Migration_UI( $migration );

		$result = $ui->get_display_status();

		$this->assertTrue( $result->equals( Status::COMPLETED() ) );
	}

	// -------------------------------------------------------------------------
	// get_display_status_label()
	// -------------------------------------------------------------------------

	/**
	 * @test
	 */
	public function it_should_return_default_label_when_no_parent_execution(): void {
		$migration = $this->create_migration_mock(
			[
				'get_status'           => Status::COMPLETED(),
				'get_latest_execution' => null,
			]
		);
		$ui        = new Migration_UI( $migration );

		$result = $ui->get_display_status_label();

		$this->assertSame( Status::COMPLETED()->get_label(), $result );
	}

	/**
	 * @test
	 */
	public function it_should_append_auto_reverted_when_reverted_and_parent_failed(): void {
		$parent_execution = $this->create_execution(
			[
				'id'     => 100,
				'status' => Status::FAILED()->getValue(),
			]
		);
		$latest_execution = $this->create_execution(
			[
				'id'                  => 101,
				'parent_execution_id' => 100,
				'status'              => Status::REVERTED()->getValue(),
			]
		);

		$migration = $this->create_migration_mock(
			[
				'get_status'           => Status::REVERTED(),
				'get_latest_execution' => $latest_execution,
			]
		);
		$ui        = new Migration_UI( $migration );

		if ( function_exists( 'uopz_set_return' ) ) {
			uopz_set_return( Table::class, 'get_by_id', $parent_execution, false );
		}

		try {
			$result = $ui->get_display_status_label();
			$this->assertStringContainsString( Status::FAILED()->get_label(), $result );
			$this->assertStringContainsString( ' (auto-reverted)', $result );
		} finally {
			if ( function_exists( 'uopz_unset_return' ) ) {
				uopz_unset_return( Table::class, 'get_by_id' );
			}
		}
	}

	/**
	 * @test
	 */
	public function it_should_append_auto_revert_failed_when_failed_and_parent_failed(): void {
		$parent_execution = $this->create_execution(
			[
				'id'     => 100,
				'status' => Status::FAILED()->getValue(),
			]
		);
		$latest_execution = $this->create_execution(
			[
				'id'                  => 101,
				'parent_execution_id' => 100,
				'status'              => Status::FAILED()->getValue(),
			]
		);

		$migration = $this->create_migration_mock(
			[
				'get_status'           => Status::FAILED(),
				'get_latest_execution' => $latest_execution,
			]
		);
		$ui        = new Migration_UI( $migration );

		if ( function_exists( 'uopz_set_return' ) ) {
			uopz_set_return( Table::class, 'get_by_id', $parent_execution, false );
		}

		try {
			$result = $ui->get_display_status_label();
			$this->assertStringContainsString( Status::FAILED()->get_label(), $result );
			$this->assertStringContainsString( ' (auto-revert failed)', $result );
		} finally {
			if ( function_exists( 'uopz_unset_return' ) ) {
				uopz_unset_return( Table::class, 'get_by_id' );
			}
		}
	}

	// -------------------------------------------------------------------------
	// get_run_action_label()
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * @dataProvider run_action_label_provider
	 *
	 * @param Status $display_status Display status (used to drive the mock).
	 * @param string $expected       Expected label substring.
	 */
	public function it_should_return_correct_run_action_label( Status $display_status, string $expected ): void {
		$migration = $this->create_migration_mock(
			[
				'get_status'      => $display_status,
				'get_total_items' => 10,
			]
		);
		$ui        = new Migration_UI( $migration );

		$result = $ui->get_run_action_label();

		$this->assertStringContainsString( $expected, $result );
	}

	/**
	 * Data provider for run action label.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public static function run_action_label_provider(): array {
		return [
			'completed' => [ Status::COMPLETED(), 'Re-run' ],
			'reverted'  => [ Status::REVERTED(), 'Re-run' ],
			'failed'    => [ Status::FAILED(), 'Retry' ],
			'pending'   => [ Status::PENDING(), 'Start' ],
		];
	}

	// -------------------------------------------------------------------------
	// get_run_action_icon()
	// -------------------------------------------------------------------------

	/**
	 * @test
	 */
	public function it_should_return_retry_icon_for_completed_reverted_or_failed(): void {
		$statuses = [ Status::COMPLETED(), Status::REVERTED(), Status::FAILED() ];

		foreach ( $statuses as $status ) {
			$migration = $this->create_migration_mock( [ 'get_status' => $status ] );
			$ui        = new Migration_UI( $migration );

			$result = $ui->get_run_action_icon();

			$this->assertSame( 'retry', $result, 'Expected retry for status: ' . $status->getValue() );
		}
	}

	/**
	 * @test
	 */
	public function it_should_return_start_icon_for_pending(): void {
		$migration = $this->create_migration_mock( [ 'get_status' => Status::PENDING() ] );
		$ui        = new Migration_UI( $migration );

		$result = $ui->get_run_action_icon();

		$this->assertSame( 'start', $result );
	}

	// -------------------------------------------------------------------------
	// show_run()
	// -------------------------------------------------------------------------

	/**
	 * @test
	 */
	public function it_should_show_run_when_applicable_can_run_has_items_and_runnable_status(): void {
		$migration = $this->create_migration_mock(
			[
				'get_status'      => Status::PENDING(),
				'get_total_items' => 5,
				'is_applicable'   => true,
				'can_run'         => true,
			]
		);
		$ui        = new Migration_UI( $migration );

		$result = $ui->show_run();

		$this->assertTrue( $result );
	}

	/**
	 * @test
	 */
	public function it_should_not_show_run_when_zero_items(): void {
		$migration = $this->create_migration_mock(
			[
				'get_status'      => Status::PENDING(),
				'get_total_items' => 0,
				'is_applicable'   => true,
				'can_run'         => true,
			]
		);
		$ui        = new Migration_UI( $migration );

		$result = $ui->show_run();

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function it_should_not_show_run_when_not_applicable(): void {
		$migration = $this->create_migration_mock(
			[
				'get_status'      => Status::PENDING(),
				'get_total_items' => 5,
				'is_applicable'   => false,
				'can_run'         => true,
			]
		);
		$ui        = new Migration_UI( $migration );

		$result = $ui->show_run();

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function it_should_not_show_run_when_cannot_run(): void {
		$migration = $this->create_migration_mock(
			[
				'get_status'      => Status::PENDING(),
				'get_total_items' => 5,
				'is_applicable'   => true,
				'can_run'         => false,
			]
		);
		$ui        = new Migration_UI( $migration );

		$result = $ui->show_run();

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function it_should_show_run_for_completed_and_reverted(): void {
		foreach ( [ Status::COMPLETED(), Status::REVERTED() ] as $status ) {
			$migration = $this->create_migration_mock(
				[
					'get_status'      => $status,
					'get_total_items' => 5,
					'is_applicable'   => true,
					'can_run'         => true,
				]
			);
			$ui        = new Migration_UI( $migration );

			$result = $ui->show_run();

			$this->assertTrue( $result, 'show_run should be true for status: ' . $status->getValue() );
		}
	}

	// -------------------------------------------------------------------------
	// show_rollback()
	// -------------------------------------------------------------------------

	/**
	 * @test
	 */
	public function it_should_show_rollback_when_applicable_completed_and_has_down_items(): void {
		$migration = $this->create_migration_mock(
			[
				'get_status'           => Status::COMPLETED(),
				'get_total_items'      => 10,
				'get_total_items_down' => 10,
				'is_applicable'        => true,
			]
		);
		$ui        = new Migration_UI( $migration );

		$result = $ui->show_rollback();

		$this->assertTrue( $result );
	}

	/**
	 * @test
	 */
	public function it_should_not_show_rollback_when_zero_down_items(): void {
		$migration = $this->create_migration_mock(
			[
				'get_status'           => Status::COMPLETED(),
				'get_total_items'      => 10,
				'get_total_items_down' => 0,
				'is_applicable'        => true,
			]
		);
		$ui        = new Migration_UI( $migration );

		$result = $ui->show_rollback();

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function it_should_not_show_rollback_when_not_applicable(): void {
		$migration = $this->create_migration_mock(
			[
				'get_status'           => Status::COMPLETED(),
				'get_total_items_down' => 10,
				'is_applicable'        => false,
			]
		);
		$ui        = new Migration_UI( $migration );

		$result = $ui->show_rollback();

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function it_should_not_show_rollback_when_pending(): void {
		$migration = $this->create_migration_mock(
			[
				'get_status'           => Status::PENDING(),
				'get_total_items_down' => 10,
				'is_applicable'        => true,
			]
		);
		$ui        = new Migration_UI( $migration );

		$result = $ui->show_rollback();

		$this->assertFalse( $result );
	}

	/**
	 * @test
	 */
	public function it_should_show_rollback_for_failed_and_canceled(): void {
		foreach ( [ Status::FAILED(), Status::CANCELED() ] as $status ) {
			$migration = $this->create_migration_mock(
				[
					'get_status'           => $status,
					'get_total_items_down' => 5,
					'is_applicable'        => true,
				]
			);
			$ui        = new Migration_UI( $migration );

			$result = $ui->show_rollback();

			$this->assertTrue( $result, 'show_rollback should be true for status: ' . $status->getValue() );
		}
	}
}

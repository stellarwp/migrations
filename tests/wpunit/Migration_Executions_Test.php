<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Enums\Status;
use StellarWP\Migrations\Tables\Migration_Executions;

class Migration_Executions_Test extends WPTestCase {
	/**
	 * @test
	 */
	public function it_should_insert_a_migration_execution(): void {
		// Arrange.
		$migration_id = 'test_migration_' . uniqid();
		$data         = [
			'migration_id'    => $migration_id,
			'start_date'      => current_time( 'mysql', true ),
			'status'          => Status::RUNNING()->getValue(),
			'items_total'     => 100,
			'items_processed' => 0,
		];

		// Act.
		$result = Migration_Executions::insert( $data );

		// Assert.
		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );
	}

	/**
	 * @test
	 */
	public function it_should_retrieve_execution_by_migration_id(): void {
		// Arrange.
		$migration_id = 'test_retrieval_' . uniqid();
		$data         = [
			'migration_id'    => $migration_id,
			'start_date'      => current_time( 'mysql', true ),
			'status'          => Status::RUNNING()->getValue(),
			'items_total'     => 50,
			'items_processed' => 25,
		];
		Migration_Executions::insert( $data );

		// Act.
		$execution = Migration_Executions::get_first_by( 'migration_id', $migration_id );

		// Assert.
		$this->assertNotNull( $execution );
		$this->assertEquals( $migration_id, $execution['migration_id'] );
		$this->assertEquals( Status::RUNNING()->getValue(), $execution['status'] );
		$this->assertEquals( 50, $execution['items_total'] );
		$this->assertEquals( 25, $execution['items_processed'] );
	}

	/**
	 * @test
	 */
	public function it_should_retrieve_all_executions_by_migration_id(): void {
		// Arrange.
		$migration_id = 'test_all_executions_' . uniqid();

		Migration_Executions::insert(
			[
				'migration_id'    => $migration_id,
				'start_date'      => current_time( 'mysql', true ),
				'status'          => Status::RUNNING()->getValue(),
				'items_total'     => 100,
				'items_processed' => 0,
			]
		);

		Migration_Executions::insert(
			[
				'migration_id'    => $migration_id,
				'start_date'      => current_time( 'mysql', true ),
				'status'          => Status::RUNNING()->getValue(),
				'items_total'     => 100,
				'items_processed' => 50,
			]
		);

		Migration_Executions::insert(
			[
				'migration_id'    => $migration_id,
				'start_date'      => current_time( 'mysql', true ),
				'end_date'        => current_time( 'mysql', true ),
				'status'          => Status::COMPLETED()->getValue(),
				'items_total'     => 100,
				'items_processed' => 100,
			]
		);

		// Act.
		$executions = Migration_Executions::get_all_by( 'migration_id', $migration_id );

		// Assert.
		$this->assertCount( 3, $executions );
	}

	/**get_all_by
	 * @test
	 */
	public function it_should_store_nullable_end_date(): void {
		// Arrange.
		$migration_id = 'test_nullable_' . uniqid();

		// Act.
		Migration_Executions::insert(
			[
				'migration_id'    => $migration_id,
				'start_date'      => current_time( 'mysql', true ),
				'status'          => Status::RUNNING()->getValue(),
				'items_total'     => 100,
				'items_processed' => 0,
			]
		);

		$execution = Migration_Executions::get_first_by( 'migration_id', $migration_id );

		// Assert.
		$this->assertNotNull( $execution );
		$this->assertNull( $execution['end_date'] );
	}

	/**
	 * @test
	 */
	public function it_should_record_start_date(): void {
		// Arrange.
		$migration_id = 'test_start_date_' . uniqid();
		$before       = current_time( 'mysql', true );

		// Act.
		Migration_Executions::insert(
			[
				'migration_id'    => $migration_id,
				'start_date'      => current_time( 'mysql', true ),
				'status'          => Status::RUNNING()->getValue(),
				'items_total'     => 100,
				'items_processed' => 0,
			]
		);

		$after     = current_time( 'mysql', true );
		$execution = Migration_Executions::get_first_by( 'migration_id', $migration_id );

		// Assert.
		$this->assertNotNull( $execution );
		$this->assertArrayHasKey( 'start_date', $execution );

		// Convert start_date to string for comparison if it's a DateTime object.
		$start_date = $execution['start_date'];
		if ( $start_date instanceof \DateTime ) {
			$start_date = $start_date->format( 'Y-m-d H:i:s' );
		}

		$this->assertGreaterThanOrEqual( $before, $start_date );
		$this->assertLessThanOrEqual( $after, $start_date );
	}

	/**
	 * @test
	 */
	public function it_should_return_null_for_non_existent_migration(): void {
		// Act.
		$execution = Migration_Executions::get_first_by( 'migration_id', 'non_existent_migration_' . uniqid() );

		// Assert.
		$this->assertNull( $execution );
	}

	/**
	 * @test
	 */
	public function it_should_return_empty_array_for_non_existent_migration_all(): void {
		// Act.
		$executions = Migration_Executions::get_all_by( 'migration_id', 'non_existent_migration_' . uniqid() );

		// Assert.
		$this->assertIsArray( $executions );
		$this->assertEmpty( $executions );
	}

	/**
	 * @test
	 */
	public function it_should_retrieve_execution_by_status(): void {
		// Arrange.
		$migration_id_running   = 'test_status_running_' . uniqid();
		$migration_id_completed = 'test_status_completed_' . uniqid();

		Migration_Executions::insert(
			[
				'migration_id'    => $migration_id_running,
				'start_date'      => current_time( 'mysql', true ),
				'status'          => Status::RUNNING()->getValue(),
				'items_total'     => 100,
				'items_processed' => 50,
			]
		);

		Migration_Executions::insert(
			[
				'migration_id'    => $migration_id_completed,
				'start_date'      => current_time( 'mysql', true ),
				'end_date'        => current_time( 'mysql', true ),
				'status'          => Status::COMPLETED()->getValue(),
				'items_total'     => 100,
				'items_processed' => 100,
			]
		);

		// Act.
		$running_execution   = Migration_Executions::get_first_by( 'migration_id', $migration_id_running );
		$completed_execution = Migration_Executions::get_first_by( 'migration_id', $migration_id_completed );

		// Assert.
		$this->assertNotNull( $running_execution );
		$this->assertEquals( Status::RUNNING()->getValue(), $running_execution['status'] );

		$this->assertNotNull( $completed_execution );
		$this->assertEquals( Status::COMPLETED()->getValue(), $completed_execution['status'] );
	}
}

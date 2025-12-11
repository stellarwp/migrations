<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tables\Migration_Events;

class Migration_Events_Test extends WPTestCase {
	/**
	 * @test
	 */
	public function it_should_insert_a_migration_event(): void {
		$result = Migration_Events::insert(
			[
				'migration_id' => 'test_migration',
				'type'         => Migration_Events::TYPE_SCHEDULED,
				'data'         => [
					'args' => [ 'up', 'test_migration', 1 ],
				],
			]
		);

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );
	}

	/**
	 * @test
	 */
	public function it_should_retrieve_event_by_migration_id(): void {
		Migration_Events::insert(
			[
				'migration_id' => 'test_retrieval',
				'type'         => Migration_Events::TYPE_SCHEDULED,
				'data'         => [ 'foo' => 'bar' ],
			]
		);

		$event = Migration_Events::get_first_by( 'migration_id', 'test_retrieval' );

		$this->assertNotNull( $event );
		$this->assertEquals( 'test_retrieval', $event['migration_id'] );
		$this->assertEquals( Migration_Events::TYPE_SCHEDULED, $event['type'] );
	}

	/**
	 * @test
	 */
	public function it_should_retrieve_all_events_by_migration_id(): void {
		$migration_id = 'test_all_events_' . uniqid();

		Migration_Events::insert(
			[
				'migration_id' => $migration_id,
				'type'         => Migration_Events::TYPE_SCHEDULED,
				'data'         => [],
			]
		);

		Migration_Events::insert(
			[
				'migration_id' => $migration_id,
				'type'         => Migration_Events::TYPE_BATCH_STARTED,
				'data'         => [],
			]
		);

		Migration_Events::insert(
			[
				'migration_id' => $migration_id,
				'type'         => Migration_Events::TYPE_COMPLETED,
				'data'         => [],
			]
		);

		$events = Migration_Events::get_all_by( 'migration_id', $migration_id );

		$this->assertCount( 3, $events );
	}

	/**
	 * @test
	 */
	public function it_should_store_json_data(): void {
		$migration_id = 'test_json_' . uniqid();
		$data         = [
			'args'    => [ 'up', 'test_migration', 1 ],
			'message' => 'Test message',
			'nested'  => [
				'foo' => 'bar',
			],
		];

		Migration_Events::insert(
			[
				'migration_id' => $migration_id,
				'type'         => Migration_Events::TYPE_SCHEDULED,
				'data'         => $data,
			]
		);

		$event = Migration_Events::get_first_by( 'migration_id', $migration_id );

		$this->assertNotNull( $event );
		$this->assertIsArray( $event['data'] );
		$this->assertEquals( $data['args'], $event['data']['args'] );
		$this->assertEquals( $data['message'], $event['data']['message'] );
		$this->assertEquals( $data['nested']['foo'], $event['data']['nested']['foo'] );
	}

	/**
	 * @test
	 */
	public function it_should_have_correct_event_type_constants(): void {
		$this->assertEquals( 'scheduled', Migration_Events::TYPE_SCHEDULED );
		$this->assertEquals( 'batch-started', Migration_Events::TYPE_BATCH_STARTED );
		$this->assertEquals( 'batch-completed', Migration_Events::TYPE_BATCH_COMPLETED );
		$this->assertEquals( 'completed', Migration_Events::TYPE_COMPLETED );
		$this->assertEquals( 'failed', Migration_Events::TYPE_FAILED );
	}

	/**
	 * @test
	 */
	public function it_should_record_created_at_timestamp(): void {
		$migration_id = 'test_timestamp_' . uniqid();

		$before = current_time( 'mysql' );

		Migration_Events::insert(
			[
				'migration_id' => $migration_id,
				'type'         => Migration_Events::TYPE_SCHEDULED,
				'data'         => [],
			]
		);

		$after = current_time( 'mysql' );

		$event = Migration_Events::get_first_by( 'migration_id', $migration_id );

		$this->assertNotNull( $event );
		$this->assertArrayHasKey( 'created_at', $event );

		// Convert created_at to string for comparison if it's a DateTime object.
		$created_at = $event['created_at'];
		if ( $created_at instanceof \DateTime ) {
			$created_at = $created_at->format( 'Y-m-d H:i:s' );
		}

		$this->assertGreaterThanOrEqual( $before, $created_at );
		$this->assertLessThanOrEqual( $after, $created_at );
	}

	/**
	 * @test
	 */
	public function it_should_return_null_for_non_existent_migration(): void {
		$event = Migration_Events::get_first_by( 'migration_id', 'non_existent_migration_' . uniqid() );

		$this->assertNull( $event );
	}

	/**
	 * @test
	 */
	public function it_should_return_empty_array_for_non_existent_migration_all(): void {
		$events = Migration_Events::get_all_by( 'migration_id', 'non_existent_migration_' . uniqid() );

		$this->assertIsArray( $events );
		$this->assertEmpty( $events );
	}
}

<?php
/**
 * Execution Model Tests.
 *
 * Tests for the Execution model class.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Models
 */

declare( strict_types=1 );

namespace StellarWP\Migrations\Tests\Models;

use DateTime;
use DateTimeInterface;
use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Enums\Status;
use StellarWP\Migrations\Models\Execution;

/**
 * Execution Model Tests.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Models
 */
class Execution_Test extends WPTestCase {
	/**
	 * @test
	 */
	public function it_should_create_execution_from_attributes(): void {
		$start_date = new DateTime( '2024-01-15 10:00:00' );
		$end_date   = new DateTime( '2024-01-15 10:05:00' );
		$created_at = new DateTime( '2024-01-15 09:55:00' );

		$attributes = [
			'id'              => 123,
			'migration_id'    => 'test_migration',
			'start_date_gmt'  => $start_date,
			'end_date_gmt'    => $end_date,
			'status'          => Status::COMPLETED()->getValue(),
			'items_total'     => 100,
			'items_processed' => 100,
			'created_at'      => $created_at,
		];

		$execution = new Execution( $attributes );

		$this->assertInstanceOf( Execution::class, $execution );
	}

	/**
	 * @test
	 */
	public function it_should_return_correct_id(): void {
		$execution = $this->create_test_execution( [ 'id' => 456 ] );

		$this->assertEquals( 456, $execution->get_id() );
	}

	/**
	 * @test
	 */
	public function it_should_return_correct_migration_id(): void {
		$execution = $this->create_test_execution( [ 'migration_id' => 'my_custom_migration' ] );

		$this->assertEquals( 'my_custom_migration', $execution->get_migration_id() );
	}

	/**
	 * @test
	 */
	public function it_should_return_start_date_as_datetime_interface(): void {
		$start_date = new DateTime( '2024-01-15 10:00:00' );
		$execution  = $this->create_test_execution( [ 'start_date_gmt' => $start_date ] );

		$result = $execution->get_start_date();

		$this->assertInstanceOf( DateTimeInterface::class, $result );
		$this->assertEquals( '2024-01-15 10:00:00', $result->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * @test
	 */
	public function it_should_return_null_for_null_start_date(): void {
		$execution = $this->create_test_execution( [ 'start_date_gmt' => null ] );

		$this->assertNull( $execution->get_start_date() );
	}

	/**
	 * @test
	 */
	public function it_should_return_end_date_as_datetime_interface(): void {
		$end_date  = new DateTime( '2024-01-15 10:05:00' );
		$execution = $this->create_test_execution( [ 'end_date_gmt' => $end_date ] );

		$result = $execution->get_end_date();

		$this->assertInstanceOf( DateTimeInterface::class, $result );
		$this->assertEquals( '2024-01-15 10:05:00', $result->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * @test
	 */
	public function it_should_return_null_for_null_end_date(): void {
		$execution = $this->create_test_execution( [ 'end_date_gmt' => null ] );

		$this->assertNull( $execution->get_end_date() );
	}

	/**
	 * @test
	 */
	public function it_should_return_status_as_status_enum(): void {
		$execution = $this->create_test_execution( [ 'status' => Status::RUNNING()->getValue() ] );

		$result = $execution->get_status();

		$this->assertInstanceOf( Status::class, $result );
		$this->assertEquals( Status::RUNNING()->getValue(), $result->getValue() );
	}

	/**
	 * @test
	 * @dataProvider status_provider
	 */
	public function it_should_handle_all_status_values( string $status_value ): void {
		$execution = $this->create_test_execution( [ 'status' => $status_value ] );

		$result = $execution->get_status();

		$this->assertInstanceOf( Status::class, $result );
		$this->assertEquals( $status_value, $result->getValue() );
	}

	/**
	 * Data provider for status values.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function status_provider(): array {
		return [
			'pending'   => [ Status::PENDING()->getValue() ],
			'scheduled' => [ Status::SCHEDULED()->getValue() ],
			'running'   => [ Status::RUNNING()->getValue() ],
			'completed' => [ Status::COMPLETED()->getValue() ],
			'failed'    => [ Status::FAILED()->getValue() ],
		];
	}

	/**
	 * @test
	 */
	public function it_should_return_correct_items_total(): void {
		$execution = $this->create_test_execution( [ 'items_total' => 500 ] );

		$this->assertEquals( 500, $execution->get_items_total() );
	}

	/**
	 * @test
	 */
	public function it_should_return_correct_items_processed(): void {
		$execution = $this->create_test_execution( [ 'items_processed' => 250 ] );

		$this->assertEquals( 250, $execution->get_items_processed() );
	}

	/**
	 * @test
	 */
	public function it_should_return_created_at_as_datetime_interface(): void {
		$created_at = new DateTime( '2024-01-15 09:55:00' );
		$execution  = $this->create_test_execution( [ 'created_at' => $created_at ] );

		$result = $execution->get_created_at();

		$this->assertInstanceOf( DateTimeInterface::class, $result );
		$this->assertEquals( '2024-01-15 09:55:00', $result->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * @test
	 */
	public function it_should_convert_to_array(): void {
		$start_date = new DateTime( '2024-01-15 10:00:00' );
		$end_date   = new DateTime( '2024-01-15 10:05:00' );
		$created_at = new DateTime( '2024-01-15 09:55:00' );

		$execution = $this->create_test_execution( [
			'id'              => 789,
			'migration_id'    => 'array_test_migration',
			'start_date_gmt'  => $start_date,
			'end_date_gmt'    => $end_date,
			'status'          => Status::COMPLETED()->getValue(),
			'items_total'     => 200,
			'items_processed' => 200,
			'created_at'      => $created_at,
		] );

		$result = $execution->to_array();

		$this->assertIsArray( $result );
		$this->assertEquals( 789, $result['id'] );
		$this->assertEquals( 'array_test_migration', $result['migration_id'] );
		$this->assertSame( $start_date, $result['start_date'] );
		$this->assertSame( $end_date, $result['end_date'] );
		$this->assertInstanceOf( Status::class, $result['status'] );
		$this->assertEquals( Status::COMPLETED()->getValue(), $result['status']->getValue() );
		$this->assertEquals( 200, $result['items_total'] );
		$this->assertEquals( 200, $result['items_processed'] );
		$this->assertSame( $created_at, $result['created_at'] );
	}

	/**
	 * @test
	 */
	public function it_should_handle_zero_items(): void {
		$execution = $this->create_test_execution( [
			'items_total'     => 0,
			'items_processed' => 0,
		] );

		$this->assertEquals( 0, $execution->get_items_total() );
		$this->assertEquals( 0, $execution->get_items_processed() );
	}

	/**
	 * @test
	 */
	public function it_should_handle_partially_processed_items(): void {
		$execution = $this->create_test_execution( [
			'items_total'     => 100,
			'items_processed' => 50,
		] );

		$this->assertEquals( 100, $execution->get_items_total() );
		$this->assertEquals( 50, $execution->get_items_processed() );
	}

	/**
	 * Create a test execution with optional overrides.
	 *
	 * @param array<string, mixed> $overrides Attributes to override defaults.
	 *
	 * @return Execution The test execution.
	 */
	private function create_test_execution( array $overrides = [] ): Execution {
		$defaults = [
			'id'              => 1,
			'migration_id'    => 'test_migration',
			'start_date_gmt'  => new DateTime( '2024-01-15 10:00:00' ),
			'end_date_gmt'    => new DateTime( '2024-01-15 10:05:00' ),
			'status'          => Status::COMPLETED()->getValue(),
			'items_total'     => 100,
			'items_processed' => 100,
			'created_at'      => new DateTime( '2024-01-15 09:55:00' ),
		];

		return new Execution( array_merge( $defaults, $overrides ) );
	}
}

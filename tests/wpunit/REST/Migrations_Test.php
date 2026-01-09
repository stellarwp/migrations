<?php
/**
 * REST API tests for the Migrations list endpoint.
 *
 * @since TBD
 *
 * @package StellarWP\Migrations\Tests\REST
 */

declare(strict_types=1);

namespace StellarWP\Migrations\Tests\REST;

use Closure;
use StellarWP\Migrations\Tests\TestCases\REST_Test_Case;

/**
 * REST API tests for the Migrations list endpoint.
 *
 * @since TBD
 *
 * @package StellarWP\Migrations\Tests\REST
 */
class Migrations_Test extends REST_Test_Case {

	/**
	 * @test
	 */
	public function it_should_return_401_for_guest_user(): void {
		wp_set_current_user( 0 );

		$response = $this->do_rest_api_request( '/migrations' );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * @test
	 */
	public function it_should_return_403_for_non_admin_user(): void {
		$user_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		$response = $this->do_rest_api_request( '/migrations' );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * @test
	 */
	public function it_should_return_403_for_editor_user(): void {
		$user_id = $this->factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $user_id );

		$response = $this->do_rest_api_request( '/migrations' );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * @test
	 */
	public function it_should_return_200_for_admin_user(): void {
		$this->set_admin_user();

		$response = $this->do_rest_api_request( '/migrations' );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * @test
	 */
	public function it_should_list_all_migrations(): void {
		$this->set_admin_user();

		$data = $this->assert_endpoint( '/migrations' );

		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );

		// Verify each migration has expected keys.
		foreach ( $data as $migration ) {
			$this->assertArrayHasKey( 'id', $migration );
			$this->assertArrayHasKey( 'label', $migration );
			$this->assertArrayHasKey( 'description', $migration );
			$this->assertArrayHasKey( 'tags', $migration );
			$this->assertArrayHasKey( 'total_batches', $migration );
			$this->assertArrayHasKey( 'can_run', $migration );
			$this->assertArrayHasKey( 'is_applicable', $migration );
			$this->assertArrayHasKey( 'status', $migration );
		}
	}

	/**
	 * @test
	 */
	public function it_should_filter_migrations_by_tag(): void {
		$this->set_admin_user();

		$data = $this->assert_endpoint( '/migrations', 'GET', 200, [ 'tags' => 'data' ] );

		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );

		// All returned migrations should have the 'data' tag.
		foreach ( $data as $migration ) {
			$this->assertContains( 'data', $migration['tags'], "Migration {$migration['id']} should have 'data' tag" );
		}
	}

	/**
	 * @test
	 */
	public function it_should_filter_migrations_by_multiple_tags(): void {
		$this->set_admin_user();

		$data = $this->assert_endpoint( '/migrations', 'GET', 200, [ 'tags' => 'data,test' ] );

		$this->assertIsArray( $data );

		// All returned migrations should have at least one of the tags.
		foreach ( $data as $migration ) {
			$has_data_tag = in_array( 'data', $migration['tags'], true );
			$has_test_tag = in_array( 'test', $migration['tags'], true );
			$this->assertTrue( $has_data_tag || $has_test_tag, "Migration {$migration['id']} should have 'data' or 'test' tag" );
		}
	}

	/**
	 * @test
	 */
	public function it_should_return_empty_array_for_nonexistent_tag(): void {
		$this->set_admin_user();

		$data = $this->assert_endpoint( '/migrations', 'GET', 200, [ 'tags' => 'nonexistent_tag_xyz' ] );

		$this->assertIsArray( $data );
		$this->assertEmpty( $data );
	}

	/**
	 * @test
	 */
	public function it_should_include_multi_batch_migration(): void {
		$this->set_admin_user();

		$data = $this->assert_endpoint( '/migrations' );

		$multi_batch = array_filter( $data, fn( $m ) => $m['id'] === 'tests_multi_batch_migration' );
		$this->assertNotEmpty( $multi_batch );

		$migration = array_values( $multi_batch )[0];
		// 15 items / 5 per batch = 3 batches.
		$this->assertEquals( 3, $migration['total_batches'] );
	}

	/**
	 * @test
	 */
	public function it_should_include_simple_migration(): void {
		$this->set_admin_user();

		$data = $this->assert_endpoint( '/migrations' );

		$simple = array_filter( $data, fn( $m ) => $m['id'] === 'tests_simple_migration' );
		$this->assertNotEmpty( $simple );

		$migration = array_values( $simple )[0];
		$this->assertEquals( 1, $migration['total_batches'] );
		$this->assertEquals( 'Simple Migration', $migration['label'] );
	}

	/**
	 * @test
	 */
	public function it_should_show_is_applicable_false_for_non_applicable_migration(): void {
		$this->set_admin_user();

		$data = $this->assert_endpoint( '/migrations', 'GET', 200, [ 'tags' => 'legacy' ] );

		$not_applicable = array_filter( $data, fn( $m ) => $m['id'] === 'tests_not_applicable_migration' );
		$this->assertNotEmpty( $not_applicable );

		$migration = array_values( $not_applicable )[0];
		$this->assertFalse( $migration['is_applicable'] );
	}

	/**
	 * @test
	 * @dataProvider different_user_roles_provider
	 */
	public function it_should_enforce_permissions_for_different_roles( Closure $fixture ): void {
		$fixture();

		$expected_code = $this->get_expected_status_code();
		$response      = $this->do_rest_api_request( '/migrations' );

		$this->assertEquals( $expected_code, $response->get_status() );
	}
}

<?php
declare( strict_types=1 );

namespace StellarWP\Migrations;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Tests\Migrations\Student_Meta_Migration;
use Generator;
use Closure;

/**
 * Tests using Student_Meta_Migration migration which operates on real database.
 *
 * These tests create actual dummy data, run the migration, and verify the results.
 */
class Student_Meta_Migration_Test extends WPTestCase {
	/**
	 * @before
	 * @after
	 */
	public function reset_state(): void {
		Config::get_container()->get( Registry::class )->flush();
		tests_migrations_clear_calls_data();
	}

	public function dummy_data_provider(): Generator {
		yield 'no data to migrate' => [
			function (): array {
				return [];
			},
		];

		yield 'single post course access from' => [
			function (): array {
				$uid = self::factory()->user->create( [ 'role' => 'contributor' ] );

				add_user_meta( $uid, 'user_id', $uid );
				add_user_meta( $uid, 'course_1_access_from', '2025-01-01' );

				return [ $uid ];
			},
		];

		yield 'single post group access from' => [
			function (): array {
				$uid = self::factory()->user->create( [ 'role' => 'contributor' ] );

				add_user_meta( $uid, 'user_id', $uid );
				add_user_meta( $uid, 'group_1_access_from', '2025-01-01' );

				return [ $uid ];
			},
		];

		yield 'multiple posts course access from' => [
			function (): array {
				$uids = self::factory()->user->create_many( 5, [ 'role' => 'contributor' ] );

				foreach ( $uids as $uid ) {
					add_user_meta( $uid, 'user_id', $uid );
					add_user_meta( $uid, 'course_1_access_from', '2025-01-01' );
				}

				return $uids;
			},
		];

		yield 'multiple posts group access from' => [
			function (): array {
				$uids = self::factory()->user->create_many( 5, [ 'role' => 'contributor' ] );

				foreach ( $uids as $uid ) {
					add_user_meta( $uid, 'user_id', $uid );
					add_user_meta( $uid, 'group_1_access_from', '2025-01-01' );
				}

				return $uids;
			},
		];

		yield 'multiple posts course and group access from' => [
			function (): array {
				$uids = self::factory()->user->create_many( 5, [ 'role' => 'contributor' ] );

				foreach ( $uids as $uid ) {
					add_user_meta( $uid, 'user_id', $uid );
					add_user_meta( $uid, 'course_1_access_from', '2025-01-01' );
					add_user_meta( $uid, 'group_1_access_from', '2025-01-01' );
				}

				return $uids;
			},
		];
	}

	/**
	 * @test
	 * @dataProvider dummy_data_provider
	 */
	public function it_should_migrate_multiple_posts_and_verify_values_preserved( Closure $fixture ): void {
		$uids = $fixture();

		foreach ( $uids as $uid ) {
			$this->assertEmpty( get_user_meta( $uid, 'learndash_student', true ) );
		}

		$registry  = Config::get_container()->get( Registry::class );
		$registry->register( 'tests_student_meta_migration', Student_Meta_Migration::class );

		$migration = $registry->get( 'tests_student_meta_migration' );

		$this->assertInstanceOf( Student_Meta_Migration::class, $migration );

		$this->assertEquals( count( $uids ), $migration->get_total_batches() );

		$prefix = Config::get_hook_prefix();
		do_action( "stellarwp_migrations_{$prefix}_schedule_migrations" );
		wp_cache_flush();

		foreach ( $uids as $uid ) {
			$this->assertEquals( '1', get_user_meta( $uid, 'learndash_student', true ) );
		}

		$this->assertTrue( $migration->is_up_done() );

		$calls = tests_migrations_get_calls_data();

		$count = count( $uids );
		$count = $count ? $count : 1;

		$this->assertCount( $count, $calls );
	}
}

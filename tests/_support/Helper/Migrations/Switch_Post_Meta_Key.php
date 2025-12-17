<?php
declare( strict_types=1 );

namespace StellarWP\Migrations\Tests\Migrations;

use StellarWP\Migrations\Abstracts\Migration_Abstract;
use StellarWP\DB\DB;

/**
 * A test migration that switches post meta keys from old to new.
 *
 * Processes one record per batch to demonstrate batched migrations.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Helper\Migrations
 */
class Switch_Post_Meta_Key extends Migration_Abstract {
	public const OLD_META_KEY = 'old_meta_key';
	public const NEW_META_KEY = 'new_meta_key';

	/**
	 * @var int[]
	 */
	private static array $created_post_ids = [];

	/**
	 * @var array<int, string>
	 */
	private static array $created_meta_values = [];

	public function get_total_items(): int {
		return count( self::$created_post_ids );
	}

	public function get_default_batch_size(): int {
		return 1;
	}

	public function get_label(): string {
		return 'Switch Post Meta Key Migration';
	}

	public function get_description(): string {
		return 'This migration switches the post meta key from old to new.';
	}

	public function is_applicable(): bool {
		return true;
	}

	public function is_up_done(): bool {
		$count = (int) DB::get_var(
			DB::prepare(
				'SELECT COUNT(*) FROM %i WHERE meta_key = %s',
				DB::prefix( 'postmeta' ),
				self::OLD_META_KEY
			)
		);

		return $count <= 0;
	}

	public function is_down_done(): bool {
		$count = (int) DB::get_var(
			DB::prepare(
				'SELECT COUNT(*) FROM %i WHERE meta_key = %s',
				DB::prefix( 'postmeta' ),
				self::NEW_META_KEY
			)
		);

		return $count <= 0;
	}

	public function up( int $batch, int $batch_size ): void {
		DB::query(
			DB::prepare(
				'UPDATE %i SET meta_key = %s WHERE meta_key = %s LIMIT %d',
				DB::prefix( 'postmeta' ),
				self::NEW_META_KEY,
				self::OLD_META_KEY,
				$batch_size
			)
		);
	}

	public function down( int $batch, int $batch_size ): void {
		DB::query(
			DB::prepare(
				'UPDATE %i SET meta_key = %s WHERE meta_key = %s LIMIT %d',
				DB::prefix( 'postmeta' ),
				self::OLD_META_KEY,
				self::NEW_META_KEY,
				$batch_size
			)
		);
	}

	/**
	 * Create dummy data for testing.
	 *
	 * @param int $count Number of posts to create with old meta key.
	 *
	 * @return array{post_ids: int[], meta_values: array<int, string>}
	 */
	public static function create_dummy_data( int $count = 3 ): array {
		global $wpdb;

		$post_ids    = [];
		$meta_values = [];

		for ( $i = 1; $i <= $count; $i++ ) {
			$post_id = wp_insert_post(
				[
					'post_title'  => "Test Post for Migration {$i}",
					'post_status' => 'publish',
					'post_type'   => 'post',
				]
			);

			$meta_value = "test_value_{$i}_" . uniqid();

			add_post_meta( $post_id, self::OLD_META_KEY, $meta_value );

			$post_ids[]              = $post_id;
			$meta_values[ $post_id ] = $meta_value;

			self::$created_post_ids[]              = $post_id;
			self::$created_meta_values[ $post_id ] = $meta_value;
		}

		return [
			'post_ids'    => $post_ids,
			'meta_values' => $meta_values,
		];
	}

	/**
	 * Verify the migration ran correctly (up).
	 *
	 * @return array{success: bool, old_count: int, new_count: int, values_preserved: bool, errors: string[]}
	 */
	public static function verify_up_results(): array {
		global $wpdb;

		$errors = [];

		// Clean post meta cache since we're using direct SQL updates.
		foreach ( self::$created_post_ids as $post_id ) {
			wp_cache_delete( $post_id, 'post_meta' );
		}

		// Count old meta keys (should be 0).
		$old_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::OLD_META_KEY
			)
		);

		if ( $old_count > 0 ) {
			$errors[] = "Expected 0 old meta keys, found {$old_count}";
		}

		// Count new meta keys.
		$new_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::NEW_META_KEY
			)
		);

		$expected_count = count( self::$created_meta_values );
		if ( $new_count !== $expected_count ) {
			$errors[] = "Expected {$expected_count} new meta keys, found {$new_count}";
		}

		// Verify values were preserved.
		$values_preserved = true;
		foreach ( self::$created_meta_values as $post_id => $expected_value ) {
			$actual_value = get_post_meta( $post_id, self::NEW_META_KEY, true );
			if ( $actual_value !== $expected_value ) {
				$values_preserved = false;
				$errors[]         = "Post {$post_id}: expected value '{$expected_value}', got '{$actual_value}'";
			}
		}

		return [
			'success'          => empty( $errors ),
			'old_count'        => $old_count,
			'new_count'        => $new_count,
			'values_preserved' => $values_preserved,
			'errors'           => $errors,
		];
	}

	/**
	 * Verify the migration was rolled back correctly (down).
	 *
	 * @return array{success: bool, old_count: int, new_count: int, values_preserved: bool, errors: string[]}
	 */
	public static function verify_down_results(): array {
		global $wpdb;

		$errors = [];

		// Clean post meta cache since we're using direct SQL updates.
		foreach ( self::$created_post_ids as $post_id ) {
			wp_cache_delete( $post_id, 'post_meta' );
		}

		// Count new meta keys (should be 0 after rollback).
		$new_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::NEW_META_KEY
			)
		);

		if ( $new_count > 0 ) {
			$errors[] = "Expected 0 new meta keys after rollback, found {$new_count}";
		}

		// Count old meta keys.
		$old_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::OLD_META_KEY
			)
		);

		$expected_count = count( self::$created_meta_values );
		if ( $old_count !== $expected_count ) {
			$errors[] = "Expected {$expected_count} old meta keys after rollback, found {$old_count}";
		}

		// Verify values were preserved.
		$values_preserved = true;
		foreach ( self::$created_meta_values as $post_id => $expected_value ) {
			$actual_value = get_post_meta( $post_id, self::OLD_META_KEY, true );
			if ( $actual_value !== $expected_value ) {
				$values_preserved = false;
				$errors[]         = "Post {$post_id}: expected value '{$expected_value}', got '{$actual_value}'";
			}
		}

		return [
			'success'          => empty( $errors ),
			'old_count'        => $old_count,
			'new_count'        => $new_count,
			'values_preserved' => $values_preserved,
			'errors'           => $errors,
		];
	}

	/**
	 * Get counts for verification.
	 *
	 * @return array{old_count: int, new_count: int, expected_count: int}
	 */
	public static function get_counts(): array {
		global $wpdb;

		$old_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::OLD_META_KEY
			)
		);

		$new_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				self::NEW_META_KEY
			)
		);

		return [
			'old_count'      => $old_count,
			'new_count'      => $new_count,
			'expected_count' => count( self::$created_meta_values ),
		];
	}

	/**
	 * Clean up all dummy data.
	 *
	 * @return void
	 */
	public static function cleanup_dummy_data(): void {
		global $wpdb;

		// Delete meta.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s OR meta_key = %s",
				self::OLD_META_KEY,
				self::NEW_META_KEY
			)
		);

		// Delete posts.
		foreach ( self::$created_post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		self::$created_post_ids    = [];
		self::$created_meta_values = [];
	}

	/**
	 * Reset static state.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$created_post_ids    = [];
		self::$created_meta_values = [];
	}
}

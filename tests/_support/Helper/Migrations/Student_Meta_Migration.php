<?php
/**
 * LearnDash Student Meta Migration.
 */

namespace StellarWP\Migrations\Tests\Migrations;

use StellarWP\Migrations\Abstracts\Migration_Abstract;
use StellarWP\DB\DB;
use StellarWP\DB\QueryBuilder\JoinQueryBuilder;
use StellarWP\DB\QueryBuilder\QueryBuilder;
use StellarWP\Migrations\Enums\Operation;

/**
 * Student Meta Migration class.
 *
 * @since 0.0.1
 */
class Student_Meta_Migration extends Migration_Abstract {
	/**
	 * Checks if the migration is applicable to the current site.
	 *
	 * @since 0.0.1
	 *
	 * @return bool True if the migration is applicable, false otherwise.
	 */
	public function is_applicable(): bool {
		return true;
	}

	/**
	 * Returns the total number of items for the migration.
	 *
	 * @since 0.0.1
	 *
	 * @return int Total number of items.
	 */
	public function get_total_items( ?Operation $operation = null ): int {
		return $this->get_distinct_user_count();
	}

	/**
	 * Returns the default batch size for the migration.
	 *
	 * @since 0.0.1
	 *
	 * @return int Default batch size.
	 */
	public function get_default_batch_size(): int {
		return 1;
	}

	/**
	 * Checks if the migration has been completed.
	 *
	 * @since 0.0.1
	 *
	 * @return bool True if the migration has been completed, false otherwise.
	 */
	public function is_up_done(): bool {
		return $this->get_distinct_user_count() === 0;
	}

	/**
	 * Gets the count of distinct users that need to be migrated.
	 *
	 * @since 0.0.1
	 *
	 * @return int Count of distinct users.
	 */
	private function get_distinct_user_count(): int {
		// We count distinct users because each user can have multiple matching meta rows.
		$user_ids = $this->get_base_query()
			->select( 'access_meta.user_id' )
			->distinct()
			->getAll();

		return is_array( $user_ids ) ? count( $user_ids ) : 0;
	}

	/**
	 * Checks if the migration has been rolled back.
	 *
	 * @since 0.0.1
	 *
	 * @return bool True if the migration has been rolled back, false otherwise.
	 */
	public function is_down_done(): bool {
		// No rollback is needed - we only add data. We always return that we are done.
		return true;
	}

	/**
	 * Executes the migration logic for a single batch.
	 *
	 * @since 0.0.1
	 *
	 * @param int $batch      The batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 */
	public function up( int $batch, int $batch_size ): void {
		$data = $this->get_base_query()
			->select( 'access_meta.user_id' )
			->distinct()
			->orderBy( 'access_meta.user_id', 'ASC' )
			->limit( $batch_size )
			->getAll();

		if ( empty( $data ) || ! is_array( $data ) ) {
			return;
		}

		foreach ( $data as $row ) {
			DB::table( 'usermeta' )
				->insert(
					[
						'user_id'    => $row->user_id,
						'meta_key'   => 'learndash_student',
						'meta_value' => '1',
					]
				);
		}
	}

	public function down( int $batch, int $batch_size ): void {
		// Not a destructive migration - we only add data. We don't need to rollback on failure.
	}

	/**
	 * Returns the base query for the migration.
	 *
	 * @since 0.0.1
	 *
	 * @return QueryBuilder The base query for the migration.
	 */
	public function get_base_query(): QueryBuilder {
		return DB::table( 'usermeta', 'access_meta' )
			->join(
				function ( JoinQueryBuilder $builder ) {
					$builder
					->leftJoin( 'usermeta', 'student_meta' )
					->on( 'access_meta.user_id', 'student_meta.user_id' )
					->andOn( 'student_meta.meta_key', 'learndash_student', true );
				}
			)
			->where(
				function ( $query ) {
					$query->whereLike( 'access_meta.meta_key', 'course_%_access_from' )
						->orWhereLike( 'access_meta.meta_key', 'group_%_access_from' );
				}
			)
			->whereIsNull( 'student_meta.user_id' );
	}

	/**
	 * Returns the human-readable migration label.
	 *
	 * @since 0.0.1
	 *
	 * @return string Migration label.
	 */
	public function get_label(): string {
		return __( 'Student Meta Migration', 'stellarwp-migrations' );
	}

	/**
	 * Returns the migration description.
	 *
	 * @since 0.0.1
	 *
	 * @return string Migration description.
	 */
	public function get_description(): string {
		return __( 'Improves the way to identify students.', 'stellarwp-migrations' );
	}

	/**
	 * Returns the list of tags for the migration.
	 *
	 * @since 0.0.1
	 *
	 * @return array<string> Array of tags.
	 */
	public function get_tags(): array {
		return [ 'student-management', 'usermeta' ];
	}
}

<?php
/**
 * LearnDash Student Meta Migration.
 */

namespace StellarWP\Migrations\Tests\Migrations;

use StellarWP\Migrations\Abstracts\Migration_Abstract;
use StellarWP\DB\DB;
use StellarWP\DB\QueryBuilder\JoinQueryBuilder;
use StellarWP\DB\QueryBuilder\QueryBuilder;

/**
 * Student Meta Migration class.
 *
 * @since TBD
 */
class Student_Meta_Migration extends Migration_Abstract {
	/**
	 * Returns the unique migration identifier.
	 *
	 * @since TBD
	 *
	 * @return string Migration slug.
	 */
	public function get_id(): string {
		return 'tests_student_meta_migration';
	}

	/**
	 * Checks if the migration is applicable to the current site.
	 *
	 * @since TBD
	 *
	 * @return bool True if the migration is applicable, false otherwise.
	 */
	public function is_applicable(): bool {
		return true;
	}

	/**
	 * Returns the total number of batches for the migration.
	 *
	 * @since TBD
	 *
	 * @return int Total number of batches.
	 */
	public function get_total_batches(): int {
		// We divide by 1 because we process one record per batch.
		return (int) $this->get_base_query()->count() / 1;
	}

	/**
	 * Checks if the migration has been completed.
	 *
	 * @since TBD
	 *
	 * @return bool True if the migration has been completed, false otherwise.
	 */
	public function is_up_done(): bool {
		return $this->get_base_query()->count() === 0;
	}

	/**
	 * Checks if the migration has been rolled back.
	 *
	 * @since TBD
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
	 * @since TBD
	 *
	 * @param int $batch The batch number.
	 *
	 * @return void
	 */
	public function up( int $batch ): void {
		$data = $this->get_base_query()
			->select( 'access_meta.user_id' )
			->orderBy( 'access_meta.user_id', 'ASC' )
			->limit( 1 )
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

	public function down( int $batch ): void {
		// Not a destructive migration - we only add data. We don't need to rollback on failure.
	}

	/**
	 * Returns the base query for the migration.
	 *
	 * @since TBD
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
	 * @since TBD
	 *
	 * @return string Migration label.
	 */
	public function get_label(): string {
		return __( 'Student Meta Migration', 'stellarwp-migrations' );
	}

	/**
	 * Returns the migration description.
	 *
	 * @since TBD
	 *
	 * @return string Migration description.
	 */
	public function get_description(): string {
		return __( 'Improves the way to identify students.', 'stellarwp-migrations' );
	}


	/**
	 * Checks if the migration can be run again after completion.
	 *
	 * @since TBD
	 *
	 * @return bool True if the migration can be run again after completion, false otherwise.
	 */
	public function is_repeatable(): bool {
		return true;
	}

	/**
	 * Returns the list of tags for the migration.
	 *
	 * @since TBD
	 *
	 * @return array<string> Array of tags.
	 */
	public function get_tags(): array {
		return [ 'student-management', 'usermeta' ];
	}
}

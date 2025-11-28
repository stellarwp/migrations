<?php
declare( strict_types=1 );

namespace StellarWP\Migrations\Tests\Migrations;

use StellarWP\Migrations\Abstracts\Migration_Abstract;
use StellarWP\DB\DB;

/**
 * Switch Post Meta Key Migration
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Helper\Migrations
 */
class Switch_Post_Meta_Key extends Migration_Abstract {
	public const OLD_META_KEY = 'old_meta_key';
	public const NEW_META_KEY = 'new_meta_key';

	public function get_id(): string {
		return 'tests_switch_post_meta_key';
	}

	public function is_applicable(): bool {
		$has_this_site_ever_had_version_X_Y_Z_of_the_product = true;

		return $has_this_site_ever_had_version_X_Y_Z_of_the_product;
	}

	public function is_up_done(): bool {
		$count = (int) DB::get_var(
			DB::prepare(
				"SELECT COUNT(*) FROM %i WHERE meta_key = %s",
				DB ::prefix( 'postmeta' ),
				self::OLD_META_KEY
			)
		);

		return $count <= 0;
	}

	public function is_down_done(): bool {
		$count = (int) DB::get_var(
			DB::prepare(
				"SELECT COUNT(*) FROM %i WHERE meta_key = %s",
				DB ::prefix( 'postmeta' ),
				self::NEW_META_KEY
			)
		);

		return $count <= 0;
	}

	public function up( int $batch ): void {
		DB::query(
			DB::prepare(
				"UPDATE %i SET meta_key = %s WHERE meta_key = %s LIMIT 1",
				DB ::prefix( 'postmeta' ),
				self::NEW_META_KEY,
				self::OLD_META_KEY
			)
		);
	}

	public function down( int $batch ): void {
		DB::query(
			DB::prepare(
				"UPDATE %i SET meta_key = %s WHERE meta_key = %s LIMIT 1",
				DB ::prefix( 'postmeta' ),
				self::OLD_META_KEY,
				self::NEW_META_KEY
			)
		);
	}
}

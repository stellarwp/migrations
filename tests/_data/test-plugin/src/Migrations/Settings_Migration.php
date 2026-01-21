<?php
/**
 * Settings Migration for testing.
 *
 * Migrates plugin settings from old format to new format.
 *
 * @since 0.0.1
 *
 * @package Test_Plugin\Migrations
 */

declare( strict_types=1 );

namespace Test_Plugin\Migrations;

use StellarWP\Migrations\Abstracts\Migration_Abstract;
use StellarWP\Migrations\Enums\Operation;

/**
 * A migration that converts old settings to a new format.
 *
 * This is a quick, single-batch migration useful for testing simple migrations.
 *
 * @since 0.0.1
 */
class Settings_Migration extends Migration_Abstract {
	/**
	 * Old settings option key.
	 *
	 * @var string
	 */
	protected const OLD_OPTION_KEY = 'test_plugin_old_settings';

	/**
	 * New settings option key.
	 *
	 * @var string
	 */
	protected const NEW_OPTION_KEY = 'test_plugin_new_settings';

	/**
	 * Get the migration label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return 'Settings Format Migration';
	}

	/**
	 * Get the migration description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return 'Converts plugin settings from the legacy format to the new structured format. This is a quick, single-batch operation.';
	}

	/**
	 * Get migration tags.
	 *
	 * @return array<string>
	 */
	public function get_tags(): array {
		return [ 'settings', 'configuration' ];
	}

	/**
	 * Get total items to process.
	 *
	 * @param Operation|null $operation The operation type.
	 *
	 * @return int
	 */
	public function get_total_items( ?Operation $operation = null ): int {
		return 1;
	}

	/**
	 * Get default batch size.
	 *
	 * @return int
	 */
	public function get_default_batch_size(): int {
		return 1;
	}

	/**
	 * Whether this migration is applicable.
	 *
	 * Only applicable if old settings exist and new settings don't.
	 *
	 * @return bool
	 */
	public function is_applicable(): bool {
		return get_option( self::OLD_OPTION_KEY ) !== false;
	}

	/**
	 * Check if the migration has completed running up.
	 *
	 * @return bool
	 */
	public function is_up_done(): bool {
		return get_option( self::NEW_OPTION_KEY ) !== false;
	}

	/**
	 * Check if the migration has completed rolling back.
	 *
	 * @return bool
	 */
	public function is_down_done(): bool {
		return get_option( self::NEW_OPTION_KEY ) === false;
	}

	/**
	 * Run the migration up - convert old settings to new format.
	 *
	 * @param int $batch      The current batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 */
	public function up( int $batch, int $batch_size ): void {
		$old_settings = get_option( self::OLD_OPTION_KEY, [] );

		// Convert flat settings to structured format.
		$new_settings = [
			'general'     => [
				'enabled' => $old_settings['enabled'] ?? true,
				'name'    => $old_settings['name'] ?? 'Default',
			],
			'advanced'    => [
				'debug'     => $old_settings['debug'] ?? false,
				'log_level' => $old_settings['log_level'] ?? 'info',
			],
			'migrated_at' => current_time( 'mysql' ),
		];

		update_option( self::NEW_OPTION_KEY, $new_settings );
	}

	/**
	 * Roll back the migration - remove new settings.
	 *
	 * @param int $batch      The current batch number.
	 * @param int $batch_size The batch size.
	 *
	 * @return void
	 */
	public function down( int $batch, int $batch_size ): void {
		delete_option( self::NEW_OPTION_KEY );
	}
}

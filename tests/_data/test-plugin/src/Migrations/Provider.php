<?php
/**
 * Migrations Provider for the Test Plugin.
 *
 * @since 0.0.1
 *
 * @package Test_Plugin\Migrations
 */

namespace Test_Plugin\Migrations;

use StellarWP\Shepherd\Abstracts\Provider_Abstract;
use StellarWP\Migrations\Registry;

/**
 * Registers all test migrations for CLI integration testing.
 *
 * @since 0.0.1
 */
class Provider extends Provider_Abstract {
	/**
	 * Register migrations with the registry.
	 *
	 * @return void
	 */
	public function register(): void {
		$registry = $this->container->get( Registry::class );

		$registry['tests_simple_migration']         = Simple_Migration::class;
		$registry['tests_multi_batch_migration']    = Multi_Batch_Migration::class;
		$registry['tests_not_applicable_migration'] = Not_Applicable_Migration::class;
		$registry['tests_failing_migration']        = Failing_Migration::class;
	}
}

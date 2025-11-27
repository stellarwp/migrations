<?php
/**
 * Main Migrations Service Provider.
 *
 * @package StellarWP\Migrations
 */

declare(strict_types=1);

namespace StellarWP\Migrations;

use StellarWP\Shepherd\Abstracts\Provider_Abstract;
use StellarWP\Shepherd\Provider as Shepherd_Provider;
use StellarWP\Shepherd\Config as Shepherd_Config;
use StellarWP\Migrations\Config;

/**
 * Main service provider for the Migrations library.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Provider
 */
class Provider extends Provider_Abstract {

	/**
	 * Whether the provider has been registered.
	 *
	 * @since 0.0.1
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Register the service provider.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function register(): void {
		if ( Shepherd_Provider::is_registered() ) {
			$this->register_migrations();
			return;
		}

		$prefix = Config::get_hook_prefix();

		Shepherd_Config::set_container( $this->container );
		Shepherd_Config::set_hook_prefix( $prefix );

		$this->container->get( Shepherd_Provider::class )->register();

		if ( has_action( "shepherd_{$prefix}_tables_registered", [ $this, 'register_migrations' ] ) ) {
			return;
		}

		add_action( "shepherd_{$prefix}_tables_registered", [ $this, 'register_migrations' ] );
	}

	/**
	 * Register migrations.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function register_migrations(): void {
		if ( self::is_registered() ) {
			return;
		}

		$prefix = Config::get_hook_prefix();

		if ( ! did_action( "shepherd_{$prefix}_tables_registered" ) && ! doing_action( "shepherd_{$prefix}_tables_registered" ) ) {
			add_action( "shepherd_{$prefix}_tables_registered", [ $this, 'register_migrations' ] );
			return;
		}

		self::$registered = true;

		// Register implementations here.
	}

	/**
	 * Check if the provider is registered.
	 *
	 * @since 0.0.1
	 *
	 * @return bool
	 */
	public static function is_registered(): bool {
		return self::$registered;
	}

	/**
	 * Reset registration status. Primarily for testing.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$registered = false;
		Shepherd_Config::reset();
	}
}

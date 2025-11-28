<?php
use StellarWP\Migrations\Tests\Container;
use StellarWP\ContainerContract\ContainerInterface;
use StellarWP\DB\DB;
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Provider;

/**
 * Resets the config.
 *
 * @return void
 */
function tests_migrations_reset_config(): void {
	Config::reset();
	Config::set_hook_prefix( tests_migrations_get_hook_prefix() );
	Config::set_container( tests_migrations_get_container() );
}

/**
 * Get the hook prefix.
 *
 * @return string
 */
function tests_migrations_get_hook_prefix(): string {
	return 'foobar';
}

/**
 * Gets a container instance for tests.
 *
 * @return ContainerInterface
 */
function tests_migrations_get_container(): ContainerInterface {
	static $container = null;

	if ( null === $container ) {
		$container = new Container();
		$container->bind( ContainerInterface::class, $container );
	}

	return $container;
}

/**
 * Enables fake transactions.
 *
 * @return void
 */
function tests_migrations_fake_transactions_enable() {
	uopz_set_return( DB::class, 'beginTransaction', true, false );
	uopz_set_return( DB::class, 'rollback', true, false );
	uopz_set_return( DB::class, 'commit', true, false );
}

/**
 * Disables fake transactions.
 *
 * @return void
 */
function tests_migrations_fake_transactions_disable() {
	uopz_unset_return( DB::class, 'beginTransaction' );
	uopz_unset_return( DB::class, 'rollback' );
	uopz_unset_return( DB::class, 'commit' );
}

/**
 * Bootstraps the common test environment.
 *
 * @return void
 */
function tests_migrations_common_bootstrap(): void {
	tests_migrations_reset_config();
	tests_migrations_fake_transactions_enable();

	$container = Config::get_container();

	add_action( 'shepherd_' . Config::get_hook_prefix() . '_tables_error', '__return_true' );

	// Bootstrap and register Migrations.
	$container->singleton( Provider::class );
	$container->get( Provider::class )->register();
}

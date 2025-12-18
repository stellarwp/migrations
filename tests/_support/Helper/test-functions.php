<?php
use StellarWP\Migrations\Tests\Container;
use StellarWP\ContainerContract\ContainerInterface;
use StellarWP\DB\DB;
use StellarWP\Migrations\Config;
use StellarWP\Shepherd\Config as Shepherd_Config;
use StellarWP\Migrations\Provider;
use StellarWP\Migrations\Tasks\Execute;
use StellarWP\Migrations\Tables\Migration_Executions;
use StellarWP\Migrations\Tables\Migration_Logs;
use StellarWP\Shepherd\Tables\Utility\Safe_Dynamic_Prefix;
/**
 * Drop the tables before and after the suite.
 *
 * @return void
 */
function tests_migrations_drop_tables() {
	$container           = tests_migrations_get_container();
	$safe_dynamic_prefix = $container->get( Safe_Dynamic_Prefix::class );

	$tables        = [];
	$table_classes = [
		Migration_Executions::class,
		Migration_Logs::class,
	];

	$longest_table_name = $safe_dynamic_prefix->get_longest_table_name( $table_classes );

	foreach ( $table_classes as $table_class ) {
		$tables[] = sprintf( $table_class::raw_base_table_name(), $safe_dynamic_prefix->get( $longest_table_name ) );
	}

	foreach ( $tables as $table ) {
		DB::query(
			DB::prepare( 'DROP TABLE IF EXISTS %i', DB::prefix( $table ) )
		);
	}
}

/**
 * Raises the auto increment for the tables.
 *
 * @return void
 */
function tests_migrations_raise_auto_increment(): void {
	$tables = [
		Migration_Executions::base_table_name(),
		Migration_Logs::base_table_name(),
	];

	foreach ( $tables as $offset => $table ) {
		DB::query(
			DB::prepare( 'ALTER TABLE %i AUTO_INCREMENT = %d', DB::prefix( $table ), 728365 + ( 1 + (int) $offset * 3 ) )
		);
	}
}

/**
 * Resets the config.
 *
 * @return void
 */
function tests_migrations_reset_config(): void {
	Shepherd_Config::reset();
	Config::reset();
	Shepherd_Config::set_hook_prefix( tests_migrations_get_hook_prefix() );
	Shepherd_Config::set_container( tests_migrations_get_container() );
	Shepherd_Config::set_max_table_name_length( 64 );
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
	tests_migrations_drop_tables();
	tests_migrations_fake_transactions_enable();

	$container = Config::get_container();

	add_action( 'shepherd_' . Config::get_hook_prefix() . '_tables_error', '__return_true' );

	// Bootstrap and register Migrations.
	$container->singleton( Provider::class );
	$container->get( Provider::class )->register();

	// Raise the auto increment for the tables.
	tests_migrations_raise_auto_increment();

	// We don't test shepherd functionality here, we expect it to work as expected.
	tests_migrations_shepherd_sync_enable();

	// Drop the tables after the tests are done.
	tests_add_filter(
		'shutdown',
		'tests_migrations_drop_tables',
		10000
	);
}

/**
 * Data for the migrations execute calls.
 *
 * @var array
 */
$GLOBALS['stellarwp_migrations_execute_calls_data'] = [];

/**
 * Callback for the migrations execute calls.
 *
 * @var callable
 */
$GLOBALS['stellarwp_migrations_execute_callback'] = function ( $handler, $task ) {
	if ( ! $task instanceof Execute ) {
		return $handler;
	}

	return function () use ( $task ) {
		global $stellarwp_migrations_execute_calls_data;
		$stellarwp_migrations_execute_calls_data[] = $task;

		$task->process();
	};
};

/**
 * Get the calls data.
 *
 * @return array
 */
function tests_migrations_get_calls_data(): array {
	global $stellarwp_migrations_execute_calls_data;
	return $stellarwp_migrations_execute_calls_data;
}

/**
 * Clear the calls data.
 *
 * @return void
 */
function tests_migrations_clear_calls_data(): void {
	global $stellarwp_migrations_execute_calls_data;
	$stellarwp_migrations_execute_calls_data = [];
}

/**
 * Enables the migrations execute calls.
 *
 * @return void
 */
function tests_migrations_shepherd_sync_enable() {
	global $stellarwp_migrations_execute_callback;
	$prefix = tests_migrations_get_hook_prefix();
	add_filter( "shepherd_{$prefix}_dispatch_handler", $stellarwp_migrations_execute_callback, 10, 2 );
}

/**
 * Disables the migrations execute calls.
 *
 * @return void
 */
function tests_migrations_shepherd_sync_disable() {
	global $stellarwp_migrations_execute_callback;
	$prefix = tests_migrations_get_hook_prefix();
	remove_filter( "shepherd_{$prefix}_dispatch_handler", $stellarwp_migrations_execute_callback );
}

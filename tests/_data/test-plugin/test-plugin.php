<?php
/**
 * Test Plugin
 *
 * A test plugin for testing the migrations library.
 *
 * @package Test_Plugin
 *
 * @wordpress-plugin
 * Plugin Name: Test Plugin
 * Description: A test plugin for testing the migrations library.
 * Version:     0.0.1
 * Author:      StellarWP
 * Author URI:  https://stellarwp.com
 * License:     GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

use StellarWP\Migrations\Provider;
use StellarWP\Migrations\Config;
use StellarWP\ContainerContract\ContainerInterface;
use Test_Plugin\Migrations\Provider as MigrationsProvider;
use Test_Plugin\Admin\Provider as AdminProvider;
use Test_Plugin\Container;

function test_plugin_get_container(): ContainerInterface {
	static $container = null;

	if ( null === $container ) {
		$container = new Container();
		$container->bind( ContainerInterface::class, $container );
	}

	return $container;
}

add_action(
	'plugins_loaded',
	function () {
		$container = test_plugin_get_container();

		Config::set_container( $container );
		Config::set_hook_prefix( 'bar' );

		$container->register( Provider::class );
		$container->register( MigrationsProvider::class );
		$container->register( AdminProvider::class );
	}
);

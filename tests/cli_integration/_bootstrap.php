<?php

use lucatume\WPBrowser\Events\Dispatcher;
use Codeception\Events;
use Codeception\Event\SuiteEvent;

function tests_migrations_cli_integration_get_prefix(): string {
	return 'bar';
}

Dispatcher::addListener(
	Events::SUITE_BEFORE,
	function ( SuiteEvent $suiteEvent ) {
		codecept_debug( 'Installing test plugin on SUITE BEFORE' );

		$source      = codecept_root_dir( 'tests/_data/test-plugin' );
		$destination = codecept_root_dir( '../../mu-plugins' );

		// Copy test plugin to mu-plugins.
		$result = shell_exec( 'mkdir -p ' . $destination . '/test-plugin && cp -R ' . $source . ' ' . $destination );
		codecept_debug( 'Copied test plugin to mu-plugins: ' . $result );

		$destination .= '/test-plugin';

		$result = shell_exec( 'ls -la ' . $destination );
		codecept_debug( 'ls -la ' . $destination . ': ' . $result );

		// Run composer install to get fresh dependencies with correct paths.
		$result = shell_exec( 'composer install --working-dir=' . $destination . ' 2>&1' );
		codecept_debug( 'Composer install: ' . $result );

		// Create include file for mu-plugin loading.
		$php = "<?php require_once __DIR__ . '/test-plugin/test-plugin.php';";

		$result = shell_exec(
			'echo "' . $php . '" > ' . codecept_root_dir( '../../mu-plugins/include-test-plugin.php' )
		);

		codecept_debug( 'Created include file: ' . $result );
	}
);

Dispatcher::addListener(
	Events::SUITE_AFTER,
	function ( SuiteEvent $suiteEvent ) {
		codecept_debug( 'Removing test plugin on SUITE AFTER' );

		$result = shell_exec( 'rm -rf ' . codecept_root_dir( '../../mu-plugins/test-plugin' ) );

		codecept_debug( $result );

		$result = shell_exec( 'rm -rf ' . codecept_root_dir( '../../mu-plugins/include-test-plugin.php' ) );

		codecept_debug( $result );
	}
);

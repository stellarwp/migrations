<?php

use lucatume\WPBrowser\Events\Dispatcher;
use Codeception\Events;
use Codeception\Event\SuiteEvent;

function tests_migrations_cli_integration_get_prefix(): string {
	return 'bar';
}

Dispatcher::addListener(Events::SUITE_BEFORE, function (SuiteEvent $suiteEvent) {
	codecept_debug( 'Installing test plugin on SUITE BEFORE' );
	$result = shell_exec( 'cp -R ' . codecept_root_dir( 'tests/_data/test-plugin' ) . ' ' . codecept_root_dir( '../../mu-plugins/' ) );

	codecept_debug( $result );

	$result = shell_exec( 'composer install --working-dir=' . codecept_root_dir( '../../mu-plugins/test-plugin/' ) );

	codecept_debug( $result );

	$php = "<?php require_once __DIR__ . '/test-plugin/test-plugin.php';";

	$result = shell_exec(
		'echo "' . $php . '" > ' . codecept_root_dir( '../../mu-plugins/include-test-plugin.php' )
	);

	codecept_debug( $result );
});

Dispatcher::addListener(Events::SUITE_AFTER, function (SuiteEvent $suiteEvent) {
	codecept_debug('Removing test plugin on SUITE AFTER');

	$result = shell_exec( 'rm -rf ' . codecept_root_dir( '../../mu-plugins/test-plugin' ) );

	codecept_debug( $result );

	$result = shell_exec( 'rm -rf ' . codecept_root_dir( '../../mu-plugins/include-test-plugin.php' ) );

	codecept_debug( $result );
});

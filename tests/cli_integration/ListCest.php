<?php

namespace StellarWP\Migrations;

use Cli_integrationTester;
use tad\Codeception\SnapshotAssertions\SnapshotAssertions;

class ListCest {
	/**
	 * @test
	 */
	public function it_should_list_all_migrations( Cli_integrationTester $I ) {
		$output = $I->cliToString( [ tests_migrations_cli_integration_get_prefix(), 'migrations', 'list', '--format=json' ] );
	}
}

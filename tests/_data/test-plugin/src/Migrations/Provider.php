<?php

namespace Test_Plugin\Migrations;

use StellarWP\Shepherd\Abstracts\Provider_Abstract;
use StellarWP\Migrations\Registry;

class Provider extends Provider_Abstract {
	public function register(): void {
		$registry = $this->container->get( Registry::class );

		$registry['tests_simple_migration'] = Simple_Migration::class;
	}
}

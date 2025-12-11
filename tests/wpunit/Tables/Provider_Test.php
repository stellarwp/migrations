<?php
declare( strict_types=1 );

namespace StellarWP\Migrations\Tables;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Config;
use StellarWP\Shepherd\Tables\Utility\Safe_Dynamic_Prefix;

class Provider_Test extends WPTestCase {
	/**
	 * @test
	 */
	public function it_should_use_different_instances_for_safe_dynamic_prefix(): void {
		$container            = Config::get_container();
		$safe_dynamic_prefix1 = $container->get( Safe_Dynamic_Prefix::class );
		$safe_dynamic_prefix2 = $container->get( Provider::get_safe_dynamic_prefix_implementation_id() );

		$this->assertNotSame( $safe_dynamic_prefix1, $safe_dynamic_prefix2 );
	}
}

<?php
/**
 * Test Plugin's container for tests.
 *
 * @since 0.0.1
 *
 * @package Test_Plugin
 */

declare( strict_types=1 );

namespace Test_Plugin;

use StellarWP\ContainerContract\ContainerInterface;
use lucatume\DI52\Container as DI52_Container;

/**
 * Test Plugin's container for tests.
 *
 * @since 0.0.1
 *
 * @package Test_Plugin
 */
class Container extends DI52_Container implements ContainerInterface {}

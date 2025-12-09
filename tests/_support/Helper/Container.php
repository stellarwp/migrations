<?php
/**
 * Shepherd's container for tests.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests
 */

declare( strict_types=1 );

namespace StellarWP\Migrations\Tests;

use StellarWP\ContainerContract\ContainerInterface;

use lucatume\DI52\Container as DI52_Container;

/**
 * Migrations's container for tests.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests
 */
class Container extends DI52_Container implements ContainerInterface {}

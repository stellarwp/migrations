<?php
/**
 * Migrations's functions.
 *
 * @package StellarWP\Migrations
 */

declare( strict_types=1 );

namespace StellarWP\Migrations;

use RuntimeException;
use StellarWP\Migrations\Config;

/**
 * Get the Migrations's Provider instance.
 *
 * @since 0.0.1
 *
 * @return Provider The Migrations's Provider.
 *
 * @throws RuntimeException If Migrations is not registered.
 */
function migrations(): Provider {
	if ( ! Provider::is_registered() ) {
		throw new RuntimeException( 'Migrations is not registered.' );
	}

	static $migrations = null;

	if ( null !== $migrations ) {
		return $migrations;
	}

	$migrations = Config::get_container()->get( Provider::class );

	return $migrations;
}

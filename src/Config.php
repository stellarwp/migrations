<?php
/**
 * Migrations Config
 *
 * @package StellarWP\Migrations
 */

declare(strict_types=1);

namespace StellarWP\Migrations;

use RuntimeException;
use StellarWP\ContainerContract\ContainerInterface;

/**
 * Migrations Config
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations
 */
class Config {
	/**
	 * Container object.
	 *
	 * @since 0.0.1
	 *
	 * @var ?ContainerInterface
	 */
	protected static ?ContainerInterface $container = null;

	/**
	 * The hook prefix.
	 *
	 * @since 0.0.1
	 *
	 * @var string
	 */
	protected static string $hook_prefix;

	/**
	 * Get the container.
	 *
	 * @since 0.0.1
	 *
	 * @throws RuntimeException If the container is not set.
	 *
	 * @return ContainerInterface
	 */
	public static function get_container(): ContainerInterface {
		if ( self::$container === null ) {
			throw new RuntimeException( 'You must provide a container via StellarWP\Migrations\Config::set_container() before attempting to fetch it.' );
		}

		return self::$container;
	}

	/**
	 * Gets the hook prefix.
	 *
	 * @since 0.0.1
	 *
	 * @throws RuntimeException If the hook prefix is not set.
	 *
	 * @return string
	 */
	public static function get_hook_prefix(): string {
		if ( ! static::$hook_prefix ) {
			$class = self::class;
			throw new RuntimeException( "You must specify a hook prefix for your project with {$class}::set_hook_prefix()" );
		}

		return static::$hook_prefix;
	}

	/**
	 * Set the container object.
	 *
	 * @since 0.0.1
	 *
	 * @param ContainerInterface $container Container object.
	 *
	 * @return void
	 */
	public static function set_container( ContainerInterface $container ) {
		self::$container = $container;
	}

	/**
	 * Sets the hook prefix.
	 *
	 * @since 0.0.1
	 *
	 * @param string $prefix The prefix to add to hooks.
	 *
	 * @throws RuntimeException If the hook prefix is empty.
	 *
	 * @return void
	 */
	public static function set_hook_prefix( string $prefix ): void {
		if ( ! $prefix ) {
			throw new RuntimeException( 'The hook prefix cannot be empty.' );
		}

		static::$hook_prefix = $prefix;
	}

	/**
	 * Resets the config.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public static function reset(): void {
		static::$container   = null;
		static::$hook_prefix = '';
	}
}

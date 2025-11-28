<?php
/**
 * Migrations Registry.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Registry
 */

declare(strict_types=1);

namespace StellarWP\Migrations;

use StellarWP\Migrations\Contracts\Migration;
use ArrayAccess;
use Iterator;
use Countable;
use RuntimeException;

/**
 * Migrations Registry.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Registry
 */
class Registry implements ArrayAccess, Iterator, Countable {
	/**
	 * Collection of items.
	 *
	 * @since 0.0.1
	 *
	 * @var array<Migration>
	 */
	protected array $resources = [];

	/**
	 * Constructor.
	 *
	 * @since 0.0.1
	 *
	 * @param array<Migration> $resources An array of items.
	 */
	public function __construct( array $resources = [] ) {
		foreach ( $resources as $offset => $value ) {
			$this->set( (string) $offset, $value );
		}
	}

	/**
	 * Sets a value in the collection.
	 *
	 * @since 0.0.1
	 *
	 * @param string    $offset The offset to set.
	 * @param Migration $value  The value to set.
	 */
	protected function set( string $offset, Migration $value ): void {
		$prefix = Config::get_hook_prefix();
		if ( did_action( "stellarwp_migrations_{$prefix}_schedule_migrations", $value ) ) {
			throw new RuntimeException( esc_html__( 'Too late to add a migration to the registry.', 'stellarwp-migrations' ) );
		}

		$this->resources[ $offset ] = $value;
	}

	/**
	 * @inheritDoc
	 */
	public function current(): Migration {
		return current( $this->resources );
	}

	/**
	 * @inheritDoc
	 */
	public function key(): ?string {
		return (string) key( $this->resources );
	}

	/**
	 * @inheritDoc
	 */
	public function next(): void {
		next( $this->resources );
	}

	/**
	 * @inheritDoc
	 *
	 * @param TKey $offset The offset to check.
	 *
	 * @return bool
	 */
	public function offsetExists( $offset ): bool {
		return array_key_exists( $offset, $this->resources );
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $offset The offset to get.
	 *
	 * @return Migration|null
	 */
	public function offsetGet( $offset ): ?Migration {
		return $this->resources[ $offset ] ?? null;
	}

	/**
	 * @inheritDoc
	 *
	 * @param string    $offset The offset to set.
	 * @param Migration $value  The value to set.
	 */
	public function offsetSet( $offset, $value ): void {
		if ( ! $offset ) {
			$offset = (string) count( $this->resources );
		}
		$this->set( $offset, $value );
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $offset The offset to unset.
	 */
	public function offsetUnset( $offset ): void {
		unset( $this->resources[ $offset ] );
	}

	/**
	 * @inheritDoc
	 */
	public function rewind(): void {
		reset( $this->resources );
	}

	/**
	 * @inheritDoc
	 */
	public function valid(): bool {
		return key( $this->resources ) !== null;
	}

	/**
	 * @inheritDoc
	 */
	public function count(): int {
		return count( $this->resources );
	}

	/**
	 * Get a migration by its ID.
	 *
	 * @since 0.0.1
	 *
	 * @param string $migration_id The migration ID.
	 *
	 * @return Migration|null
	 */
	public function get( string $migration_id ): ?Migration {
		foreach ( $this->resources as $migration ) {
			if ( $migration->get_id() === $migration_id ) {
				return $migration;
			}
		}

		return null;
	}
}

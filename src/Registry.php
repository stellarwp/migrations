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
	protected array $migrations = [];

	/**
	 * Constructor.
	 *
	 * @since 0.0.1
	 *
	 * @param array<Migration> $migrations An array of migrations.
	 *
	 * @return void
	 *
	 * @throws RuntimeException If the migration is not a valid migration.
	 */
	public function __construct( array $migrations = [] ) {
		foreach ( $migrations as $migration ) {
			if ( ! $migration instanceof Migration ) {
				throw new RuntimeException( 'You should pass an array of migrations to the Registry constructor.' );
			}

			$this->register( $migration );
		}
	}

	/**
	 * Sets a value in the collection.
	 *
	 * @since 0.0.1
	 *
	 * @param Migration $migration The migration to register.
	 *
	 * @return void
	 *
	 * @throws RuntimeException If the migration is too late to be registered.
	 */
	public function register( Migration $migration ): void {
		$prefix = Config::get_hook_prefix();
		if ( did_action( "stellarwp_migrations_{$prefix}_schedule_migrations" ) ) {
			_doing_it_wrong( __FUNCTION__, 'Too late to add a migration to the registry.', '0.0.1' );
			return;
		}

		$migration_id = $migration->get_id();
		if ( strlen( $migration_id ) > 191 ) {
			throw new RuntimeException( "Migration ID {$migration_id} is too long." );
		}

		$this->migrations[ $migration_id ] = $migration;
	}

	/**
	 * @inheritDoc
	 */
	public function current(): Migration {
		return current( $this->migrations );
	}

	/**
	 * @inheritDoc
	 */
	public function key(): ?string {
		return (string) key( $this->migrations );
	}

	/**
	 * @inheritDoc
	 */
	public function next(): void {
		next( $this->migrations );
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $migration_id The migration id to check.
	 *
	 * @return bool
	 */
	public function offsetExists( $migration_id ): bool {
		return array_key_exists( $migration_id, $this->migrations );
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $offset The offset to get.
	 *
	 * @return Migration|null
	 */
	public function offsetGet( $offset ): ?Migration {
		return $this->migrations[ $offset ] ?? null;
	}

	/**
	 * @inheritDoc
	 *
	 * @param string    $offset The offset to set.
	 * @param Migration $value  The value to set.
	 */
	public function offsetSet( $offset, $value ): void {
		$this->register( $value );
	}

	/**
	 * @inheritDoc
	 *
	 * @param string $migration_id The migration id to unset.
	 */
	public function offsetUnset( $migration_id ): void {
		unset( $this->migrations[ $migration_id ] );
	}

	/**
	 * @inheritDoc
	 */
	public function rewind(): void {
		reset( $this->migrations );
	}

	/**
	 * @inheritDoc
	 */
	public function valid(): bool {
		return key( $this->migrations ) !== null;
	}

	/**
	 * @inheritDoc
	 */
	public function count(): int {
		return count( $this->migrations );
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
		return $this->migrations[ $migration_id ] ?? null;
	}

	/**
	 * Flush the registry.
	 *
	 * @since 0.0.1
	 *
	 * @return void
	 */
	public function flush(): void {
		$this->migrations = [];
	}
}

# Migration Contract

This document describes the `Migration` interface and `Migration_Abstract` base class.

## Interface: `Migration`

All migrations must implement `StellarWP\Migrations\Contracts\Migration`.

### Methods

#### `is_applicable(): bool`

Determines whether the migration should run on the current site. This should return a consistent value regardless of whether the migration has run.

```php
public function is_applicable(): bool {
    // Only run if a specific option exists.
    return get_option( 'my_plugin_needs_migration' ) === 'yes';
}
```

#### `is_up_done(): bool`

Returns `true` when the migration has fully completed. The library calls this after each batch to determine whether to continue.

```php
public function is_up_done(): bool {
    global $wpdb;
    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE meta_key = %s",
            $wpdb->postmeta,
            'old_key'
        )
    ) === 0;
}
```

#### `is_down_done(): bool`

Returns `true` when the rollback has fully completed.

```php
public function is_down_done(): bool {
    global $wpdb;
    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE meta_key = %s",
            $wpdb->postmeta,
            'new_key'
        )
    ) === 0;
}
```

#### `up( int $batch ): void`

Executes the migration logic for a single batch. Process a fixed number of records per call.

```php
public function up( int $batch ): void {
    global $wpdb;
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE %i SET meta_key = %s WHERE meta_key = %s LIMIT 100",
            $wpdb->postmeta,
            'new_key',
            'old_key'
        )
    );
}
```

#### `down( int $batch ): void`

Reverts the migration logic for a single batch.

```php
public function down( int $batch ): void {
    global $wpdb;
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE %i SET meta_key = %s WHERE meta_key = %s LIMIT 100",
            $wpdb->postmeta,
            'old_key',
            'new_key'
        )
    );
}
```

#### `before( int $batch, string $context ): void`

Called before each batch executes. The `$context` parameter is either `'up'` or `'down'`.

```php
public function before( int $batch, string $context ): void {
    // Custom pre-batch logic.
}
```

#### `after( int $batch, string $context, bool $is_complete ): void`

Called after each batch executes. The `$is_complete` parameter indicates whether the migration/rollback has finished.

```php
public function after( int $batch, string $context, bool $is_complete ): void {
    if ( $is_complete ) {
        // Cleanup or notification logic.
    }
}
```

#### `get_up_extra_args_for_batch( int $batch ): array`

Returns extra arguments to be passed to the `up()` method for a specific batch. This enables migrations to pass dynamic, batch-specific data to their processing methods.

The `$batch` parameter is the batch number about to be processed. The returned array values are spread as additional arguments to the `up()` method.

```php
public function get_up_extra_args_for_batch( int $batch ): array {
    // Return batch-specific data for the up migration.
    return [ $this->get_items_for_batch( $batch ) ];
}
```

#### `get_down_extra_args_for_batch( int $batch ): array`

Returns extra arguments to be passed to the `down()` method for a specific batch. This enables migrations to pass dynamic, batch-specific data to their rollback methods.

The `$batch` parameter is the batch number about to be processed. The returned array values are spread as additional arguments to the `down()` method.

```php
public function get_down_extra_args_for_batch( int $batch ): array {
    // Return batch-specific data for the down rollback.
    return [ $this->get_items_for_batch( $batch ) ];
}
```

When extra arguments are provided, your `up()` and `down()` methods should accept them as variadic parameters:

```php
public function up( int $batch, ...$extra_args ): void {
    $items = $extra_args[0] ?? [];
    foreach ( $items as $item ) {
        // Process item.
    }
}

public function down( int $batch, ...$extra_args ): void {
    $items = $extra_args[0] ?? [];
    foreach ( $items as $item ) {
        // Revert item.
    }
}
```

## Abstract Class: `Migration_Abstract`

`StellarWP\Migrations\Abstracts\Migration_Abstract` provides default empty implementations for `before()`, `after()`, `get_up_extra_args_for_batch()`, and `get_down_extra_args_for_batch()`. Extend this class to avoid implementing these methods when not needed.

```php
use StellarWP\Migrations\Abstracts\Migration_Abstract;

class My_Migration extends Migration_Abstract {
    public function is_applicable(): bool {
        return true;
    }

    public function is_up_done(): bool {
        // Implementation.
    }

    public function is_down_done(): bool {
        // Implementation.
    }

    public function up( int $batch ): void {
        // Implementation.
    }

    public function down( int $batch ): void {
        // Implementation.
    }
}
```

## Registry

The `Registry` class stores and manages migrations. It implements `ArrayAccess`, `Iterator`, and `Countable`.

### Registering Migrations

```php
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Registry;

$registry = Config::get_container()->get( Registry::class );

// Via register method.
$registry->register( new My_Migration() );

// Via array access.
$registry[] = new My_Migration();
```

### Retrieving Migrations

```php
$migration = $registry->get( 'my_migration_id' );

// Or via array access.
$migration = $registry['my_migration_id'];
```

### Constraints

- Migration IDs must be 191 characters or fewer.
- Migrations cannot be registered after the `stellarwp_migrations_{prefix}_schedule_migrations` action has fired.

## Migration Events Table

The library tracks migration state in a database table with the following event types:

| Type | Description |
|------|-------------|
| `scheduled` | Migration was queued for execution. |
| `batch-started` | A batch began processing. |
| `batch-completed` | A batch finished (more batches remain). |
| `completed` | The migration fully completed. |
| `failed` | A batch failed with an exception. |

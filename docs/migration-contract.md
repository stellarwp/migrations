# Migration Contract

This document describes the `Migration` interface and `Migration_Abstract` base class.

## Interface: `Migration`

All migrations must implement `StellarWP\Migrations\Contracts\Migration`.

### Methods

#### `get_label(): string`

Returns a human-readable label for the migration.

```php
public function get_label(): string {
    return 'Rename Meta Key';
}
```

#### `get_description(): string`

Returns a description of what the migration does.

```php
public function get_description(): string {
    return 'Renames the old_key meta key to new_key for all posts.';
}
```

#### `get_total_items(): int`

Returns the total number of items to process for the migration. Used for progress tracking.

```php
public function get_total_items(): int {
    global $wpdb;

    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE meta_key = %s",
            $wpdb->postmeta,
            'old_key'
        )
    );

    return $count;
}
```

#### `is_applicable(): bool`

Determines whether the migration should run on the current site. This should return a consistent value regardless of whether the migration has run.

```php
public function is_applicable(): bool {
    // Only run if a specific option exists.
    return get_option( 'my_plugin_needs_migration' ) === 'yes';
}
```

#### `can_run(): bool`

Determines whether the migration can currently run. Unlike `is_applicable()`, this can change based on runtime conditions.

```php
public function can_run(): bool {
    // Only run during off-peak hours.
    $hour = (int) date( 'G' );
    return $hour < 6 || $hour > 22;
}
```

Default implementation returns `true`.

#### `get_number_of_retries_per_batch(): int`

Returns the number of times to retry a failed batch before giving up.

```php
public function get_number_of_retries_per_batch(): int {
    return 3;
}
```

Default implementation returns `0`.

#### `get_default_batch_size(): int`

Returns the default number of items to process per batch. This value is used when the migration is initially scheduled.

```php
public function get_default_batch_size(): int {
    return 100;
}
```

#### `get_tags(): array`

Returns an array of tags for categorizing or filtering migrations.

```php
public function get_tags(): array {
    return [ 'database', 'meta' ];
}
```

Default implementation returns an empty array.

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

#### `up( int $batch, int $batch_size ): void`

Executes the migration logic for a single batch. Process a fixed number of records per call.

```php
public function up( int $batch, int $batch_size ): void {
    global $wpdb;
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE %i SET meta_key = %s WHERE meta_key = %s LIMIT %d",
            $wpdb->postmeta,
            'new_key',
            'old_key',
            $batch_size
        )
    );
}
```

#### `down( int $batch, int $batch_size ): void`

Reverts the migration logic for a single batch.

```php
public function down( int $batch, int $batch_size ): void {
    global $wpdb;
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE %i SET meta_key = %s WHERE meta_key = %s LIMIT %d",
            $wpdb->postmeta,
            'old_key',
            'new_key',
            $batch_size
        )
    );
}
```

#### `before_up( int $batch, int $batch_size ): void`

Called before each batch of the migration executes.

```php
public function before_up( int $batch, int $batch_size ): void {
    // Custom pre-batch logic for migrations.
}
```

#### `after_up( int $batch, int $batch_size, bool $is_completed ): void`

Called after each batch of the migration executes. The `$is_completed` parameter indicates whether the migration has finished.

```php
public function after_up( int $batch, int $batch_size, bool $is_completed ): void {
    if ( $is_completed ) {
        // Cleanup or notification logic.
    }
}
```

#### `before_down( int $batch, int $batch_size ): void`

Called before each batch of the rollback executes.

```php
public function before_down( int $batch, int $batch_size ): void {
    // Custom pre-batch logic for rollbacks.
}
```

#### `after_down( int $batch, int $batch_size, bool $is_completed ): void`

Called after each batch of the rollback executes. The `$is_completed` parameter indicates whether the rollback has finished.

```php
public function after_down( int $batch, int $batch_size, bool $is_completed ): void {
    if ( $is_completed ) {
        // Cleanup or notification logic.
    }
}
```

#### `get_up_extra_args_for_batch( int $batch, int $batch_size ): array`

Returns extra arguments to be passed to the `up()` method for a specific batch. This enables migrations to pass dynamic, batch-specific data to their processing methods.

The `$batch` parameter is the batch number about to be processed. The `$batch_size` parameter is the number of items to process in this batch. The returned array values are spread as additional arguments to the `up()` method.

```php
public function get_up_extra_args_for_batch( int $batch, int $batch_size ): array {
    // Return batch-specific data for the up migration.
    return [ $this->get_items_for_batch( $batch, $batch_size ) ];
}
```

#### `get_down_extra_args_for_batch( int $batch, int $batch_size ): array`

Returns extra arguments to be passed to the `down()` method for a specific batch. This enables migrations to pass dynamic, batch-specific data to their rollback methods.

The `$batch` parameter is the batch number about to be processed. The `$batch_size` parameter is the number of items to process in this batch. The returned array values are spread as additional arguments to the `down()` method.

```php
public function get_down_extra_args_for_batch( int $batch, int $batch_size ): array {
    // Return batch-specific data for the down rollback.
    return [ $this->get_items_for_batch( $batch, $batch_size ) ];
}
```

When extra arguments are provided, your `up()` and `down()` methods should accept them as variadic parameters:

```php
public function up( int $batch, int $batch_size, ...$extra_args ): void {
    $items = $extra_args[0] ?? [];
    foreach ( $items as $item ) {
        // Process item.
    }
}

public function down( int $batch, int $batch_size, ...$extra_args ): void {
    $items = $extra_args[0] ?? [];
    foreach ( $items as $item ) {
        // Revert item.
    }
}
```

## Abstract Class: `Migration_Abstract`

`StellarWP\Migrations\Abstracts\Migration_Abstract` provides default implementations for the following methods:

| Method                               | Default Value |
|--------------------------------------|---------------|
| `before_up()`                        | No-op         |
| `after_up()`                         | No-op         |
| `before_down()`                      | No-op         |
| `after_down()`                       | No-op         |
| `can_run()`                          | `true`        |
| `get_number_of_retries_per_batch()` | `0`           |
| `get_tags()`                         | `[]`          |
| `get_up_extra_args_for_batch()`     | `[]`          |
| `get_down_extra_args_for_batch()`   | `[]`          |

Extend this class to avoid implementing these methods when not needed.

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

    public function get_default_batch_size(): int {
        return 100;
    }

    public function up( int $batch, int $batch_size ): void {
        // Implementation.
    }

    public function down( int $batch, int $batch_size ): void {
        // Implementation.
    }
}
```

## Registry

The `Registry` class stores and manages migrations. It implements `ArrayAccess`, `Iterator`, and `Countable`.

### Registering Migrations

Migrations are registered using a unique ID and class name:

```php
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Registry;

$registry = Config::get_container()->get( Registry::class );

// Via register method.
$registry->register( 'my_plugin_migration', My_Migration::class );

// Via array access.
$registry['my_plugin_migration'] = My_Migration::class;

// Via constructor (for multiple migrations).
$registry = new Registry( [
    'my_plugin_migration_1' => My_Migration::class,
    'my_plugin_migration_2' => Another_Migration::class,
] );
```

### Retrieving Migrations

The registry returns a new instance of the migration class each time:

```php
$migration = $registry->get( 'my_plugin_migration' );

// Or via array access.
$migration = $registry['my_plugin_migration'];
```

### Constraints

- Migration IDs must be strings with a maximum of 191 characters.
- Migration values must be class-strings (fully qualified class names).
- Migrations cannot be registered after the `stellarwp_migrations_{prefix}_schedule_migrations` action has fired.

## Migration Logs

The library provides comprehensive logging capabilities for tracking migration execution and debugging issues.

### Log Types

The `Log_Type` enum provides the following log levels:

| Type | Description |
|------|-------------|
| `INFO` | Informational messages about migration progress. |
| `WARNING` | Warning messages for non-critical issues. |
| `ERROR` | Error messages for failures and exceptions. |
| `DEBUG` | Debug messages for troubleshooting. |

### Using the Logger

The `Logger` utility class makes it easy to add logs for a migration execution:

```php
use StellarWP\Migrations\Utilities\Logger;

// Create a logger for an execution.
$logger = Logger::for_execution( $execution_id );

// Log messages at different levels.
$logger->info( 'Processing batch 1' );
$logger->warning( 'Skipped invalid record', [ 'record_id' => 123 ] );
$logger->error( 'Failed to update record', [ 'error' => $e->getMessage() ] );
$logger->debug( 'Query result', [ 'count' => 50 ] );
```

### Logger Methods

- `info( string $message, ?array $data = null )` - Log informational messages
- `warning( string $message, ?array $data = null )` - Log warning messages
- `error( string $message, ?array $data = null )` - Log error messages
- `debug( string $message, ?array $data = null )` - Log debug messages

### Log Table Schema

Each log entry contains:

- `id` - Unique log entry ID
- `migration_execution_id` - Reference to the migration execution
- `type` - Log type (info, warning, error, debug)
- `message` - Human-readable log message (max 1024 characters)
- `data` - Optional JSON data for additional context
- `created_at` - Timestamp when the log was created

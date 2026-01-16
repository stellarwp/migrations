# Getting Started

This guide covers the basics of setting up and using the Migrations library.

## Installation

Install via Composer:

```bash
composer require stellarwp/migrations
```

## Configuration

Before using the library, configure it with a container and hook prefix:

```php
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Provider;

// Set your DI container (must implement StellarWP\ContainerContract\ContainerInterface).
Config::set_container( $container );

// Set a unique hook prefix for your plugin.
Config::set_hook_prefix( 'my_plugin' );

// Register the provider.
$container->get( Provider::class )->register();
```

## Creating a Migration

Extend `Migration_Abstract` and implement the required methods:

```php
use StellarWP\Migrations\Abstracts\Migration_Abstract;

class Rename_Meta_Key extends Migration_Abstract {
    public function is_applicable(): bool {
        // Return true if the pre-conditions are met. You should not consider if the migration has been run or not here.
        return true;
    }

    public function is_up_done(): bool {
        // Return true if the migration has completed.
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE meta_key = %s",
                $wpdb->postmeta,
                'old_key'
            )
        ) === 0;
    }

    public function is_down_done(): bool {
        // Return true if the rollback has completed.
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE meta_key = %s",
                $wpdb->postmeta,
                'new_key'
            )
        ) === 0;
    }

    public function get_default_batch_size(): int {
        return 100;
    }

    public function up( int $batch, int $batch_size ): void {
        // Perform the migration for this batch.
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

    public function down( int $batch, int $batch_size ): void {
        // Revert the migration for this batch.
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
}
```

## Registering Migrations

Register your migrations with the registry before the scheduling action fires. Each migration requires a unique ID (string) and the migration class name:

```php
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Registry;

$registry = Config::get_container()->get( Registry::class );
$registry->register( 'my_plugin_rename_meta_key', Rename_Meta_Key::class );
```

The migration ID must be:

- A unique string identifier (max 191 characters)
- Stable across requests (used to track migration state)

Migrations are automatically scheduled to run on the `shutdown` hook.

## Batched Migrations

For large datasets, implement batched migrations by processing a subset of data in each `up()` call. The library will continue calling `up()` with incrementing batch numbers until `is_up_done()` returns `true`.

The `$batch_size` parameter contains the number of items to process in each batch, which is determined by the `get_default_batch_size()` method if not overwritten.

```php
public function get_default_batch_size(): int {
    return 100;
}

public function up( int $batch, int $batch_size ): void {
    // Process batch_size records per batch.
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

## Lifecycle Hooks

Override the lifecycle methods for custom logic around each batch:

```php
public function before_up( int $batch, int $batch_size ): void {
    // Runs before each batch of the migration.
    error_log( "Starting up batch {$batch} with size {$batch_size}" );
}

public function after_up( int $batch, int $batch_size, bool $is_completed ): void {
    // Runs after each batch of the migration.
    if ( $is_completed ) {
        error_log( 'Migration completed' );
    }
}

public function before_down( int $batch, int $batch_size ): void {
    // Runs before each batch of the rollback.
    error_log( "Starting down batch {$batch} with size {$batch_size}" );
}

public function after_down( int $batch, int $batch_size, bool $is_completed ): void {
    // Runs after each batch of the rollback.
    if ( $is_completed ) {
        error_log( 'Rollback completed' );
    }
}
```

## Logging

The library provides a Logger utility that is automatically bound to the container during migration execution. You can access it from within your migration to log custom messages:

```php
use StellarWP\Migrations\Abstracts\Migration_Abstract;
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Utilities\Logger;

class My_Migration extends Migration_Abstract {
    public function up( int $batch, int $batch_size ): void {
        $logger = Config::get_container()->get( Logger::class );

        // Log informational messages.
        $logger->info( 'Processing batch', [ 'batch' => $batch ] );

        // Perform migration work.
        global $wpdb;
        $updated = $wpdb->query( /* ... */ );

        // Log success or warnings.
        if ( $updated ) {
            $logger->info( "Updated {$updated} records" );
        } else {
            $logger->warning( 'No records were updated' );
        }
    }
}
```

Available log levels:

- `$logger->info( $message, $data )` - Informational messages
- `$logger->warning( $message, $data )` - Warning messages
- `$logger->error( $message, $data )` - Error messages
- `$logger->debug( $message, $data )` - Debug messages

All logs are stored in the migration logs table and are associated with the current migration execution. The `$data` parameter is optional and accepts any serializable data structure.

## Log Cleanup

The library automatically cleans up old migration logs to prevent the database from growing indefinitely. This is handled by the `Clear_Logs` Shepherd task.

**Default Behavior:**

- Logs are retained for **180 days** (6 months) by default
- The `Clear_Logs` task runs periodically via Shepherd
- Old logs are deleted, but a summary log entry is created for each processed execution

**Customizing Retention Period:**

You can customize the retention period using the `stellarwp_migrations_{prefix}_log_retention_days` filter:

```php
add_filter( 'stellarwp_migrations_my_plugin_log_retention_days', function( int $retention_days ) {
    // Retain logs for 90 days instead of the default 180 days.
    return 90;
} );
```

**What Gets Cleaned Up:**

1. All logs for migration executions older than the retention period are deleted
2. A summary log entry is created for each processed execution, indicating:
   - When the logs were deleted
   - The migration execution status at the time of cleanup
   - The retention period that was applied

**Manual Cleanup:**

The cleanup task runs automatically via Shepherd. If you need to trigger it manually, you can dispatch the task programmatically:

```php
use StellarWP\Migrations\Tasks\Clear_Logs;
use StellarWP\Shepherd\Regulator;

$regulator = Config::get_container()->get( Regulator::class );
$task      = new Clear_Logs();
$regulator->dispatch( $task );
```

See the [Hooks Reference](./hooks.md) for more details on the `stellarwp_migrations_{prefix}_log_retention_days` filter.

## Failure Handling

If a migration throws an exception during `up()`, the library automatically:

1. Records the failure in the migration events table.
2. Dispatches a rollback task to execute `down()`.

Failures during `down()` are recorded but do not trigger additional rollbacks.

## Next Steps

- [Migration Contract](./migration-contract.md) - Full API reference
- [CLI Reference](./cli.md) - WP-CLI commands for migrations
- [REST API Reference](./rest-api.md) - REST API endpoints for programmatic access
- [Hooks Reference](./hooks.md) - Available actions and filters

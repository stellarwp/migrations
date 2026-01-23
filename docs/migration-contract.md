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

#### `get_total_items( ?Operation $operation = null ): int`

Returns the total number of items to process for the migration. Used for progress tracking.

The optional `$operation` parameter allows returning different counts for `up` vs `down` operations. When `null` is passed, `Operation::UP()` is assumed.

```php
use StellarWP\Migrations\Enums\Operation;

public function get_total_items( ?Operation $operation = null ): int {
    global $wpdb;

    // Use Operation::UP() as default if null.
    $operation = $operation ?? Operation::UP();

    // Return different counts based on operation.
    if ( $operation->equals( Operation::DOWN() ) ) {
        // Count items that need to be rolled back.
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE meta_key = %s",
                $wpdb->postmeta,
                'new_key'
            )
        );
    }

    // Default: count items for migration (up).
    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE meta_key = %s",
            $wpdb->postmeta,
            'old_key'
        )
    );
}
```

For simple migrations where the count is the same for both operations, you can ignore the parameter:

```php
public function get_total_items( ?Operation $operation = null ): int {
    global $wpdb;

    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE meta_key = %s",
            $wpdb->postmeta,
            'old_key'
        )
    );
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

#### `get_total_batches( int $batch_size, ?Operation $operation = null ): int`

Returns the total number of batches for the migration. This is calculated from `get_total_items()` divided by the batch size, rounded up.

The optional `$operation` parameter is passed through to `get_total_items()` to support different batch counts for `up` vs `down` operations.

```php
use StellarWP\Migrations\Enums\Operation;

// The default implementation in Migration_Abstract:
public function get_total_batches( int $batch_size, ?Operation $operation = null ): int {
    return (int) ceil( $this->get_total_items( $operation ) / $batch_size );
}
```

You typically don't need to override this method unless you have custom batching logic.

#### `get_status(): Status`

Returns the current status of the migration based on its most recent execution. Used by the CLI and for reporting.

The default implementation in `Migration_Abstract` queries the `Migration_Executions` table to find the latest execution for this migration and returns its status. If no executions exist, it returns `Status::PENDING()`.

```php
use StellarWP\Migrations\Enums\Status;

public function get_status(): Status {
    // The default implementation queries the last execution.
    // Returns Status::PENDING() if no executions exist.
}
```

**Available Status Values:**

| Status                     | Description                                     |
| -------------------------- | ----------------------------------------------- |
| `Status::PENDING()`        | Migration has not started                       |
| `Status::SCHEDULED()`      | Migration has been scheduled                    |
| `Status::RUNNING()`        | Migration is currently running                  |
| `Status::COMPLETED()`      | Migration finished successfully                 |
| `Status::FAILED()`         | Migration failed                                |
| `Status::PAUSED()`         | Migration is paused                             |
| `Status::CANCELED()`       | Migration was canceled                          |
| `Status::NOT_APPLICABLE()` | Migration is not applicable to the current site |

**Note:** The `get_status()` method requires the migration ID to be set (via the constructor) to query the execution history. If `is_applicable()` returns `false`, the status will be `NOT_APPLICABLE`.

#### `get_latest_execution(): ?Execution`

Returns the most recent execution for this migration as an `Execution` model, or `null` if no executions exist.

```php
use StellarWP\Migrations\Models\Execution;

$execution = $migration->get_latest_execution();

if ( $execution ) {
    // Access execution data via getter methods.
    $status = $execution->get_status();           // Status enum
    $items_processed = $execution->get_items_processed();
    $items_total = $execution->get_items_total();
    $start_date = $execution->get_start_date();   // DateTimeInterface|null
    $end_date = $execution->get_end_date();       // DateTimeInterface|null
}
```

The `Execution` model provides a type-safe way to access execution data. See the [Execution Model](#execution-model) section for full details.

#### `to_array(): array`

Converts the migration to an array representation. This is used by the CLI commands and implements `JsonSerializable`.

```php
public function to_array(): array {
    return [
        'label'         => $this->get_label(),
        'description'   => $this->get_description(),
        'tags'          => $this->get_tags(),
        'total_batches' => $this->get_total_batches( $this->get_default_batch_size() ),
        'can_run'       => $this->can_run(),
        'is_applicable' => $this->is_applicable(),
        'status'        => $this->get_status(),
    ];
}
```

The `Migration` interface extends `JsonSerializable`, so migrations can be directly serialized to JSON via `json_encode()`.

## Operation Enum

The `Operation` enum (`StellarWP\Migrations\Enums\Operation`) represents the migration direction:

| Value               | Description                   |
| ------------------- | ----------------------------- |
| `Operation::UP()`   | Migration operation (forward) |
| `Operation::DOWN()` | Rollback operation (reverse)  |

Usage:

```php
use StellarWP\Migrations\Enums\Operation;

$operation = Operation::UP();

// Check the operation type.
if ( $operation->equals( Operation::DOWN() ) ) {
    // Handle rollback case.
}

// Get the string value ('up' or 'down').
$value = $operation->getValue(); // 'up'

// Get human-readable label.
$label = $operation->get_label(); // 'Up'
```

## Abstract Class: `Migration_Abstract`

`StellarWP\Migrations\Abstracts\Migration_Abstract` provides a base class that implements the `Migration` interface with sensible defaults.

### Constructor

The abstract class requires a migration ID to be passed to the constructor:

```php
public function __construct( string $migration_id )
```

This ID is used internally to query execution history and determine the current status. When using the `Registry`, the migration ID is automatically passed to the constructor when retrieving migrations.

### `get_id(): string`

Returns the migration ID that was passed to the constructor:

```php
$migration = $registry->get( 'my_plugin_migration' );
echo $migration->get_id(); // 'my_plugin_migration'
```

### Default Implementations

`Migration_Abstract` provides default implementations for the following methods:

| Method                              | Default Value                       |
| ----------------------------------- | ----------------------------------- |
| `before_up()`                       | No-op                               |
| `after_up()`                        | No-op                               |
| `before_down()`                     | No-op                               |
| `after_down()`                      | No-op                               |
| `can_run()`                         | `true`                              |
| `get_number_of_retries_per_batch()` | `0`                                 |
| `get_tags()`                        | `[]`                                |
| `get_up_extra_args_for_batch()`     | `[]`                                |
| `get_down_extra_args_for_batch()`   | `[]`                                |
| `get_total_batches()`               | Calculated from items/batch_size    |
| `get_status()`                      | Queries last execution or `PENDING` |
| `get_latest_execution()`            | Returns `Execution` model or `null` |
| `to_array()`                        | Array of migration properties       |
| `get_id()`                          | Returns the migration ID            |

Extend this class to avoid implementing these methods when not needed.

```php
use StellarWP\Migrations\Abstracts\Migration_Abstract;

class My_Migration extends Migration_Abstract {
    // The constructor receives the migration ID from the Registry.
    // You can add your own constructor if needed, but must call parent::__construct().
    public function __construct( string $migration_id ) {
        parent::__construct( $migration_id );
        // Your initialization code here.
    }

    public function get_label(): string {
        return 'My Migration';
    }

    public function get_description(): string {
        return 'Performs data transformation.';
    }

    public function is_applicable(): bool {
        return true;
    }

    public function is_up_done(): bool {
        // Implementation.
    }

    public function is_down_done(): bool {
        // Implementation.
    }

    public function get_total_items( ?Operation $operation = null ): int {
        return 1000;
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

**Note:** When extending `Migration_Abstract`, the constructor must accept the `$migration_id` parameter and pass it to the parent constructor. The Registry handles this automatically when retrieving migrations.

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

The registry returns a new instance of the migration class each time, automatically passing the migration ID to the constructor:

```php
$migration = $registry->get( 'my_plugin_migration' );
// Equivalent to: new My_Migration( 'my_plugin_migration' )

// Or via array access.
$migration = $registry['my_plugin_migration'];
```

### Filtering Migrations

The registry supports filtering migrations with a callback:

```php
// Get only migrations with a specific tag.
$data_migrations = $registry->filter( function( Migration $migration ) {
    return in_array( 'data', $migration->get_tags(), true );
} );

// Get only applicable migrations.
$applicable = $registry->filter( fn( Migration $m ) => $m->is_applicable() );
```

The `filter()` method returns a new `Registry` instance containing only the matching migrations.

### Getting All Migrations

To retrieve all migrations as an array:

```php
$all_migrations = $registry->all();

foreach ( $all_migrations as $migration_id => $migration ) {
    // $migration is a Migration instance.
    echo $migration->get_label();
}
```

### Constraints

- Migration IDs must be strings with a maximum of 191 characters.
- Migration values must be class-strings (fully qualified class names).
- Migrations cannot be registered after the `stellarwp_migrations_{prefix}_schedule_migrations` action has fired.

## Migration Logs

The library provides comprehensive logging capabilities for tracking migration execution and debugging issues.

### Log Types

The `Log_Type` enum provides the following log levels:

| Type      | Description                                      |
| --------- | ------------------------------------------------ |
| `INFO`    | Informational messages about migration progress. |
| `WARNING` | Warning messages for non-critical issues.        |
| `ERROR`   | Error messages for failures and exceptions.      |
| `DEBUG`   | Debug messages for troubleshooting.              |

### Using the Logger

The `Logger` utility class makes it easy to add logs for a migration execution:

```php
use StellarWP\Migrations\Utilities\Logger;

// Create a logger for an execution.
$logger = new Logger( $execution_id );

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

### Log Level Filtering

The logger implements a high-pass filter system to control which log messages are written to the database. This prevents excessive logging in production environments while allowing detailed logging during development or troubleshooting.

#### Log Level Hierarchy

Log levels are ordered by priority (from most verbose to least verbose):

1. `debug` - Detailed debugging information
2. `info` - Informational messages about migration progress
3. `warning` - Warning messages for non-critical issues
4. `error` - Error messages for failures and exceptions

When a minimum log level is set, only messages at that level or higher will be written to the database.

#### Default Behavior

The minimum log level is automatically determined based on the `WP_DEBUG` constant:

- **`WP_DEBUG = true`**: Minimum level is `debug` (all messages are logged)
- **`WP_DEBUG = false`**: Minimum level is `info` (debug messages are not logged)

#### Filtering Log Levels

You can customize the minimum log level using the `stellarwp_migrations_{prefix}_minimum_log_level` filter, where `{prefix}` is your configured hook prefix:

```php
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Enums\Log_Type;

$prefix = Config::get_hook_prefix();

add_filter( "stellarwp_migrations_{$prefix}_minimum_log_level", function( Log_Type $minimum_log_level ) {
    // Only log warnings and errors.
    return Log_Type::WARNING();
} );
```

The filter receives and should return a `Log_Type` enum instance. Available values:

- `Log_Type::DEBUG()` - Most verbose
- `Log_Type::INFO()` - Informational messages
- `Log_Type::WARNING()` - Warnings
- `Log_Type::ERROR()` - Errors only (least verbose)

**Note:** The filter name includes your configured hook prefix, allowing multiple instances of the library to run with independent log level configurations.

#### Usage Example

The logger provides a transparent API where you can call any log method without checking conditionals:

```php
use StellarWP\Migrations\Utilities\Logger;

$logger = new Logger( $execution_id );

// These will always be called, but only written to DB if they meet the minimum level.
$logger->debug( 'Starting to process items.' );
$logger->info( 'Processing 100 items.' );
$logger->warning( 'Item 5 has invalid data.' );
$logger->error( 'Failed to process item 10.' );
```

### Log Table Schema

Each log entry contains:

- `id` - Unique log entry ID
- `migration_execution_id` - Reference to the migration execution
- `type` - Log type (info, warning, error, debug)
- `message` - Human-readable log message
- `data` - Optional JSON data for additional context
- `created_at` - Timestamp when the log was created

---

## Execution Model

The `Execution` model (`StellarWP\Migrations\Models\Execution`) is a read-only Data Transfer Object (DTO) that represents a migration execution record.

### Creating an Execution

Executions are typically retrieved from the database via the `Migration_Executions` table class or through the `get_latest_execution()` method on a migration:

```php
use StellarWP\Migrations\Tables\Migration_Executions;

// Via the table class.
$execution = Migration_Executions::get_first_by( 'migration_id', 'my_migration' );
$executions = Migration_Executions::get_all_by( 'migration_id', 'my_migration' );

// Via the migration.
$migration = $registry->get( 'my_migration' );
$execution = $migration->get_latest_execution();
```

### Getter Methods

The `Execution` model provides the following getter methods:

| Method                  | Return Type          | Description                                          |
| ----------------------- | -------------------- | ---------------------------------------------------- |
| `get_id()`              | `int`                | The unique execution ID                              |
| `get_migration_id()`    | `string`             | The migration ID this execution belongs to           |
| `get_start_date()`      | `?DateTimeInterface` | When the execution started (null if not yet started) |
| `get_end_date()`        | `?DateTimeInterface` | When the execution ended (null if still running)     |
| `get_status()`          | `Status`             | The current status as a Status enum                  |
| `get_items_total()`     | `int`                | Total number of items to process                     |
| `get_items_processed()` | `int`                | Number of items processed so far                     |
| `get_created_at()`      | `DateTimeInterface`  | When the execution record was created                |

### Usage Example

```php
use StellarWP\Migrations\Models\Execution;
use StellarWP\Migrations\Enums\Status;

$execution = $migration->get_latest_execution();

if ( $execution ) {
    // Get basic information.
    $id = $execution->get_id();
    $migration_id = $execution->get_migration_id();

    // Check status.
    $status = $execution->get_status();
    if ( $status->equals( Status::COMPLETED() ) ) {
        echo 'Migration completed successfully!';
    }

    // Calculate progress.
    $total = $execution->get_items_total();
    $processed = $execution->get_items_processed();
    $percent = $total > 0 ? ( $processed / $total ) * 100 : 0;
    echo sprintf( 'Progress: %d/%d (%.1f%%)', $processed, $total, $percent );

    // Get timing information.
    $start = $execution->get_start_date();
    $end = $execution->get_end_date();

    if ( $start ) {
        echo 'Started: ' . $start->format( 'Y-m-d H:i:s' );
    }

    if ( $end ) {
        echo 'Ended: ' . $end->format( 'Y-m-d H:i:s' );
    }
}
```

### Converting to Array

The `to_array()` method converts the execution to an associative array:

```php
$data = $execution->to_array();

// Returns:
// [
//     'id'              => 123,
//     'migration_id'    => 'my_migration',
//     'start_date'      => DateTimeInterface,
//     'end_date'        => DateTimeInterface|null,
//     'status'          => Status,
//     'items_total'     => 100,
//     'items_processed' => 50,
//     'created_at'      => DateTimeInterface,
// ]
```

**Note:** The array keys use `start_date` and `end_date` (without the `_gmt` suffix) for consistency with the getter method names.

---

## Next Steps

- [Getting Started](./getting-started.md) - Basic usage guide
- [Admin UI Reference](./admin-ui.md) - Admin interface for managing migrations
- [CLI Reference](./cli.md) - WP-CLI commands for migrations
- [REST API Reference](./rest-api.md) - REST API endpoints for programmatic access
- [Programmatic Scheduling](./programmatic-scheduling.md) - How to programmatically schedule migrations
- [Hooks Reference](./hooks.md) - Available actions and filters

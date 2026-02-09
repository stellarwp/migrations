# Hooks Reference

All hooks use the prefix configured via `Config::set_hook_prefix()`. In the examples below, `{prefix}` represents your configured prefix.

## Actions

### Scheduling

#### `stellarwp_migrations_{prefix}_schedule_migrations`

Fires when migrations should be scheduled. Triggered automatically on the `shutdown` hook.

```php
do_action( "stellarwp_migrations_{prefix}_schedule_migrations" );
```

#### `stellarwp_migrations_{prefix}_pre_schedule_migrations`

Fires before migrations are scheduled.

```php
add_action( "stellarwp_migrations_{prefix}_pre_schedule_migrations", function() {
    // Runs before any migrations are queued.
} );
```

#### `stellarwp_migrations_{prefix}_post_schedule_migrations`

Fires after migrations are scheduled.

```php
add_action( "stellarwp_migrations_{prefix}_post_schedule_migrations", function() {
    // Runs after all migrations have been queued.
} );
```

#### `stellarwp_migrations_{prefix}_pre_schedule_migration`

Fires before a single migration is scheduled via `migrations()->schedule()`.

```php
add_action(
    "stellarwp_migrations_{prefix}_pre_schedule_migration",
    function( $migration, $operation, $from_batch, $to_batch ) {
        // $migration: Migration instance being scheduled.
        // $operation: Operation enum (UP or DOWN).
        // $from_batch: Starting batch number (int).
        // $to_batch: Ending batch number (int).
    },
    10,
    4
);
```

#### `stellarwp_migrations_{prefix}_post_schedule_migration`

Fires after a single migration is scheduled via `migrations()->schedule()`.

```php
add_action(
    "stellarwp_migrations_{prefix}_post_schedule_migration",
    function( $migration, $operation, $execution_id, $from_batch, $to_batch ) {
        // $migration: Migration instance that was scheduled.
        // $operation: Operation enum (UP or DOWN).
        // $execution_id: The execution record ID (int).
        // $from_batch: Starting batch number (int).
        // $to_batch: Ending batch number (int).
    },
    10,
    5
);
```

### Batch Processing

#### `stellarwp_migrations_{prefix}_before_{method}_batch_processed`

Fires before a batch is processed. `{method}` is either `up` or `down`.

```php
add_action(
    "stellarwp_migrations_{prefix}_before_up_batch_processed",
    function( $migration, $method, $batch, $batch_size, $execution_id ) {
        // $migration: Migration instance.
        // $method: 'up' or 'down'.
        // $batch: Batch number (int).
        // $batch_size: Number of items to process in this batch (int).
        // $execution_id: The execution ID (int).
    },
    10,
    5
);
```

#### `stellarwp_migrations_{prefix}_before_batch_processed`

Generic version that fires for both `up` and `down`.

```php
add_action(
    "stellarwp_migrations_{prefix}_before_batch_processed",
    function( $migration, $method, $batch, $batch_size, $execution_id ) {
        // Runs before any batch.
        // $migration: Migration instance.
        // $method: 'up' or 'down'.
        // $batch: Batch number (int).
        // $batch_size: Number of items to process in this batch (int).
        // $execution_id: The execution ID (int).
    },
    10,
    5
);
```

#### `stellarwp_migrations_{prefix}_post_{method}_batch_processed`

Fires after a batch completes successfully. `{method}` is either `up` or `down`.

```php
add_action(
    "stellarwp_migrations_{prefix}_post_up_batch_processed",
    function( $migration, $method, $batch, $batch_size, $execution_id ) {
        // Batch completed successfully.
        // $migration: Migration instance.
        // $method: 'up' or 'down'.
        // $batch: Batch number (int).
        // $batch_size: Number of items to process in this batch (int).
        // $execution_id: The execution ID (int).
    },
    10,
    5
);
```

#### `stellarwp_migrations_{prefix}_post_batch_processed`

Generic version that fires for both `up` and `down`.

```php
add_action(
    "stellarwp_migrations_{prefix}_post_batch_processed",
    function( $migration, $method, $batch, $batch_size, $execution_id ) {
        // Runs after any successful batch.
        // $migration: Migration instance.
        // $method: 'up' or 'down'.
        // $batch: Batch number (int).
        // $batch_size: Number of items to process in this batch (int).
        // $execution_id: The execution ID (int).
    },
    10,
    5
);
```

### Failure Handling

#### `stellarwp_migrations_{prefix}_{method}_batch_failed`

Fires when a batch fails. `{method}` is either `up` or `down`.

```php
add_action(
    "stellarwp_migrations_{prefix}_up_batch_failed",
    function( $migration, $method, $batch, $batch_size, $execution_id, $exception ) {
        // $migration: Migration instance.
        // $method: 'up' or 'down'.
        // $batch: Batch number (int).
        // $batch_size: Number of items to process in this batch (int).
        // $execution_id: The execution ID (int).
        // $exception: The thrown Exception.
        error_log( "Migration failed: " . $exception->getMessage() );
    },
    10,
    6
);
```

#### `stellarwp_migrations_{prefix}_batch_failed`

Generic version that fires for both `up` and `down` failures.

```php
add_action(
    "stellarwp_migrations_{prefix}_batch_failed",
    function( $migration, $method, $batch, $batch_size, $execution_id, $exception ) {
        // Handle any batch failure.
        // $migration: Migration instance.
        // $method: 'up' or 'down'.
        // $batch: Batch number (int).
        // $batch_size: Number of items to process in this batch (int).
        // $execution_id: The execution ID (int).
        // $exception: The thrown Exception.
    },
    10,
    6
);
```

## Filters

### `stellarwp_migrations_{prefix}_automatic_schedule`

Controls whether migrations should be automatically scheduled. Return `false` to prevent automatic scheduling while still allowing migrations to be triggered via WP-CLI or programmatically.

```php
add_filter( 'stellarwp_migrations_{prefix}_automatic_schedule', '__return_false' );
```

This filter allows you to control automatic scheduling on a per-prefix basis.

### `stellarwp_migrations_{prefix}_minimum_log_level`

Controls the minimum log level that will be written to the database. Messages below this level are discarded.

```php
use StellarWP\Migrations\Enums\Log_Type;

add_filter( 'stellarwp_migrations_{prefix}_minimum_log_level', function( Log_Type $minimum_log_level ) {
    // Only log warnings and errors in production.
    return Log_Type::WARNING();
} );
```

**Log Level Hierarchy** (from most to least verbose):

1. `Log_Type::DEBUG()` - Detailed debugging information
2. `Log_Type::INFO()` - Informational messages
3. `Log_Type::WARNING()` - Warning messages
4. `Log_Type::ERROR()` - Error messages only

**Default Behavior:**

- When `WP_DEBUG` is `true`: Minimum level is `DEBUG` (all messages logged)
- When `WP_DEBUG` is `false`: Minimum level is `INFO` (debug messages skipped)

This filter is useful for:

- Reducing database writes in production by only logging warnings and errors
- Enabling verbose logging during troubleshooting
- Customizing logging behavior per environment

### `stellarwp_migrations_{prefix}_log_retention_days`

Controls the retention period in days for migration logs. Logs older than this period are automatically cleaned up by the `Clear_Logs` task.

```php
add_filter( 'stellarwp_migrations_{prefix}_log_retention_days', function( int $retention_days ) {
    // Retain logs for 90 days instead of the default 180 days.
    return 90;
} );
```

**Default Behavior:**

- Default retention period is **180 days** (6 months)
- The filter must return a value greater than 1, otherwise the default is used
- Logs are automatically cleaned up by the `Clear_Logs` Shepherd task

**How It Works:**

1. The `Clear_Logs` task runs periodically via Shepherd
2. It identifies all migration executions older than the retention period
3. Deletes all logs associated with those old executions
4. Creates a summary log entry for each processed execution indicating when logs were deleted

**Use Cases:**

- Reduce database size by cleaning up old logs
- Comply with data retention policies
- Customize retention based on environment (shorter in development, longer in production)

### Log download

The following filters and actions apply when downloading migration execution logs as CSV (e.g. via the "Download logs (CSV)" link on the single migration page).

#### `stellarwp_migrations_{prefix}_log_download_batch_size`

Filters the number of log rows fetched per batch when streaming the CSV. Default is 500.

```php
add_filter( 'stellarwp_migrations_{prefix}_log_download_batch_size', function( int $batch_size ) {
    return 1000;
} );
```

#### `stellarwp_migrations_{prefix}_log_download_csv_separator`

Filters the CSV column separator. Default is `;`.

```php
add_filter( 'stellarwp_migrations_{prefix}_log_download_csv_separator', function( string $separator ) {
    return ',';
} );
```

#### `stellarwp_migrations_{prefix}_log_download_headers`

Filters the CSV header row (array of column labels). When you add or remove headers, you must also filter row data via `stellarwp_migrations_{prefix}_log_download_row` so that the number and order of columns in each row match the headers.

```php
add_filter( 'stellarwp_migrations_{prefix}_log_download_headers', function( array $headers ) {
    $headers[] = 'Extra Column';
    return $headers;
}, 10, 1 );
```

#### `stellarwp_migrations_{prefix}_log_download_row`

Filters the row data for each log entry in the CSV. Use this filter when customizing headers via `log_download_headers` so that the number and order of row values match the headers. The default row has six columns: ID, Migration Execution ID, Date GMT, Type, Message, Data.

**Parameters:**

- `$row` (array) – The row values for CSV (same length and order as the filtered headers). Each value is already sanitized for CSV.
- `$log_entry` (array) – The raw log entry from the table (e.g. `id`, `migration_execution_id`, `created_at`, `type`, `message`, `data`).
- `$separator` (string) – The CSV separator in use.

```php
add_filter(
    'stellarwp_migrations_{prefix}_log_download_row',
    function( array $row, array $log_entry, string $separator ) {
        // Add a column to match an extra header. Sanitize so the value does not contain the separator or newlines.
        $value = (string) ( $log_entry['duration_seconds'] ?? '' );
        $row[] = str_replace( [ $separator, "\r\n", "\r", "\n" ], ' ', $value );
        return $row;
    },
    10,
    3
);
```

#### `stellarwp_migrations_{prefix}_log_download_filename`

Filters the filename used for the downloaded CSV. Default is `migration-execution-{id}-logs-{Y-m-d-His}.csv`.

```php
add_filter( 'stellarwp_migrations_{prefix}_log_download_filename', function( string $filename ) {
    return 'my-logs.csv';
} );
```

#### `stellarwp_migrations_{prefix}_log_download_stream_before`

Fires before each batch of log rows is written to the CSV stream.

```php
add_action( 'stellarwp_migrations_{prefix}_log_download_stream_before', function( $log_entries, $migration_execution_id, $offset, $batch_size ) {
    // Optional: modify or inspect the batch before it is written.
}, 10, 4 );
```

#### `stellarwp_migrations_{prefix}_log_download_stream_after`

Fires after each batch of log rows is written to the CSV stream.

```php
add_action( 'stellarwp_migrations_{prefix}_log_download_stream_after', function( $log_entries, $migration_execution_id, $offset, $batch_size ) {
    // Optional: run code after each batch (e.g. logging).
}, 10, 4 );
```

#### `stellarwp_migrations_{prefix}_log_download_sanitize_csv_value`

Filters each CSV cell value after default sanitization. The default behavior replaces the CSV separator and newline characters with a space so the delimiter in message or data cannot break the CSV.

```php
add_filter( 'stellarwp_migrations_{prefix}_log_download_sanitize_csv_value', function( string $sanitized, string $value, string $separator ) {
    // Optionally apply extra sanitization (e.g. strip HTML).
    return strip_tags( $sanitized );
}, 10, 3 );
```

**Parameters:**

- `$sanitized` (string) – Value after separator/newline replacement.
- `$value` (string) – Original raw value.
- `$separator` (string) – The CSV separator in use.

### `stellarwp_migrations_{prefix}_template_path`

Filters the template path for the default template engine. Allows customization of where template files are loaded from.

```php
add_filter( 'stellarwp_migrations_{prefix}_template_path', function( string $path, string $name ) {
    // Load templates from a custom directory.
    if ( $name === 'list' ) {
        return get_stylesheet_directory() . '/migrations-templates/' . $name . '.php';
    }

    return $path;
}, 10, 2 );
```

**Parameters:**

- `$path` (string) - The full path to the template file.
- `$name` (string) - The template name (e.g., 'list', 'components/progress-bar').

**Note:** This filter only applies when using the `Default_Template_Engine` class. If you provide a custom template engine implementation, this filter will not be used.

## Hook Execution Order

### Automatic Scheduling (via Provider)

During automatic migration scheduling on shutdown:

1. `stellarwp_migrations_{prefix}_schedule_migrations`
1. **Filter:** `stellarwp_migrations_{prefix}_automatic_schedule` - If returns `false`, stop here.
1. `stellarwp_migrations_{prefix}_pre_schedule_migrations`
1. For each migration that needs to run:
   - Migration batches are dispatched to Shepherd
1. `stellarwp_migrations_{prefix}_post_schedule_migrations`

### Programmatic Scheduling (via `migrations()->schedule()`)

When scheduling a migration programmatically:

1. `stellarwp_migrations_{prefix}_pre_schedule_migration` (with migration, operation, from_batch, to_batch)
1. Migration batches are dispatched to Shepherd
1. `stellarwp_migrations_{prefix}_post_schedule_migration` (with migration, operation, execution_id, from_batch, to_batch)

### Batch Execution

During batch execution (for both automatic and programmatic scheduling):

For each batch:

- `Migration::before_up()` (or `before_down()`)
- `stellarwp_migrations_{prefix}_before_up_batch_processed`
- `stellarwp_migrations_{prefix}_before_batch_processed`
- `Migration::up()` (or `down()`)
- `stellarwp_migrations_{prefix}_post_up_batch_processed`
- `stellarwp_migrations_{prefix}_post_batch_processed`
- `Migration::after_up()` (or `after_down()`)
- (Repeat for additional batches until `is_up_done()` returns `true`)

During a successful rollback:

1. For each batch:
   - `Migration::before_down()`
   - `stellarwp_migrations_{prefix}_before_down_batch_processed`
   - `stellarwp_migrations_{prefix}_before_batch_processed`
   - `Migration::down()`
   - `stellarwp_migrations_{prefix}_post_down_batch_processed`
   - `stellarwp_migrations_{prefix}_post_batch_processed`
   - `Migration::after_down()`
   - (Repeat for additional batches until `is_down_done()` returns `true`)

During a failure:

1. `stellarwp_migrations_{prefix}_{method}_batch_failed`
1. `stellarwp_migrations_{prefix}_batch_failed`
1. (If `up` failed) Rollback is automatically dispatched.

---

## Next Steps

- [Getting Started](./getting-started.md) - Basic usage guide
- [Migration Contract](./migration-contract.md) - Full API reference
- [Admin UI Reference](./admin-ui.md) - Admin interface for managing migrations
- [CLI Reference](./cli.md) - WP-CLI commands for migrations
- [REST API Reference](./rest-api.md) - REST API endpoints for programmatic access
- [Programmatic Scheduling](./programmatic-scheduling.md) - How to programmatically schedule migrations

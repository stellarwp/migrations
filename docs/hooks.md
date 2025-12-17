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

### `stellarwp_migrations_{prefix}_migrations_only_via_cli`

Controls whether migrations should only run via WP-CLI. Return `true` to prevent automatic scheduling.

```php
add_filter(
    "stellarwp_migrations_{prefix}_migrations_only_via_cli",
    '__return_true'
);
```

When this filter returns `true`:

- Migrations will not be automatically scheduled on `shutdown`.
- Migrations must be triggered manually via WP-CLI or custom code.

## Hook Execution Order

During a successful migration:

1. `stellarwp_migrations_{prefix}_schedule_migrations`
2. `stellarwp_migrations_{prefix}_pre_schedule_migrations`
3. For each migration:
   - `Migration::before_up()`
   - `stellarwp_migrations_{prefix}_before_up_batch_processed`
   - `stellarwp_migrations_{prefix}_before_batch_processed`
   - `Migration::up()`
   - `stellarwp_migrations_{prefix}_post_up_batch_processed`
   - `stellarwp_migrations_{prefix}_post_batch_processed`
   - `Migration::after_up()`
   - (Repeat for additional batches until `is_up_done()` returns `true`)
4. `stellarwp_migrations_{prefix}_post_schedule_migrations`

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
2. `stellarwp_migrations_{prefix}_batch_failed`
3. (If `up` failed) Rollback is automatically dispatched.

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
    function( $migration, $batch, $method ) {
        // $migration: Migration instance.
        // $batch: Batch number (int).
        // $method: 'up' or 'down'.
    },
    10,
    3
);
```

#### `stellarwp_migrations_{prefix}_before_batch_processed`

Generic version that fires for both `up` and `down`.

```php
add_action(
    "stellarwp_migrations_{prefix}_before_batch_processed",
    function( $migration, $batch, $method ) {
        // Runs before any batch.
    },
    10,
    3
);
```

#### `stellarwp_migrations_{prefix}_post_{method}_batch_processed`

Fires after a batch completes successfully. `{method}` is either `up` or `down`.

```php
add_action(
    "stellarwp_migrations_{prefix}_post_up_batch_processed",
    function( $migration, $batch, $method ) {
        // Batch completed successfully.
    },
    10,
    3
);
```

#### `stellarwp_migrations_{prefix}_post_batch_processed`

Generic version that fires for both `up` and `down`.

```php
add_action(
    "stellarwp_migrations_{prefix}_post_batch_processed",
    function( $migration, $batch, $method ) {
        // Runs after any successful batch.
    },
    10,
    3
);
```

### Failure Handling

#### `stellarwp_migrations_{prefix}_{method}_batch_failed`

Fires when a batch fails. `{method}` is either `up` or `down`.

```php
add_action(
    "stellarwp_migrations_{prefix}_up_batch_failed",
    function( $migration, $batch, $exception ) {
        // $exception: The thrown Exception.
        error_log( "Migration failed: " . $exception->getMessage() );
    },
    10,
    3
);
```

#### `stellarwp_migrations_{prefix}_batch_failed`

Generic version that fires for both `up` and `down` failures.

```php
add_action(
    "stellarwp_migrations_{prefix}_batch_failed",
    function( $migration, $batch, $exception ) {
        // Handle any batch failure.
    },
    10,
    3
);
```

## Filters

#### `stellarwp_migrations_{prefix}_migrations_only_via_cli`

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
   - `Migration::before()`
   - `stellarwp_migrations_{prefix}_before_up_batch_processed`
   - `stellarwp_migrations_{prefix}_before_batch_processed`
   - `Migration::up()`
   - `stellarwp_migrations_{prefix}_post_up_batch_processed`
   - `stellarwp_migrations_{prefix}_post_batch_processed`
   - `Migration::after()`
   - (Repeat for additional batches until `is_up_done()` returns `true`)
4. `stellarwp_migrations_{prefix}_post_schedule_migrations`

During a failure:

1. `stellarwp_migrations_{prefix}_{method}_batch_failed`
2. `stellarwp_migrations_{prefix}_batch_failed`
3. (If `up` failed) Rollback is automatically dispatched.

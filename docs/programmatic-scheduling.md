# Programmatic Scheduling

This guide explains how to programmatically schedule migrations using the `migrations()` helper function.

## Overview

The `migrations()->schedule()` method allows you to programmatically trigger the scheduling of migration batches. This is useful when you need to:

- Trigger a migration from custom code (e.g., after an import process)
- Schedule specific batch ranges
- Integrate migration execution into your own workflows

## Basic Usage

```php
use StellarWP\Migrations\Enums\Operation;

use function StellarWP\Migrations\migrations;

// Get the migration instance from the registry
$registry  = migrations()->get_registry();
$migration = $registry->get( 'my-migration-id' );

// Schedule the migration to run (UP operation, batch 1)
$result = migrations()->schedule( $migration, Operation::UP() );

// $result contains:
// [
//     'execution_id' => 123,    // The execution record ID
//     'from_batch'   => 1,      // Starting batch number
//     'to_batch'     => 1,      // Ending batch number
//     'batch_size'   => 100,    // Items per batch
// ]
```

## Method Signature

```php
public function schedule(
    Migration $migration,
    Operation $operation,
    int $from_batch = 1,
    ?int $to_batch = null,
    ?int $batch_size = null
): array
```

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$migration` | `Migration` | required | The migration instance to schedule |
| `$operation` | `Operation` | required | `Operation::UP()` to run or `Operation::DOWN()` to rollback |
| `$from_batch` | `int` | `1` | The starting batch number |
| `$to_batch` | `int\|null` | `null` | The ending batch number (defaults to same as `$from_batch`) |
| `$batch_size` | `int\|null` | `null` | Items per batch (defaults to migration's default batch size) |

### Return Value

Returns an array with the scheduling details:

```php
[
    'execution_id' => int,    // The execution record ID for tracking
    'from_batch'   => int,    // The actual starting batch number used
    'to_batch'     => int,    // The actual ending batch number used
    'batch_size'   => int,    // The actual batch size used
]
```

### Exceptions

Throws `ApiMethodException` if the execution record cannot be inserted into the database.

## Examples

### Schedule a Single Batch

```php
use StellarWP\Migrations\Enums\Operation;

use function StellarWP\Migrations\migrations;

$registry  = migrations()->get_registry();
$migration = $registry->get( 'migrate-post-meta' );

// Schedule batch 1 only
$result = migrations()->schedule( $migration, Operation::UP() );
```

### Schedule Multiple Batches

```php
use StellarWP\Migrations\Enums\Operation;

use function StellarWP\Migrations\migrations;

$registry  = migrations()->get_registry();
$migration = $registry->get( 'migrate-post-meta' );

// Schedule batches 1 through 5
$result = migrations()->schedule(
    $migration,
    Operation::UP(),
    1,  // from_batch
    5   // to_batch
);
```

### Schedule with Custom Batch Size

```php
use StellarWP\Migrations\Enums\Operation;

use function StellarWP\Migrations\migrations;

$registry  = migrations()->get_registry();
$migration = $registry->get( 'migrate-post-meta' );

// Schedule all batches with a custom batch size of 50 items
$batch_size    = 50;
$total_batches = $migration->get_total_batches( $batch_size, Operation::UP() );

$result = migrations()->schedule(
    $migration,
    Operation::UP(),
    1,             // from_batch
    $total_batches, // to_batch (all batches)
    $batch_size    // custom batch size
);
```

### Schedule a Rollback

```php
use StellarWP\Migrations\Enums\Operation;

use function StellarWP\Migrations\migrations;

$registry  = migrations()->get_registry();
$migration = $registry->get( 'migrate-post-meta' );

// Schedule a rollback (DOWN operation)
$result = migrations()->schedule( $migration, Operation::DOWN() );
```

### Error Handling

```php
use StellarWP\Migrations\Enums\Operation;
use StellarWP\Migrations\Exceptions\ApiMethodException;

use function StellarWP\Migrations\migrations;

$registry  = migrations()->get_registry();
$migration = $registry->get( 'migrate-post-meta' );

try {
    $result = migrations()->schedule( $migration, Operation::UP() );

    // Log success
    error_log( sprintf(
        'Migration scheduled. Execution ID: %d, Batches: %d-%d',
        $result['execution_id'],
        $result['from_batch'],
        $result['to_batch']
    ) );
} catch ( ApiMethodException $e ) {
    // Handle scheduling failure
    error_log( 'Failed to schedule migration: ' . $e->getMessage() );
}
```

### Conditional Scheduling

```php
use StellarWP\Migrations\Enums\Operation;

use function StellarWP\Migrations\migrations;

$registry  = migrations()->get_registry();
$migration = $registry->get( 'migrate-post-meta' );

// Only schedule if the migration is applicable and can run
if ( $migration->is_applicable() && $migration->can_run() ) {
    $result = migrations()->schedule( $migration, Operation::UP() );
}
```

## How It Works

When you call `schedule()`, the following happens:

1. An execution record is created in the database with status `SCHEDULED`
2. For each batch in the specified range, an `Execute` task is dispatched via Shepherd
3. The tasks are processed asynchronously in the background
4. Each batch updates the execution record with progress

## Tracking Execution

You can track the execution progress using the returned `execution_id`:

```php
use StellarWP\Migrations\Tables\Migration_Executions;

// Get the execution record
$execution = Migration_Executions::get_by_id( $result['execution_id'] );

// Check status
$status          = $execution->get_status();        // Status enum
$items_total     = $execution->get_items_total();   // Total items to process
$items_processed = $execution->get_items_processed(); // Items processed so far
```

## Related

- [Migration Contract](../src/Contracts/Migration.php) - The Migration interface
- [Operation Enum](../src/Enums/Operation.php) - UP and DOWN operations
- [Execution Model](../src/Models/Execution.php) - Execution tracking model

## Next Steps

- [Admin UI Reference](./admin-ui.md) - Admin interface for managing migrations
- [CLI Reference](./cli.md) - WP-CLI commands for migrations
- [REST API Reference](./rest-api.md) - REST API endpoints for programmatic access
- [Getting Started](./getting-started.md) - Basic usage guide
- [Migration Contract](./migration-contract.md) - Full API reference
- [Hooks Reference](./hooks.md) - Available actions and filters

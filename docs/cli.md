# CLI Reference

The Migrations library provides WP-CLI commands for managing and monitoring migrations from the command line. This is useful for running migrations in controlled environments, debugging issues, and monitoring execution status.

## Command Namespace

All commands are registered under the `{prefix} migrations` namespace, where `{prefix}` is determined by your configured hook prefix (converted to lowercase with underscores/spaces replaced by hyphens).

For example, if you configured:

```php
Config::set_hook_prefix( 'my_plugin' );
```

The commands will be available as:

```bash
wp my-plugin migrations <command>
```

## Available Commands

### list

Lists all registered migrations.

```bash
wp {prefix} migrations list [--tags=<tags>] [--format=<format>]
```

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--tags=<tags>` | Comma-separated list of tags to filter by | (all migrations) |
| `--format=<format>` | Output format: `table`, `json`, `csv`, `yaml` | `table` |

**Output Columns:**

- `id` - The migration's unique identifier
- `label` - Human-readable label
- `description` - Description of what the migration does
- `tags` - Assigned tags
- `total_batches` - Total number of batches to process
- `can_run` - Whether the migration can currently run
- `is_applicable` - Whether the migration applies to this site
- `status` - Current status

**Examples:**

```bash
# List all registered migrations
wp my-plugin migrations list

# List migrations with specific tags
wp my-plugin migrations list --tags=database,cleanup

# Output as JSON
wp my-plugin migrations list --format=json
```

---

### run

Runs a specific migration.

```bash
wp {prefix} migrations run <migration_id> [--from-batch=<batch>] [--to-batch=<batch>] [--batch-size=<size>]
```

**Arguments:**

| Argument | Description | Required |
|----------|-------------|----------|
| `<migration_id>` | The unique ID of the migration to run | Yes |

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--from-batch=<batch>` | Batch number to start from | `1` |
| `--to-batch=<batch>` | Batch number to end at | (last batch) |
| `--batch-size=<size>` | Number of items per batch | (migration default) |

**Examples:**

```bash
# Run a migration
wp my-plugin migrations run rename_meta_key

# Run specific batches
wp my-plugin migrations run rename_meta_key --from-batch=5 --to-batch=10

# Run with custom batch size
wp my-plugin migrations run rename_meta_key --batch-size=50
```

**Behavior:**

1. Creates a new execution record with status `SCHEDULED`
2. Processes each batch sequentially, displaying a progress bar
3. Logs are written to the migration_logs table for each batch

---

### rollback

Rolls back a specific migration.

```bash
wp {prefix} migrations rollback <migration_id> [--from-batch=<batch>] [--to-batch=<batch>] [--batch-size=<size>]
```

**Arguments:**

| Argument | Description | Required |
|----------|-------------|----------|
| `<migration_id>` | The unique ID of the migration to rollback | Yes |

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--from-batch=<batch>` | Batch number to start from | `1` |
| `--to-batch=<batch>` | Batch number to end at | (last batch) |
| `--batch-size=<size>` | Number of items per batch | (migration default) |

**Examples:**

```bash
# Rollback a migration
wp my-plugin migrations rollback rename_meta_key

# Rollback specific batches
wp my-plugin migrations rollback rename_meta_key --from-batch=1 --to-batch=5
```

---

### executions

Lists execution records for a specific migration.

```bash
wp {prefix} migrations executions <migration_id> [--format=<format>]
```

**Arguments:**

| Argument | Description | Required |
|----------|-------------|----------|
| `<migration_id>` | The unique ID of the migration | Yes |

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--format=<format>` | Output format: `table`, `json`, `csv`, `yaml` | `table` |

**Output Columns:**

- `id` - Execution ID (use this with the `logs` command)
- `migration_id` - The migration identifier
- `start_date_gmt` - When the execution started
- `end_date_gmt` - When the execution completed
- `status` - Execution status (SCHEDULED, RUNNING, COMPLETED, FAILED)
- `items_total` - Total items to process
- `items_processed` - Items processed so far
- `created_at` - When the execution was created

**Examples:**

```bash
# List executions for a migration
wp my-plugin migrations executions rename_meta_key

# Output as JSON for scripting
wp my-plugin migrations executions rename_meta_key --format=json
```

---

### logs

Lists log entries for a specific execution.

```bash
wp {prefix} migrations logs <execution_id> [--type=<type>] [--not-type=<types>] [--search=<term>] [--limit=<limit>] [--offset=<offset>] [--order=<order>] [--order-by=<column>] [--format=<format>]
```

**Arguments:**

| Argument | Description | Required |
|----------|-------------|----------|
| `<execution_id>` | The execution ID (from `executions` command) | Yes |

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--type=<types>` | Filter by log type(s): `info`, `warning`, `error`, `debug`. Comma-separated for multiple. | (all types) |
| `--not-type=<types>` | Exclude log type(s). Comma-separated for multiple. | (none) |
| `--search=<term>` | Filter logs by search term in message | (no filter) |
| `--limit=<limit>` | Maximum number of results | `100` |
| `--offset=<offset>` | Skip first N results | `0` |
| `--order=<order>` | Sort order: `ASC` or `DESC` | `DESC` |
| `--order-by=<column>` | Column to sort by | `created_at` |
| `--format=<format>` | Output format: `table`, `json`, `csv`, `yaml` | `table` |

**Output Columns:**

- `id` - Log entry ID
- `type` - Log type (info, warning, error, debug)
- `message` - Log message
- `data` - Additional context data (JSON)
- `created_at` - When the log was created

**Examples:**

```bash
# View logs for an execution
wp my-plugin migrations logs 123

# View only errors
wp my-plugin migrations logs 123 --type=error

# View warnings and errors
wp my-plugin migrations logs 123 --type=warning,error

# Exclude debug messages
wp my-plugin migrations logs 123 --not-type=debug

# Search for specific text
wp my-plugin migrations logs 123 --search="failed to update"

# Paginate results
wp my-plugin migrations logs 123 --limit=50 --offset=100

# Sort oldest first
wp my-plugin migrations logs 123 --order=ASC
```

**Note:** You cannot use `--type` and `--not-type` together.

---

## Typical Workflow

### 1. Check Available Migrations

```bash
wp my-plugin migrations list
```

### 2. Run a Migration

```bash
wp my-plugin migrations run my_migration_id
```

### 3. Monitor Progress

```bash
# Check executions
wp my-plugin migrations executions my_migration_id

# View logs for the execution
wp my-plugin migrations logs 123
```

### 4. Debug Issues

```bash
# View only error logs
wp my-plugin migrations logs 123 --type=error

# Search for specific issues
wp my-plugin migrations logs 123 --search="failed" --type=error,warning
```

### 5. Rollback if Needed

```bash
wp my-plugin migrations rollback my_migration_id
```

---

## CLI-Only Migrations

To run migrations only via CLI (preventing automatic scheduling on page loads), use the `stellarwp_migrations_{prefix}_automatic_schedule` filter:

```php
add_filter( 'stellarwp_migrations_my_plugin_automatic_schedule', '__return_false' );
```

With this filter active, migrations will only run when explicitly triggered via WP-CLI.

---

## Exit Codes

| Code | Description |
|------|-------------|
| `0` | Success |
| `1` | Error (invalid arguments, migration not found, etc.) |

---

## Next Steps

- [Getting Started](./getting-started.md) - Basic usage guide
- [Migration Contract](./migration-contract.md) - Full API reference
- [Hooks Reference](./hooks.md) - Available actions and filters

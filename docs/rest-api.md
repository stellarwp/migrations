# REST API Reference

The Migrations library provides REST API endpoints for managing and monitoring migrations programmatically. This enables integration with admin dashboards, external tools, and automation workflows.

## API Namespace

All endpoints are registered under the `{prefix}/migrations/v1` namespace, where `{prefix}` is determined by your configured hook prefix (converted to lowercase with underscores/spaces replaced by hyphens).

For example, if you configured:

```php
Config::set_hook_prefix( 'my_plugin' );
```

The API base URL will be:

```
/wp-json/my-plugin/migrations/v1
```

## Authentication

All endpoints require the `manage_options` capability. Requests must be authenticated using one of WordPress's supported authentication methods:

- **Cookie authentication** (for logged-in users in the admin)
- **Application passwords** (WordPress 5.6+)
- **OAuth** or other authentication plugins

## Available Endpoints

### List Migrations

Lists all registered migrations.

```
GET /wp-json/{prefix}/migrations/v1/migrations
```

**Parameters:**

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `tags` | string | Comma-separated list of tags to filter by | (all migrations) |

**Response:**

```json
[
  {
    "id": "my_plugin_rename_meta_key",
    "label": "Rename Meta Key",
    "description": "Renames old_key to new_key in post meta",
    "tags": ["database", "cleanup"],
    "total_batches": 15,
    "can_run": true,
    "is_applicable": true,
    "status": "pending"
  }
]
```

**Example:**

```bash
curl -X GET "https://example.com/wp-json/my-plugin/migrations/v1/migrations" \
  -H "Authorization: Basic <credentials>"

# Filter by tags
curl -X GET "https://example.com/wp-json/my-plugin/migrations/v1/migrations?tags=database,cleanup" \
  -H "Authorization: Basic <credentials>"
```

---

### Run a Migration

Schedules a migration for execution.

```
POST /wp-json/{prefix}/migrations/v1/migrations/{migration_id}/run
```

**URL Parameters:**

| Parameter | Type | Description | Required |
|-----------|------|-------------|----------|
| `migration_id` | string | The unique ID of the migration to run | Yes |

**Body Parameters:**

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `from-batch` | integer | Batch number to start from | `1` |
| `to-batch` | integer | Batch number to end at | (last batch) |
| `batch-size` | integer | Number of items per batch | (migration default) |

**Response:**

```json
{
  "success": true,
  "message": "Migration scheduled for execution.",
  "execution_id": 123,
  "operation": "up",
  "from_batch": 1,
  "to_batch": 15,
  "batch_size": 100
}
```

**Example:**

```bash
# Run a migration with default settings
curl -X POST "https://example.com/wp-json/my-plugin/migrations/v1/migrations/rename_meta_key/run" \
  -H "Authorization: Basic <credentials>"

# Run specific batches with custom batch size
curl -X POST "https://example.com/wp-json/my-plugin/migrations/v1/migrations/rename_meta_key/run" \
  -H "Authorization: Basic <credentials>" \
  -H "Content-Type: application/json" \
  -d '{"from-batch": 5, "to-batch": 10, "batch-size": 50}'
```

---

### Rollback a Migration

Schedules a migration rollback.

```
POST /wp-json/{prefix}/migrations/v1/migrations/{migration_id}/rollback
```

**URL Parameters:**

| Parameter | Type | Description | Required |
|-----------|------|-------------|----------|
| `migration_id` | string | The unique ID of the migration to rollback | Yes |

**Body Parameters:**

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `from-batch` | integer | Batch number to start from | `1` |
| `to-batch` | integer | Batch number to end at | (last batch) |
| `batch-size` | integer | Number of items per batch | (migration default) |

**Response:**

```json
{
  "success": true,
  "message": "Migration scheduled for execution.",
  "execution_id": 124,
  "operation": "down",
  "from_batch": 1,
  "to_batch": 15,
  "batch_size": 100
}
```

**Example:**

```bash
curl -X POST "https://example.com/wp-json/my-plugin/migrations/v1/migrations/rename_meta_key/rollback" \
  -H "Authorization: Basic <credentials>"
```

---

### List Executions

Lists execution records for a specific migration.

```
GET /wp-json/{prefix}/migrations/v1/migrations/{migration_id}/executions
```

**URL Parameters:**

| Parameter | Type | Description | Required |
|-----------|------|-------------|----------|
| `migration_id` | string | The unique ID of the migration | Yes |

**Response:**

```json
[
  {
    "id": 123,
    "migration_id": "rename_meta_key",
    "start_date_gmt": "2024-01-15T10:30:00+00:00",
    "end_date_gmt": "2024-01-15T10:35:00+00:00",
    "status": "completed",
    "items_total": 1500,
    "items_processed": 1500,
    "created_at": "2024-01-15T10:30:00+00:00"
  }
]
```

**Example:**

```bash
curl -X GET "https://example.com/wp-json/my-plugin/migrations/v1/migrations/rename_meta_key/executions" \
  -H "Authorization: Basic <credentials>"
```

---

### List Logs

Lists log entries for a specific execution.

```
GET /wp-json/{prefix}/migrations/v1/migrations/executions/{execution_id}/logs
```

**URL Parameters:**

| Parameter | Type | Description | Required |
|-----------|------|-------------|----------|
| `execution_id` | integer | The execution ID | Yes |

**Query Parameters:**

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `type` | string | Filter by log type(s): `info`, `warning`, `error`, `debug`. Comma-separated. | (all types) |
| `not-type` | string | Exclude log type(s). Comma-separated. | (none) |
| `search` | string | Filter logs by search term in message | (no filter) |
| `limit` | integer | Maximum number of results | `100` |
| `offset` | integer | Skip first N results | `0` |
| `order` | string | Sort order: `ASC` or `DESC` | `DESC` |
| `order-by` | string | Column to sort by: `id`, `type`, `created_at` | `created_at` |

**Response:**

```json
[
  {
    "id": 456,
    "type": "info",
    "message": "Updated 100 records",
    "data": {"batch": 1, "updated": 100},
    "created_at": "2024-01-15T10:30:05+00:00"
  },
  {
    "id": 457,
    "type": "warning",
    "message": "No records were updated",
    "data": {"batch": 15},
    "created_at": "2024-01-15T10:35:00+00:00"
  }
]
```

**Example:**

```bash
# Get all logs for an execution
curl -X GET "https://example.com/wp-json/my-plugin/migrations/v1/executions/123/logs" \
  -H "Authorization: Basic <credentials>"

# Get only error logs
curl -X GET "https://example.com/wp-json/my-plugin/migrations/v1/executions/123/logs?type=error" \
  -H "Authorization: Basic <credentials>"

# Search logs with pagination
curl -X GET "https://example.com/wp-json/my-plugin/migrations/v1/executions/123/logs?search=failed&limit=50&offset=0" \
  -H "Authorization: Basic <credentials>"
```

---

## Error Responses

All endpoints return consistent error responses:

```json
{
  "code": "migrations_error",
  "success": false,
  "message": "Migration with ID invalid_id not found."
}
```

**Common Error Codes:**

| HTTP Status | Description |
|-------------|-------------|
| `400` | Bad request (invalid parameters, validation errors) |
| `401` | Unauthorized (not authenticated) |
| `403` | Forbidden (insufficient permissions) |
| `404` | Not found (migration or execution doesn't exist) |

---

## Differences from CLI

The REST API operates slightly differently from the CLI:

| Feature | CLI | REST API |
|---------|-----|----------|
| Execution | Synchronous (blocks until complete) | Asynchronous (schedules tasks via Shepherd) |
| Progress | Real-time progress bar | Poll executions endpoint for status |
| Dry-run | Supported (`--dry-run` flag) | Not supported |
| Output format | Multiple formats (`table`, `json`, `csv`, `yaml`) | JSON only |

---

## Enabling the REST API

The REST API is enabled by default when registering the migrations provider. To disable it, you can prevent the REST provider from registering:

```php
// In your plugin's initialization
add_action( 'init', function() {
    // Custom logic to conditionally register providers
}, 5 );
```

---

## Next Steps

- [CLI Reference](./cli.md) - WP-CLI commands for migrations
- [Getting Started](./getting-started.md) - Basic usage guide
- [Hooks Reference](./hooks.md) - Available actions and filters

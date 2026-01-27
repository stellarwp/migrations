# Admin UI Reference

The Migrations library provides an Admin UI layer that allows consumers to display a migration management interface in their WordPress admin pages. The UI uses AJAX to interact with the existing REST API endpoints for running, rolling back, and monitoring migrations.

## Architecture

The Admin UI is built with a template engine abstraction, allowing consumers to either use the provided default implementation or integrate with their own template system (like Tribe's `tribe_template()` or other custom template engines).

```
src/
├── Admin/
│   ├── Assets.php               # CSS/JS asset registration and enqueuing
│   └── UI.php                   # Main UI class with render methods
├── Contracts/
│   └── Template_Engine.php      # Interface for template rendering
├── Utilities/
│   └── Default_Template_Engine.php  # Simple PHP-based implementation
└── views/                       # Template files
    ├── list.php                 # Migration list view
    ├── single.php               # Single migration detail view
    ├── single-not-found.php     # Not found error view
    └── components/              # Reusable UI components
        ├── migration-card.php   # Migration card for list view
        ├── status-card.php      # Status display with progress
        ├── config-box.php       # Configuration details box
        ├── logs.php             # Execution logs viewer
        └── progress-bar.php     # Progress bar component
```

## Quick Start

### 1. Configure the Template Engine (Optional)

The library provides a default template engine that works out of the box. If you want to use your own template system:

```php
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Contracts\Template_Engine;

// Create your custom template engine.
class My_Template_Engine implements Template_Engine {
    public function template( string $name, array $context = [], bool $output = true ) {
        // Your template rendering logic.
        // $name: Template name (e.g., 'list', 'components/progress-bar')
        // $context: Variables to pass to the template
        // $output: true to echo, false to return as string
    }
}

// Set it in your plugin's initialization.
Config::set_template_engine( new My_Template_Engine() );
```

### 2. Create an Admin Page

Add a WordPress admin page that renders the migrations UI:

```php
use StellarWP\Migrations\Admin\UI;
use StellarWP\Migrations\Config;

add_action( 'admin_menu', function() {
    add_menu_page(
        'Migrations',
        'Migrations',
        'manage_options',
        'my-plugin-migrations',
        function() {
            $ui = Config::get_container()->get( UI::class );
            $ui->render_list();
        },
        'dashicons-database'
    );

    // Hidden single migration page.
    add_submenu_page(
        null, // Hidden from menu.
        'Migration Details',
        'Migration Details',
        'manage_options',
        'my-plugin-migration-single',
        function() {
            $migration_id = sanitize_key( (string) filter_input( INPUT_GET, 'migration_id' ) );

            $ui = Config::get_container()->get( UI::class );
            $ui->render_single( $migration_id );
        }
    );
} );
```

## UI Class Methods

### `render_list()`

Renders the migrations list page showing all registered migrations.

```php
$ui = Config::get_container()->get( UI::class );
$ui->render_list();
```

**Features:**

- Displays all registered migrations as cards
- Shows status, progress, and action buttons for each migration
- Supports filtering by tags
- Option to show/hide completed and non-applicable migrations
- Migrations are sorted by status priority (running/failed first, completed last)

**Query Parameters:**

- `tags[]` or `tags` - Filter migrations by tags (array or comma-separated)
- `show_completed` - Include completed migrations (default: hidden)
- `show_non_applicable` - Include non-applicable migrations (default: hidden)

**Example URLs:**

```
/wp-admin/admin.php?page=my-plugin-migrations
/wp-admin/admin.php?page=my-plugin-migrations&tags[]=database&tags[]=cleanup
/wp-admin/admin.php?page=my-plugin-migrations&show_completed=1
```

---

### `render_single( string $migration_id )`

Renders the detail page for a single migration.

```php
$ui = Config::get_container()->get( UI::class );
$ui->render_single( 'my_migration_id' );
```

**Features:**

- Shows migration label and description
- Displays current status with progress bar
- Shows configuration details (total items, batch size, retry attempts, etc.)
- Lists execution history with logs viewer
- Provides action buttons (Run, Rollback) based on current status

**Renders a "not found" view if the migration ID doesn't exist.**

---

### `set_additional_params( array $params )`

Sets additional query parameters to preserve in the filter form when it's submitted. This is useful when the migrations UI is nested within another admin page that requires certain query parameters to be maintained.

**Accepted Parameter Types:**

Only the following value types are accepted:

- `string` - Text values
- `int` - Integer values
- `bool` - Boolean values (true/false)

Any other types will be filtered out and trigger a `_doing_it_wrong` notice.

```php
$ui = Config::get_container()->get( UI::class );
$ui->set_additional_params( [
    'section-advanced' => 'migrations', // string
    'tab'              => 'tools',      // string
    'page_num'         => 1,            // int
    'active'           => true,         // bool
] );
$ui->render_list();
```

**Use Case:**

When integrating the migrations UI into existing admin pages (like WordPress settings pages or custom admin screens), you often need to preserve certain query parameters so the page context remains correct after filter submissions. This method ensures those parameters are included as hidden fields in the filter form.

## Security

The Admin UI includes built-in security checks. Both `render_list()` and `render_single()` verify that the current user has the `manage_options` capability before rendering any content:

```php
public function render_list(): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    // ... render logic
}
```

This means:

- **Guest users** see nothing (empty output)
- **Non-admin users** (subscribers, editors, etc.) see nothing
- **Only administrators** see the UI

This mirrors the REST API permission checks to ensure consistent security across all access methods.

## Template Engine Interface

Consumers who want to integrate with their own template system must implement the `Template_Engine` interface:

```php
namespace StellarWP\Migrations\Contracts;

interface Template_Engine {
    /**
     * Render a template.
     *
     * @param string              $name    Template name (e.g., 'list', 'components/progress-bar').
     * @param array<string,mixed> $context Variables to pass to the template.
     * @param bool                $output  Whether to echo or return the output.
     *
     * @return string|void The rendered template if $echo is false, void otherwise.
     */
    public function template( string $name, array $context = [], bool $output = true );
}
```

**Template Names:**

The UI calls the template engine with these template names:

| Template Name               | Description                         |
| --------------------------- | ----------------------------------- |
| `list`                      | Main list view                      |
| `single`                    | Single migration detail view        |
| `single-not-found`          | Error view when migration not found |
| `components/migration-card` | Individual migration card           |
| `components/status-card`    | Status display with progress        |
| `components/config-box`     | Configuration details               |
| `components/logs`           | Execution logs viewer               |
| `components/progress-bar`   | Progress bar                        |

## Default Template Engine

The library includes `Default_Template_Engine` which loads PHP template files from the `src/views/` directory:

```php
use StellarWP\Migrations\Utilities\Default_Template_Engine;
use StellarWP\Migrations\Config;

// This is the default behavior - no configuration needed.
// But you can explicitly set it:
Config::set_template_engine( new Default_Template_Engine() );
```

Template variables are extracted into the local scope using PHP's `extract()` function.

## Assets

The Admin UI includes CSS and JavaScript assets that are automatically registered and enqueued when rendering. The `Assets` class handles this:

```php
use StellarWP\Migrations\Admin\Assets;
use StellarWP\Migrations\Config;

// Assets are automatically enqueued by UI::render_list() and UI::render_single().
// But you can manually register/enqueue if needed:
$assets = Config::get_container()->get( Assets::class );
$assets->register_assets();  // Just register, don't enqueue yet.
$assets->enqueue_assets();   // Register and enqueue.
```

**Included Assets:**

| Asset | Dependencies                                         | Description       |
| ----- | ---------------------------------------------------- | ----------------- |
| CSS   | Select2 CSS                                          | Admin UI styling  |
| JS    | `wp-dom-ready`, `wp-api-fetch`, `jquery`, Select2 JS | AJAX interactions |

The library bundles Select2 v4.0.13 locally, eliminating the need for CDN dependencies.

**Asset Handles:**

Asset handles are prefixed with your hook prefix:

- CSS: `{prefix}-migrations-admin`
- JS: `{prefix}-migrations-admin`
- Select2 CSS: `{prefix}-migrations-select2`
- Select2 JS: `{prefix}-migrations-select2`

### Custom Assets URL

By default, the library auto-detects the assets URL based on the library's installation path. If you need to serve assets from a different location (e.g., a CDN or custom path), you can configure the assets URL:

```php
use StellarWP\Migrations\Config;

// Set a custom assets URL during plugin initialization.
Config::set_assets_url( plugin_dir_url( __FILE__ ) . 'vendor/stellarwp/migrations/assets/' );
```

**Important:** The URL should point to the `assets/` directory of the migrations library and must include a trailing slash.

**Getting the current assets URL:**

```php
use StellarWP\Migrations\Config;

$url = Config::get_assets_url();
// Returns the configured URL, or null if not set (auto-detection will be used).
```

## JavaScript AJAX Actions

The Admin UI JavaScript handles interactions with the REST API:

| Action    | REST Endpoint                    | Description          |
| --------- | -------------------------------- | -------------------- |
| Run       | `POST /migrations/{id}/run`      | Start a migration    |
| Rollback  | `POST /migrations/{id}/rollback` | Rollback a migration |
| Load Logs | `GET /executions/{id}/logs`      | Load execution logs  |

All AJAX requests use `wp.apiFetch()` which automatically includes the WordPress nonce for authentication.

**UI Updates:**

- Buttons show loading states during AJAX requests
- Status and progress update after successful operations
- Error messages are displayed in the card's message area
- Logs are paginated and load on demand

## Action Buttons by Status

The UI displays different action buttons based on the migration's current status:

| Status         | Available Actions                |
| -------------- | -------------------------------- |
| Pending        | Run                              |
| Scheduled      | (none - waiting for execution)   |
| Running        | (displays progress)              |
| Paused         | Resume, Cancel                   |
| Completed      | Run Again, Rollback              |
| Failed         | Run, Rollback                    |
| Reverted       | Run                              |
| Canceled       | Run, Rollback                    |
| Not Applicable | (none - migration doesn't apply) |

**Status Notes:**

- **Failed** status indicates the migration failed and was automatically rolled back. You can retry the migration or manually rollback any partial changes.
- **Reverted** status indicates a completed migration was manually rolled back successfully. You can run the migration again if needed.
- The "Run Again" button is shown for completed migrations to allow re-running if necessary.

## Preserving Query Parameters

When integrating the migrations UI into existing admin pages, you often need to preserve certain query parameters so the page context remains correct after filter form submissions.

### Using `set_additional_params()`

The recommended approach is to use the `set_additional_params()` method. Only string, int, and bool values are accepted:

```php
$ui = Config::get_container()->get( UI::class );

// When nested in a settings page that uses query parameters.
$ui->set_additional_params( [
    'page'             => 'my-settings-page', // string
    'section-advanced' => 'migrations',       // string
    'view_count'       => 10,                 // int
    'show_archived'    => false,              // bool
] );

$ui->render_list();
```

This ensures that when users filter migrations, they stay on the same settings page and section.

## Custom Integration Example

Here's a complete example of integrating the Admin UI with a custom template engine:

```php
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Contracts\Template_Engine;
use StellarWP\Migrations\Admin\UI;

// Custom template engine using Tribe's template system.
class Tribe_Migrations_Template_Engine implements Template_Engine {
    public function template( string $name, array $context = [], bool $output = true ) {
        // Map migration template names to Tribe template paths.
        $template = tribe_template( 'migrations/' . $name );

        if ( $output ) {
            $template->set_values( $context );
            $template->render();
            return;
        }

        return $template->set_values( $context )->get_html();
    }
}

// During plugin initialization.
add_action( 'init', function() {
    Config::set_template_engine( new Tribe_Migrations_Template_Engine() );
}, 15 );

// Add admin pages.
add_action( 'admin_menu', function() {
    add_submenu_page(
        'parent-menu-slug',
        'Data Migrations',
        'Migrations',
        'manage_options',
        'my-plugin-migrations',
        function() {
            echo '<div class="wrap">';
            Config::get_container()->get( UI::class )->render_list();
            echo '</div>';
        }
    );
} );
```

## Styling

The Admin UI uses CSS classes prefixed with `stellarwp-migration-` for easy customization:

```css
/* Migration card container */
.stellarwp-migration-card {
}

/* Status labels */
.stellarwp-migration-card__status-label {
}
.stellarwp-migration-card__status-label--pending {
}
.stellarwp-migration-card__status-label--running {
}
.stellarwp-migration-card__status-label--completed {
}
.stellarwp-migration-card__status-label--failed {
}

/* Progress bar */
.stellarwp-migration-progress {
}
.stellarwp-migration-progress__bar {
}
.stellarwp-migration-progress__fill {
}

/* Action buttons */
.stellarwp-migration-btn {
}
.stellarwp-migration-btn--primary {
}
.stellarwp-migration-btn--secondary {
}
.stellarwp-migration-btn--danger {
}
```

---

## Next Steps

- [Getting Started](./getting-started.md) - Basic usage guide
- [Migration Contract](./migration-contract.md) - Full API reference
- [CLI Reference](./cli.md) - WP-CLI commands for migrations
- [REST API Reference](./rest-api.md) - REST API endpoints used by the Admin UI
- [Programmatic Scheduling](./programmatic-scheduling.md) - How to programmatically schedule migrations
- [Hooks Reference](./hooks.md) - Available actions and filters

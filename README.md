# Migrations

A batched database migrations library for WordPress plugins powered by [Shepherd](https://github.com/stellarwp/shepherd).

## Features

- **Batched execution** - Process large datasets incrementally without timeouts.
- **Automatic rollback** - Failed migrations trigger automatic rollback via `down()`.
- **Activity logging** - All migration activity is logged to a database table.
- **Extensible hooks** - Actions and filters for custom behavior at each stage.
- **WP-CLI ready** - Optional CLI-only mode for controlled migrations.

## Quick Start

```php
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Provider;
use StellarWP\Migrations\Registry;
use StellarWP\Migrations\Abstracts\Migration_Abstract;

// Configure the library.
Config::set_container( $container );
Config::set_hook_prefix( 'my_plugin' );

// Register the provider.
$container->get( Provider::class )->register();

// Create a migration.
class Rename_Meta_Key extends Migration_Abstract {
    public function is_applicable(): bool {
        return true;
    }

    public function is_up_done(): bool {
        global $wpdb;
        return ! (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE meta_key = %s',
                $wpdb->postmeta,
                'old_key'
            )
        );
    }

    public function is_down_done(): bool {
        global $wpdb;
        return ! (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE meta_key = %s',
                $wpdb->postmeta,
                'new_key'
            )
        );
    }

    public function up( int $batch ): void {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE %i SET meta_key = %s WHERE meta_key = %s LIMIT 100',
                $wpdb->postmeta,
                'new_key',
                'old_key'
            )
        );
    }

    public function down( int $batch ): void {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                'UPDATE %i SET meta_key = %s WHERE meta_key = %s LIMIT 100',
                $wpdb->postmeta,
                'old_key',
                'new_key'
            )
        );
    }
}

// Register the migration with a unique ID.
$registry = Config::get_container()->get( Registry::class );
$registry->register( 'my_plugin_rename_meta_key', Rename_Meta_Key::class );
```

## Logging

Add custom logs to track migration progress and debug issues:

```php
public function up( int $batch, int $batch_size ): void {
    $logger = $this->get_logger();

    $logger->info( 'Starting batch processing.', [ 'batch' => $batch ] );

    // ... do migration work ...

    if ( $some_warning_condition ) {
        $logger->warning( 'Found invalid data.', [ 'record_id' => $id ] );
    }
}
```

Available log levels: `info()`, `warning()`, `error()`, `debug()`

## Documentation

- [Getting Started](./docs/getting-started.md) - Installation and basic usage.
- [Migration Contract](./docs/migration-contract.md) - Full API reference.
- [Hooks Reference](./docs/hooks.md) - Available actions and filters.
- [Tests](./docs/test.md) - Test setup instructions.

## Contributing

We welcome contributions. Please see our contributing guidelines for more information.

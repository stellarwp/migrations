# Tests

## Requirements

[SLIC (StellarWP Local Interactive Containers)](https://github.com/stellarwp/slic) is used for running tests in this project.

## Installation

1. Follow the [slic documentation](https://github.com/stellarwp/slic) to install and set up SLIC.

2. Install the required theme: `slic wp theme install kadence`

## Running Tests

### Unit Tests (wpunit)

Run the WordPress unit tests:

```bash
slic run tests/wpunit
```

### CLI Integration Tests

Run the CLI integration tests:

```bash
slic run tests/cli_integration
```

These tests verify the WP-CLI commands (`list`, `run`, `rollback`, `executions`, `logs`) work correctly.

### All Tests

Run all test suites:

```bash
slic run
```

## Test Structure

- `tests/wpunit/` - WordPress unit tests for core functionality
- `tests/cli_integration/` - CLI integration tests using Codeception CEST format
- `tests/_support/Helper/Migrations/` - Test migration classes
- `tests/_data/test-plugin/` - Test plugin for CLI integration tests

## Snapshot Testing

The CLI integration tests use snapshot testing for output verification. Snapshots are stored in `tests/cli_integration/__snapshots__/`.

To update snapshots after intentional changes:

1. Delete the relevant snapshot file(s)
2. Re-run the tests
3. New snapshots will be created automatically

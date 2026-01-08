<?php
/**
 * CLI Integration Helper Module.
 *
 * Provides snapshot assertion methods for CLI integration tests.
 *
 * @package Helper
 */

namespace Helper;

use Codeception\Module;
use Codeception\TestInterface;
use PHPUnit\Framework\Assert;
use RuntimeException;

/**
 * Helper module for CLI integration tests.
 *
 * All public methods declared in this helper class will be available in $I.
 */
class Cli_integration extends Module {
	/**
	 * Current test reference for snapshot naming.
	 *
	 * @var TestInterface|null
	 */
	protected ?TestInterface $current_test = null;

	/**
	 * Counter for multiple snapshots in the same test.
	 *
	 * @var array<string, int>
	 */
	protected static array $snapshot_counters = [];

	/**
	 * Hook called before each test.
	 *
	 * @param TestInterface $test The current test.
	 *
	 * @return void
	 */
	public function _before( TestInterface $test ): void {
		$this->current_test = $test;
	}

	/**
	 * Assert that a string matches a stored snapshot.
	 *
	 * If the snapshot file doesn't exist, it will be created with the current content.
	 * On subsequent runs, the current content will be compared to the stored snapshot.
	 *
	 * @param string      $current       The current string to compare.
	 * @param string|null $snapshot_name Optional custom snapshot name. If not provided,
	 *                                   it will be auto-generated from the test name.
	 *
	 * @return void
	 *
	 * @throws RuntimeException If the test context is not available.
	 */
	public function assertMatchesStringSnapshot( string $current, ?string $snapshot_name = null ): void {
		$this->assert_matches_snapshot( $current, $snapshot_name, 'txt' );
	}

	/**
	 * Assert that a CSV string matches a stored snapshot.
	 *
	 * If the snapshot file doesn't exist, it will be created with the current content.
	 * On subsequent runs, the current content will be compared to the stored snapshot.
	 *
	 * @param string      $current       The current CSV string to compare.
	 * @param string|null $snapshot_name Optional custom snapshot name. If not provided,
	 *                                   it will be auto-generated from the test name.
	 *
	 * @return void
	 *
	 * @throws RuntimeException If the test context is not available.
	 */
	public function assertMatchesCsvSnapshot( string $current, ?string $snapshot_name = null ): void {
		$this->assert_matches_snapshot( $current, $snapshot_name, 'csv' );
	}

	/**
	 * Assert that a YAML string matches a stored snapshot.
	 *
	 * If the snapshot file doesn't exist, it will be created with the current content.
	 * On subsequent runs, the current content will be compared to the stored snapshot.
	 *
	 * @param string      $current       The current YAML string to compare.
	 * @param string|null $snapshot_name Optional custom snapshot name. If not provided,
	 *                                   it will be auto-generated from the test name.
	 *
	 * @return void
	 *
	 * @throws RuntimeException If the test context is not available.
	 */
	public function assertMatchesYamlSnapshot( string $current, ?string $snapshot_name = null ): void {
		$this->assert_matches_snapshot( $current, $snapshot_name, 'yml' );
	}

	/**
	 * Assert that a JSON string matches a stored snapshot.
	 *
	 * The JSON will be pretty-printed before comparison for better diffs.
	 *
	 * @param string      $current       The current JSON string to compare.
	 * @param string|null $snapshot_name Optional custom snapshot name.
	 *
	 * @return void
	 */
	public function assertMatchesJsonSnapshot( string $current, ?string $snapshot_name = null ): void {
		// Pretty-print JSON for better diffs.
		$decoded = json_decode( $current, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			$current = json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		}

		$this->assert_matches_snapshot( $current, $snapshot_name, 'json' );
	}

	/**
	 * Assert that CLI output matches a stored snapshot.
	 *
	 * This is an alias for assertMatchesStringSnapshot with better semantics
	 * for CLI testing.
	 *
	 * @param string      $output        The CLI output to compare.
	 * @param string|null $snapshot_name Optional custom snapshot name.
	 *
	 * @return void
	 */
	public function assertCliOutputMatchesSnapshot( string $output, ?string $snapshot_name = null ): void {
		$this->assert_matches_snapshot( $output, $snapshot_name, 'txt' );
	}

	/**
	 * Core snapshot assertion logic.
	 *
	 * @param string      $current       The current content to compare.
	 * @param string|null $snapshot_name Optional custom snapshot name.
	 * @param string      $extension     File extension for the snapshot.
	 *
	 * @return void
	 *
	 * @throws RuntimeException If test context is not available.
	 */
	protected function assert_matches_snapshot( string $current, ?string $snapshot_name, string $extension ): void {
		$snapshot_path = $this->get_snapshot_path( $snapshot_name, $extension );
		$snapshots_dir = dirname( $snapshot_path );

		// Ensure snapshots directory exists.
		if ( ! is_dir( $snapshots_dir ) ) {
			if ( ! mkdir( $snapshots_dir, 0777, true ) && ! is_dir( $snapshots_dir ) ) {
				throw new RuntimeException(
					sprintf( 'Failed to create snapshots directory: %s', $snapshots_dir )
				);
			}
		}

		// If snapshot doesn't exist, create it.
		if ( ! file_exists( $snapshot_path ) ) {
			file_put_contents( $snapshot_path, $current );
			$this->snapshot_debug( "Snapshot created: {$snapshot_path}" );

			// Mark test as incomplete since we just created the snapshot.
			Assert::markTestIncomplete(
				sprintf( 'Snapshot created at %s. Re-run the test to verify.', $snapshot_path )
			);

			return;
		}

		// Load and compare.
		$expected = file_get_contents( $snapshot_path );

		if ( $expected === false ) {
			throw new RuntimeException(
				sprintf( 'Failed to read snapshot file: %s', $snapshot_path )
			);
		}

		// Normalize line endings for cross-platform compatibility.
		$expected = str_replace( "\r\n", "\n", $expected );
		$current  = str_replace( "\r\n", "\n", $current );

		Assert::assertSame(
			$expected,
			$current,
			sprintf(
				"Snapshot mismatch.\nSnapshot: %s\nTo update, delete the snapshot file and re-run the test.",
				$snapshot_path
			)
		);
	}

	/**
	 * Get the snapshot file path.
	 *
	 * @param string|null $snapshot_name Custom snapshot name.
	 * @param string      $extension     File extension.
	 *
	 * @return string The full path to the snapshot file.
	 *
	 * @throws RuntimeException If test context is not available.
	 */
	protected function get_snapshot_path( ?string $snapshot_name, string $extension ): string {
		if ( $this->current_test === null ) {
			throw new RuntimeException( 'Test context not available. Snapshot assertions must be called within a test.' );
		}

		$test_name = $this->current_test->getMetadata()->getName();
		$test_file = $this->current_test->getMetadata()->getFilename();
		$test_dir  = dirname( $test_file );

		// Extract class name from filename (e.g., "ListCest.php" -> "ListCest").
		$class_name = pathinfo( $test_file, PATHINFO_FILENAME );

		// Extract method name from test name (format: "ClassName:methodName" or just "methodName").
		if ( preg_match( '/^(?:.+:)?(.+)$/', $test_name, $matches ) ) {
			$method_name = $matches[1];
		} else {
			$method_name = $test_name;
		}

		// Build snapshot filename.
		if ( $snapshot_name !== null ) {
			$filename = $this->sanitize_filename( $snapshot_name );
		} else {
			// Auto-generate name from class and method.
			$counter  = $this->get_counter_for( $test_name );
			$filename = sprintf(
				'%s__%s__%d',
				$this->sanitize_filename( $class_name ),
				$this->sanitize_filename( $method_name ),
				$counter
			);
		}

		return sprintf(
			'%s/__snapshots__/%s.%s',
			$test_dir,
			$filename,
			$extension
		);
	}

	/**
	 * Get and increment counter for a test.
	 *
	 * @param string $test_name The test name.
	 *
	 * @return int The counter value.
	 */
	protected function get_counter_for( string $test_name ): int {
		if ( ! isset( self::$snapshot_counters[ $test_name ] ) ) {
			self::$snapshot_counters[ $test_name ] = 0;
		}

		return self::$snapshot_counters[ $test_name ]++;
	}

	/**
	 * Sanitize a string for use as a filename.
	 *
	 * @param string $name The name to sanitize.
	 *
	 * @return string The sanitized filename.
	 */
	protected function sanitize_filename( string $name ): string {
		// Remove namespace separators and replace with underscores.
		$name = str_replace( [ '\\', '/' ], '_', $name );

		// Remove or replace other problematic characters.
		$name = preg_replace( '/[^a-zA-Z0-9_-]/', '_', $name );

		// Collapse multiple underscores.
		$name = preg_replace( '/_+/', '_', $name );

		// Trim underscores from ends.
		return trim( $name, '_' );
	}

	/**
	 * Output snapshot debug message.
	 *
	 * @param string $message The debug message.
	 *
	 * @return void
	 */
	protected function snapshot_debug( string $message ): void {
		$this->debugSection( 'Snapshot', $message );
	}
}

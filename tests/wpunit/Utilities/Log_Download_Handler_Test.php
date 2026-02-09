<?php
/**
 * Migration Log Download Handler Tests.
 *
 * Tests for the Log_Download_Handler utility class.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Utilities
 */

declare( strict_types=1 );

namespace StellarWP\Migrations\Tests\Utilities;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Enums\Log_Type;
use StellarWP\Migrations\Enums\Status;
use StellarWP\Migrations\Tables\Migration_Executions;
use StellarWP\Migrations\Tables\Migration_Logs;
use StellarWP\Migrations\Utilities\Log_Download_Handler;
use StellarWP\DB\DB;

/**
 * Migration Log Download Handler Tests.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Utilities
 */
class Log_Download_Handler_Test extends WPTestCase {
	/**
	 * Stubs filter_input so it reads from $_GET (needed in CLI where INPUT_GET is not populated).
	 *
	 * @return void
	 */
	private function stub_filter_input_to_use_get(): void {
		uopz_set_return(
			'filter_input',
			function ( $type, $variable, $filter = FILTER_DEFAULT ) {
				if ( $type === INPUT_GET && array_key_exists( $variable, $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test stub for CLI.
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Test stub for CLI.
					$value = $_GET[ $variable ];
					if ( $filter === FILTER_SANITIZE_NUMBER_INT ) {
						return filter_var( $value, FILTER_SANITIZE_NUMBER_INT );
					}
					if ( $filter === FILTER_SANITIZE_SPECIAL_CHARS ) {
						return filter_var( $value, FILTER_SANITIZE_SPECIAL_CHARS );
					}

					return $value;
				}

				return null;
			},
			true
		);
	}

	/**
	 * Sets a wp_die handler that throws so we can assert on the message.
	 *
	 * @return void
	 */
	private function set_wp_die_to_throw(): void {
		add_filter(
			'wp_die_handler',
			static function () {
				return static function ( $message ) {
					throw new \RuntimeException( is_string( $message ) ? $message : (string) $message );
				};
			}
		);
	}

	// -------------------------------------------------------------------------
	// get_action_name()
	// -------------------------------------------------------------------------

	/**
	 * @test
	 */
	public function it_should_return_action_name_with_hook_prefix(): void {
		// Assert.
		$action = Log_Download_Handler::get_action_name();

		$this->assertSame( 'stellarwp_migrations_foobar_log_download', $action );
	}

	// -------------------------------------------------------------------------
	// get_download_url()
	// -------------------------------------------------------------------------

	/**
	 * @test
	 */
	public function it_should_return_download_url_with_action_and_execution_id(): void {
		// Arrange.
		$migration_execution_id = 42;

		// Act.
		$url = Log_Download_Handler::get_download_url( $migration_execution_id );

		// Assert.
		$this->assertStringContainsString( 'admin-post.php', $url );
		$this->assertStringContainsString( 'action=stellarwp_migrations_foobar_log_download', $url );
		$this->assertStringContainsString( 'migration_execution_id=42', $url );
		$this->assertMatchesRegularExpression( '/nonce=[a-zA-Z0-9]+/', $url );
	}

	/**
	 * @test
	 */
	public function it_should_return_url_with_verifiable_nonce(): void {
		// Arrange.
		$migration_execution_id = 123;
		$url                    = Log_Download_Handler::get_download_url( $migration_execution_id );
		$parsed                 = wp_parse_url( $url );
		$this->assertIsArray( $parsed );
		parse_str( $parsed['query'] ?? '', $query );

		// Assert.
		$this->assertArrayHasKey( 'nonce', $query );
		$verified = wp_verify_nonce(
			$query['nonce'],
			Log_Download_Handler::get_action_name() . $migration_execution_id
		);
		$this->assertNotFalse( $verified );
	}

	// -------------------------------------------------------------------------
	// download() – permission and validation failures (wp_die)
	// -------------------------------------------------------------------------

	/**
	 * @test
	 */
	public function it_should_die_with_permission_message_when_user_cannot_manage_options(): void {
		// Arrange.
		$this->set_wp_die_to_throw();
		$user_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );
		$_GET['migration_execution_id'] = '1';
		$_GET['nonce']                  = wp_create_nonce( Log_Download_Handler::get_action_name() . '1' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have sufficient permissions to download this file.' );

		// Act.
		Log_Download_Handler::download();
	}

	/**
	 * @test
	 */
	public function it_should_die_with_invalid_id_message_when_migration_execution_id_is_missing(): void {
		// Arrange.
		$this->set_wp_die_to_throw();
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$_GET['migration_execution_id'] = '0';
		$_GET['nonce']                  = wp_create_nonce( Log_Download_Handler::get_action_name() . '0' );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Invalid migration execution ID.' );

		// Act.
		Log_Download_Handler::download();
	}

	/**
	 * @test
	 */
	public function it_should_die_with_expired_url_message_when_nonce_is_invalid(): void {
		// Arrange.
		$execution_id = $this->create_test_execution();
		$this->set_wp_die_to_throw();
		$this->stub_filter_input_to_use_get();
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$_GET['migration_execution_id'] = (string) $execution_id;
		$_GET['nonce']                  = 'invalid-nonce';

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'URL expired. Please refresh the page and try again.' );

		// Act.
		Log_Download_Handler::download();
	}

	/**
	 * @test
	 */
	public function it_should_die_with_execution_not_found_when_execution_does_not_exist(): void {
		// Arrange.
		$this->set_wp_die_to_throw();
		$this->stub_filter_input_to_use_get();
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$nonexistent_id                 = 999999;
		$_GET['migration_execution_id'] = (string) $nonexistent_id;
		$_GET['nonce']                  = wp_create_nonce( Log_Download_Handler::get_action_name() . $nonexistent_id );

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Execution not found.' );

		// Act.
		Log_Download_Handler::download();
	}

	// -------------------------------------------------------------------------
	// download() – success path (streams CSV and exits)
	// -------------------------------------------------------------------------

	/**
	 * @test
	 */
	public function it_should_stream_csv_and_exit_when_request_is_valid(): void {
		// Arrange.
		$execution_id = $this->create_test_execution_with_logs();
		$this->stub_filter_input_to_use_get();
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$_GET['migration_execution_id'] = (string) $execution_id;
		$_GET['nonce']                  = wp_create_nonce( Log_Download_Handler::get_action_name() . $execution_id );

		$exit_thrown = false;
		uopz_set_return(
			Log_Download_Handler::class,
			'do_exit',
			static function () {
				throw new \RuntimeException( 'exit_called' );
			},
			true
		);

		// Three levels: handler’s ob_end_clean() removes one; handler writes and flush() sends to next; we capture and close only our buffers.
		$initial_ob_level = ob_get_level();
		ob_start();
		ob_start();
		ob_start();

		try {
			// Act.
			Log_Download_Handler::download();
		} catch ( \RuntimeException $e ) {
			if ( $e->getMessage() === 'exit_called' ) {
				$exit_thrown = true;
			} else {
				throw $e;
			}
		} finally {
			$output = '';
			while ( ob_get_level() > $initial_ob_level ) {
				$output .= ob_get_clean();
			}
		}

		// Assert.
		$this->assertTrue( $exit_thrown, 'Expected exit() to be called (thrown as exception).' );
		$this->assertStringContainsString( 'Migration Execution ID', $output );
		$this->assertStringContainsString( 'Type', $output );
		$this->assertStringContainsString( 'Message', $output );
		$this->assertStringContainsString( 'Migration started', $output );
	}

	/**
	 * @test
	 */
	public function it_should_sanitize_csv_output_by_removing_separator_and_newlines_from_log_messages(): void {
		// Arrange.
		$execution_id  = $this->create_test_execution();
		$dirty_message = "part1;part2\nline2\r\nline3\rline4";
		Migration_Logs::insert(
			[
				'migration_execution_id' => $execution_id,
				'type'                   => Log_Type::INFO()->getValue(),
				'message'                => $dirty_message,
				'data'                   => wp_json_encode( [] ),
			]
		);

		$this->stub_filter_input_to_use_get();
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$_GET['migration_execution_id'] = (string) $execution_id;
		$_GET['nonce']                  = wp_create_nonce( Log_Download_Handler::get_action_name() . $execution_id );

		uopz_set_return(
			Log_Download_Handler::class,
			'do_exit',
			static function () {
				throw new \RuntimeException( 'exit_called' );
			},
			true
		);

		$initial_ob_level = ob_get_level();
		ob_start();
		ob_start();
		ob_start();

		try {
			// Act.
			Log_Download_Handler::download();
		} catch ( \RuntimeException $e ) {
			if ( $e->getMessage() !== 'exit_called' ) {
				throw $e;
			}
		} finally {
			$output = '';
			while ( ob_get_level() > $initial_ob_level ) {
				$output .= ob_get_clean();
			}
		}

		// Assert.
		$this->assertStringContainsString( 'Migration Execution ID', $output );

		$sanitized_message = 'part1 part2 line2 line3 line4';
		$this->assertStringContainsString( $sanitized_message, $output );
		$this->assertStringNotContainsString( 'part1;part2', $output );
		$this->assertStringNotContainsString( "\nline2", $output );
		$this->assertStringNotContainsString( "\r\nline3", $output );
		$this->assertStringNotContainsString( "\rline4", $output );

		$lines      = preg_split( '/\r\n|\r|\n/', $output );
		$data_lines = array_filter(
			$lines,
			static function ( $line ) {
				return $line !== '' && ! str_contains( $line, 'Migration Execution ID' );
			}
		);
		$this->assertCount( 1, $data_lines, 'CSV should have exactly one data row (no extra lines from embedded newlines).' );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Creates a single execution record and returns its ID.
	 *
	 * @return int The execution ID.
	 */
	private function create_test_execution(): int {
		Migration_Executions::insert(
			[
				'migration_id'    => 'tests_simple_migration',
				'status'          => Status::COMPLETED()->getValue(),
				'items_total'     => 10,
				'items_processed' => 10,
			]
		);

		return (int) DB::last_insert_id();
	}

	/**
	 * Creates an execution with log entries and returns the execution ID.
	 *
	 * @return int The execution ID.
	 */
	private function create_test_execution_with_logs(): int {
		$execution_id = $this->create_test_execution();

		Migration_Logs::insert(
			[
				'migration_execution_id' => $execution_id,
				'type'                   => Log_Type::INFO()->getValue(),
				'message'                => 'Migration started',
				'data'                   => wp_json_encode( [] ),
			]
		);

		return $execution_id;
	}
}

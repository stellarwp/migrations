<?php
/**
 * Admin UI Tests.
 *
 * Tests the Admin UI render methods using snapshot assertions.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Admin
 */

declare(strict_types=1);

namespace StellarWP\Migrations\Tests\Admin;

use lucatume\WPBrowser\TestCase\WPTestCase;
use StellarWP\Migrations\Admin\UI;
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Enums\Status;
use StellarWP\Migrations\Registry;
use StellarWP\Migrations\Tables\Migration_Executions;
use StellarWP\Migrations\Tests\Migrations\Multi_Batch_Migration;
use StellarWP\Migrations\Tests\Migrations\Not_Applicable_Migration;
use StellarWP\Migrations\Tests\Migrations\Simple_Migration;
use StellarWP\Migrations\Tests\Migrations\Tagged_Migration;
use StellarWP\Migrations\Utilities\Default_Template_Engine;
use tad\Codeception\SnapshotAssertions\SnapshotAssertions;

/**
 * Admin UI Tests.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\Tests\Admin
 */
class UI_Test extends WPTestCase {
	use SnapshotAssertions;

	/**
	 * The UI instance.
	 *
	 * @var UI
	 */
	protected UI $ui;

	/**
	 * Set up before each test.
	 *
	 * @before
	 *
	 * @return void
	 */
	public function set_up_ui(): void {
		// Reset migration states.
		Simple_Migration::reset();
		Multi_Batch_Migration::reset();
		Not_Applicable_Migration::reset();
		Tagged_Migration::reset();
		tests_migrations_clear_calls_data();

		// Set up template engine.
		Config::set_template_engine( new Default_Template_Engine() );

		// Flush and re-register migrations.
		$container = Config::get_container();
		$registry  = $container->get( Registry::class );
		$registry->flush();

		// Get UI instance.
		$this->ui = $container->get( UI::class );
	}

	/**
	 * Tear down after each test.
	 *
	 * @after
	 *
	 * @return void
	 */
	public function tear_down_ui(): void {
		wp_set_current_user( 0 );

		// Reset migration states.
		Simple_Migration::reset();
		Multi_Batch_Migration::reset();
		Not_Applicable_Migration::reset();
		Tagged_Migration::reset();
		tests_migrations_clear_calls_data();
	}

	/**
	 * Set the current user as an administrator.
	 *
	 * @return int The user ID.
	 */
	protected function set_admin_user(): int {
		$user_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Register standard test migrations.
	 *
	 * @return void
	 */
	protected function register_test_migrations(): void {
		$registry = Config::get_container()->get( Registry::class );

		$registry->register( 'tests_simple_migration', Simple_Migration::class );
		$registry->register( 'tests_multi_batch_migration', Multi_Batch_Migration::class );
		$registry->register( 'tests_not_applicable_migration', Not_Applicable_Migration::class );
		$registry->register( 'tests_tagged_migration', Tagged_Migration::class );
	}

	/**
	 * @test
	 */
	public function it_should_not_render_list_for_guest_user(): void {
		wp_set_current_user( 0 );
		$this->register_test_migrations();

		ob_start();
		$this->ui->render_list();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * @test
	 */
	public function it_should_not_render_list_for_non_admin_user(): void {
		$user_id = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );
		$this->register_test_migrations();

		ob_start();
		$this->ui->render_list();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * @test
	 */
	public function it_should_render_empty_list_when_no_migrations(): void {
		$this->set_admin_user();

		ob_start();
		$this->ui->render_list();
		$output = ob_get_clean();

		$this->assertMatchesHtmlSnapshot( $output );
	}

	/**
	 * @test
	 */
	public function it_should_render_list_with_migrations(): void {
		$this->set_admin_user();
		$this->register_test_migrations();

		ob_start();
		$this->ui->render_list();
		$output = ob_get_clean();

		$this->assertMatchesHtmlSnapshot( $output );
	}

	/**
	 * @test
	 */
	public function it_should_render_list_with_single_migration(): void {
		$this->set_admin_user();

		$registry = Config::get_container()->get( Registry::class );
		$registry->register( 'tests_simple_migration', Simple_Migration::class );

		ob_start();
		$this->ui->render_list();
		$output = ob_get_clean();

		$this->assertMatchesHtmlSnapshot( $output );
	}

	/**
	 * @test
	 */
	public function it_should_not_render_single_for_guest_user(): void {
		wp_set_current_user( 0 );
		$this->register_test_migrations();

		ob_start();
		$this->ui->render_single( 'tests_simple_migration' );
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * @test
	 */
	public function it_should_not_render_single_for_non_admin_user(): void {
		$user_id = $this->factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $user_id );
		$this->register_test_migrations();

		ob_start();
		$this->ui->render_single( 'tests_simple_migration' );
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * @test
	 */
	public function it_should_render_single_not_found(): void {
		$this->set_admin_user();

		ob_start();
		$this->ui->render_single( 'nonexistent_migration' );
		$output = ob_get_clean();

		$this->assertMatchesHtmlSnapshot( $output );
	}

	/**
	 * @test
	 */
	public function it_should_render_single_simple_migration(): void {
		$this->set_admin_user();
		$this->register_test_migrations();

		ob_start();
		$this->ui->render_single( 'tests_simple_migration' );
		$output = ob_get_clean();

		$this->assertMatchesHtmlSnapshot( $output );
	}

	/**
	 * @test
	 */
	public function it_should_render_single_multi_batch_migration(): void {
		$this->set_admin_user();
		$this->register_test_migrations();

		ob_start();
		$this->ui->render_single( 'tests_multi_batch_migration' );
		$output = ob_get_clean();

		$this->assertMatchesHtmlSnapshot( $output );
	}

	/**
	 * @test
	 */
	public function it_should_render_single_tagged_migration(): void {
		$this->set_admin_user();
		$this->register_test_migrations();

		ob_start();
		$this->ui->render_single( 'tests_tagged_migration' );
		$output = ob_get_clean();

		$this->assertMatchesHtmlSnapshot( $output );
	}

	/**
	 * @test
	 */
	public function it_should_render_single_not_applicable_migration(): void {
		$this->set_admin_user();
		$this->register_test_migrations();

		ob_start();
		$this->ui->render_single( 'tests_not_applicable_migration' );
		$output = ob_get_clean();

		$this->assertMatchesHtmlSnapshot( $output );
	}

	/**
	 * @test
	 */
	public function it_should_render_single_with_execution_history(): void {
		$this->set_admin_user();
		$this->register_test_migrations();

		// Create a completed execution record.
		Migration_Executions::insert(
			[
				'migration_id'    => 'tests_simple_migration',
				'status'          => Status::COMPLETED()->getValue(),
				'items_total'     => 1,
				'items_processed' => 1,
			]
		);

		ob_start();
		$this->ui->render_single( 'tests_simple_migration' );
		$output = ob_get_clean();

		// Normalize dynamic content (execution IDs and timestamps).
		$this->assertMatchesHtmlSnapshot( $output, [ $this, 'normalize_execution_data' ] );
	}

	/**
	 * Normalize dynamic execution data in HTML output for snapshot comparison.
	 *
	 * @param string $expected The expected HTML.
	 * @param string $current  The current HTML.
	 *
	 * @return array{0: string, 1: string} Normalized expected and current.
	 */
	public function normalize_execution_data( string $expected, string $current ): array {
		// Normalize execution IDs (e.g., #728366 -> #EXECUTION_ID).
		$id_pattern = '/#\d+/';
		$expected   = preg_replace( $id_pattern, '#EXECUTION_ID', $expected );
		$current    = preg_replace( $id_pattern, '#EXECUTION_ID', $current );

		// Normalize timestamps (e.g., "Jan 15, 2026 6:51 pm" -> "TIMESTAMP").
		$timestamp_pattern = '/\w{3} \d{1,2}, \d{4} \d{1,2}:\d{2} [ap]m/i';
		$expected          = preg_replace( $timestamp_pattern, 'TIMESTAMP', $expected );
		$current           = preg_replace( $timestamp_pattern, 'TIMESTAMP', $current );

		// Normalize value attributes for execution IDs.
		$value_pattern = '/value="\d+"/';
		$expected      = preg_replace( $value_pattern, 'value="EXECUTION_ID"', $expected );
		$current       = preg_replace( $value_pattern, 'value="EXECUTION_ID"', $current );

		return [ $current, $expected ];
	}
}

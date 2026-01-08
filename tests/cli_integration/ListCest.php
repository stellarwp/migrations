<?php
/**
 * CLI Integration Tests for the `list` command.
 *
 * @package StellarWP\Migrations
 */

namespace StellarWP\Migrations;

use Cli_integrationTester;
use PHPUnit\Framework\Assert;

class ListCest {
	/**
	 * @test
	 */
	public function it_should_list_all_migrations_as_json( Cli_integrationTester $I ): void {
		$output = $I->cliToString( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'list',
			'--format=json',
		] );

		$I->assertMatchesJsonSnapshot( $output );
	}

	/**
	 * @test
	 */
	public function it_should_list_all_migrations_as_table( Cli_integrationTester $I ): void {
		$output = $I->cliToString( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'list',
			'--format=table',
		] );

		$I->assertMatchesStringSnapshot( $output );
	}

	/**
	 * @test
	 */
	public function it_should_filter_migrations_by_tag( Cli_integrationTester $I ): void {
		$output = $I->cliToString( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'list',
			'--tags=data',
			'--format=json',
		] );

		$I->assertMatchesJsonSnapshot( $output );
	}

	/**
	 * @test
	 */
	public function it_should_list_migrations_as_csv( Cli_integrationTester $I ): void {
		$output = $I->cliToString( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'list',
			'--format=csv',
		] );

		// CSV should have header row and data rows.
		Assert::assertStringContainsString( 'id,label,description,tags,total_batches,can_run,is_applicable,status', $output );
		Assert::assertStringContainsString( 'tests_simple_migration', $output );

		$I->assertMatchesJsonSnapshot( $output );
	}

	/**
	 * @test
	 */
	public function it_should_list_migrations_as_yaml( Cli_integrationTester $I ): void {
		$output = $I->cliToString( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'list',
			'--format=yaml',
		] );

		// YAML format should contain specific structure.
		Assert::assertStringContainsString( 'id:', $output );
		Assert::assertStringContainsString( 'label:', $output );
		Assert::assertStringContainsString( 'tests_simple_migration', $output );

		$I->assertMatchesJsonSnapshot( $output );
	}

	/**
	 * @test
	 */
	public function it_should_filter_by_multiple_tags( Cli_integrationTester $I ): void {
		$output = $I->cliToString( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'list',
			'--tags=data,test',
			'--format=json',
		] );

		$migrations = json_decode( $output, true );
		Assert::assertIsArray( $migrations );

		// Should include multi_batch (has 'data' tag) and failing (has 'test' tag).
		$ids = array_column( $migrations, 'id' );
		Assert::assertContains( 'tests_multi_batch_migration', $ids );
		Assert::assertContains( 'tests_failing_migration', $ids );

		$I->assertMatchesJsonSnapshot( $output );
	}

	/**
	 * @test
	 */
	public function it_should_return_no_migrations_for_nonexistent_tag( Cli_integrationTester $I ): void {
		$output = $I->cliToString( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'list',
			'--tags=nonexistent_tag_xyz',
		] );

		Assert::assertStringContainsString( 'No migrations found', $output );

		$I->assertMatchesJsonSnapshot( $output );
	}

	/**
	 * @test
	 */
	public function it_should_show_is_applicable_false_for_non_applicable_migration( Cli_integrationTester $I ): void {
		$output = $I->cliToString( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'list',
			'--tags=legacy',
			'--format=json',
		] );

		$migrations = json_decode( $output, true );
		Assert::assertIsArray( $migrations );
		Assert::assertNotEmpty( $migrations );

		// The not_applicable_migration should have is_applicable = false.
		$not_applicable = array_filter( $migrations, fn( $m ) => $m['id'] === 'tests_not_applicable_migration' );
		Assert::assertNotEmpty( $not_applicable );

		$migration = array_values( $not_applicable )[0];
		Assert::assertFalse( $migration['is_applicable'] );

		$I->assertMatchesJsonSnapshot( $output );
	}

	/**
	 * @test
	 */
	public function it_should_show_correct_total_batches( Cli_integrationTester $I ): void {
		$output = $I->cliToString( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'list',
			'--format=json',
		] );

		$migrations = json_decode( $output, true );
		Assert::assertIsArray( $migrations );

		// Find the multi-batch migration.
		$multi_batch = array_filter( $migrations, fn( $m ) => $m['id'] === 'tests_multi_batch_migration' );
		Assert::assertNotEmpty( $multi_batch );

		$migration = array_values( $multi_batch )[0];
		// 15 items / 5 per batch = 3 batches.
		Assert::assertEquals( 3, $migration['total_batches'] );

		// Simple migration should have 1 batch.
		$simple = array_filter( $migrations, fn( $m ) => $m['id'] === 'tests_simple_migration' );
		$simple_migration = array_values( $simple )[0];
		Assert::assertEquals( 1, $simple_migration['total_batches'] );

		$I->assertMatchesJsonSnapshot( $output );
	}

	/**
	 * @test
	 */
	public function it_should_show_tags_in_output( Cli_integrationTester $I ): void {
		$output = $I->cliToString( [
			tests_migrations_cli_integration_get_prefix(),
			'migrations',
			'list',
			'--format=json',
		] );

		$migrations = json_decode( $output, true );

		// Find multi-batch migration and check tags.
		$multi_batch = array_filter( $migrations, fn( $m ) => $m['id'] === 'tests_multi_batch_migration' );
		$migration   = array_values( $multi_batch )[0];

		Assert::assertIsArray( $migration['tags'] );
		Assert::assertContains( 'data', $migration['tags'] );
		Assert::assertContains( 'batch', $migration['tags'] );

		$I->assertMatchesJsonSnapshot( $output );
	}
}

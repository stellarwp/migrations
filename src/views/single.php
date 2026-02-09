<?php
/**
 * Single Migration Detail Template.
 *
 * @since 0.0.1
 * @version 0.0.1
 *
 * @package StellarWP\Migrations
 *
 * @var StellarWP\Migrations\Contracts\Migration        $migration     The migration object.
 * @var list<array<string,mixed>>                       $executions    List of execution records.
 * @var string                                          $rest_base_url REST API base URL.
 */

defined( 'ABSPATH' ) || exit;

use StellarWP\Migrations\Config;
use StellarWP\Migrations\Contracts\Migration;

if ( ! isset( $migration ) || ! $migration instanceof Migration ) {
	return;
}

$migration_id    = $migration->get_id();
$migration_label = $migration->get_label();
$description     = $migration->get_description();
$rest_base_url ??= '';
$executions    ??= [];

$template = Config::get_template_engine();
?>
<div class="wrap stellarwp-migration-single" data-rest-url="<?php echo esc_url( $rest_base_url ); ?>" data-migration-id="<?php echo esc_attr( $migration_id ); ?>">
	<header class="stellarwp-migration-single__header">
		<h1 class="stellarwp-migration-single__label"><?php echo esc_html( $migration_label ); ?></h1>
		<p class="stellarwp-migration-single__description"><?php echo esc_html( $description ); ?></p>
	</header>

	<section class="stellarwp-migration-single__section" aria-labelledby="stellarwp-migration-status-title">
		<h2 id="stellarwp-migration-status-title" class="stellarwp-migration-single__section-title"><?php esc_html_e( 'Status', 'stellarwp-migrations' ); ?></h2>
		<?php
		$template->template(
			'components/migration-status-card',
			[
				'migration'  => $migration,
				'executions' => $executions,
			]
		);
		?>
	</section>

	<section class="stellarwp-migration-single__section" aria-labelledby="stellarwp-migration-config-title">
		<h2 id="stellarwp-migration-config-title" class="stellarwp-migration-single__section-title"><?php esc_html_e( 'Configuration', 'stellarwp-migrations' ); ?></h2>
		<?php
		$template->template(
			'components/configuration-box',
			[ 'migration' => $migration ]
		);
		?>
	</section>

	<section class="stellarwp-migration-single__section" aria-labelledby="stellarwp-migration-logs-title">
		<h2 id="stellarwp-migration-logs-title" class="stellarwp-migration-single__section-title"><?php esc_html_e( 'Logs', 'stellarwp-migrations' ); ?></h2>
		<?php
		$template->template(
			'components/execution-logs',
			[
				'migration'     => $migration,
				'executions'    => $executions,
				'rest_base_url' => $rest_base_url,
			]
		);
		?>
	</section>
</div>

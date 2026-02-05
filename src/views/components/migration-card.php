<?php
/**
 * Migration Card Component Template.
 *
 * @since 0.0.1
 * @version 0.0.1
 *
 * @package StellarWP\Migrations
 *
 * @var StellarWP\Migrations\Contracts\Migration $migration Migration object.
 */

defined( 'ABSPATH' ) || exit;

use StellarWP\Migrations\Admin\Provider as Admin_Provider;
use StellarWP\Migrations\Config;
use StellarWP\Migrations\Contracts\Migration;
use StellarWP\Migrations\Enums\Status;
use StellarWP\Migrations\Utilities\Migration_UI;

if ( ! isset( $migration ) || ! $migration instanceof Migration ) {
	return;
}

$migration_ui = new Migration_UI( $migration );

$migration_id     = $migration->get_id();
$single_url       = Admin_Provider::get_single_url( $migration_id );
$migration_label  = $migration->get_label();
$description      = $migration->get_description();
$migration_tags   = $migration->get_tags();
$migration_status = $migration_ui->get_display_status();
$status_value     = $migration_status->getValue();
$status_label     = $migration_ui->get_display_status_label();

$template = Config::get_template_engine();
?>
<div class="stellarwp-migration-card" data-migration-id="<?php echo esc_attr( $migration_id ); ?>">
	<div class="stellarwp-migration-card__header">
		<h3 class="stellarwp-migration-card__label">
			<a href="<?php echo esc_url( $single_url ); ?>"><?php echo esc_html( $migration_label ); ?></a>
		</h3>
		<?php if ( ! empty( $migration_tags ) ) : ?>
			<div class="stellarwp-migration-card__tags">
				<?php foreach ( $migration_tags as $migration_tag ) : ?>
					<span class="stellarwp-migration-card__tag"><?php echo esc_html( $migration_tag ); ?></span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<p class="stellarwp-migration-card__description">
		<?php echo esc_html( $description ); ?>
	</p>
	<hr class="stellarwp-migration-card__separator" />
	<div class="stellarwp-migration-card__footer">
		<div class="stellarwp-migration-card__status">
			<span class="stellarwp-migration-card__status-label stellarwp-migration-card__status-label--<?php echo esc_attr( $status_value ); ?>">
				<?php echo esc_html( $status_label ); ?>
			</span>

			<?php if ( ! $migration_status->equals( Status::NOT_APPLICABLE() ) ) : ?>
				<?php
				$template->template(
					'components/progress-text',
					[
						'migration'    => $migration,
						'migration_ui' => $migration_ui,
					]
				);
				?>
			<?php endif; ?>
		</div>

		<?php if ( ! $migration_status->equals( Status::NOT_APPLICABLE() ) ) : ?>
			<?php
			$template->template(
				'components/progress-bar',
				[
					'migration' => $migration,
				]
			);
			?>
		<?php endif; ?>

		<?php
		$template->template(
			'components/migration-actions',
			[
				'migration'    => $migration,
				'migration_ui' => $migration_ui,
			]
		);
		?>
	</div>
	<div class="stellarwp-migration-card__message" style="display: none;"></div>
</div>

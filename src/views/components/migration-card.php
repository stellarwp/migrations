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

if ( ! isset( $migration ) || ! $migration instanceof Migration ) {
	return;
}

$migration_id     = $migration->get_id();
$single_url       = Admin_Provider::get_single_url( $migration_id );
$migration_label  = $migration->get_label();
$description      = $migration->get_description();
$migration_status = $migration->get_status();
$migration_tags   = $migration->get_tags();

$status_value = $migration_status->getValue();
$status_label = $migration_status->get_label();
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
				Config::get_template_engine()->template(
					'components/progress-text',
					[ 'migration' => $migration ]
				);
				?>
			<?php endif; ?>
		</div>

		<?php if ( ! $migration_status->equals( Status::NOT_APPLICABLE() ) ) : ?>
			<?php
			Config::get_template_engine()->template(
				'components/progress-bar',
				[ 'migration' => $migration ]
			);
			?>
		<?php endif; ?>

		<?php
		Config::get_template_engine()->template(
			'components/migration-actions',
			[
				'migration' => $migration,
			]
		);
		?>
	</div>
	<div class="stellarwp-migration-card__message" style="display: none;"></div>
</div>

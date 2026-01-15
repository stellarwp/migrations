<?php
/**
 * Migrations List Template.
 *
 * @since 0.0.1
 *
 * @package StellarWP\Migrations\views
 *
 * @var list<StellarWP\Migrations\Contracts\Migration>                             $migrations    List of migrations objects.
 * @var list<string>                                                               $all_tags      All available tags.
 * @var array{tags: list<string>, show_completed: bool, show_non_applicable: bool} $filters       Current filter values.
 * @var string                                                                     $rest_base_url REST API base URL.
 */

defined( 'ABSPATH' ) || exit;

use StellarWP\Migrations\Config;
use StellarWP\Migrations\Utilities\Cast;

$migrations    ??= [];
$all_tags      ??= [];
$filters       ??= [];
$rest_base_url ??= '';

$template = Config::get_template_engine();

?>
<div class="wrap">
	<form method="get" class="stellarwp-migrations-filters">
		<?php
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only, no state change.
		if ( isset( $_GET['page'] ) ) :
			?>
			<input type="hidden" name="page" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( Cast::to_string($_GET['page']) ) ) ); ?>" />
			<?php
		endif;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		?>

		<div class="stellarwp-migrations-filters__row">
			<?php if ( ! empty( $all_tags ) ) : ?>
				<div class="stellarwp-migrations-filters__field stellarwp-migrations-filters__field--tags">
					<select
						id="stellarwp-migrations-tags"
						name="tags[]"
						multiple="multiple"
						class="stellarwp-migrations-select2"
						data-placeholder="<?php esc_attr_e( 'Filter by tags...', 'stellarwp-migrations' ); ?>"
					>
						<?php foreach ( $all_tags as $filter_tag ) : ?>
							<option value="<?php echo esc_attr( $filter_tag ); ?>" <?php selected( in_array( $filter_tag, $filters['tags'], true ) ); ?>>
								<?php echo esc_html( $filter_tag ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<div class="stellarwp-migrations-filters__actions">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Filter', 'stellarwp-migrations' ); ?>
				</button>
				<a href="<?php echo esc_url( remove_query_arg( [ 'tags', 'show_completed', 'show_non_applicable' ] ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Reset', 'stellarwp-migrations' ); ?>
				</a>
			</div>

			<div class="stellarwp-migrations-filters__checkboxes">
				<label>
					<input
						type="checkbox"
						name="show_completed"
						value="1"
						<?php checked( $filters['show_completed'] ); ?>
						onchange="this.form.submit();"
					/>
					<?php esc_html_e( 'Show Completed', 'stellarwp-migrations' ); ?>
				</label>
				<label>
					<input
						type="checkbox"
						name="show_non_applicable"
						value="1"
						<?php checked( $filters['show_non_applicable'] ); ?>
						onchange="this.form.submit();"
					/>
					<?php esc_html_e( 'Show Non-Applicable', 'stellarwp-migrations' ); ?>
				</label>
			</div>
		</div>
	</form>

	<div class="stellarwp-migrations-list" data-rest-url="<?php echo esc_url( $rest_base_url ); ?>">
		<?php if ( empty( $migrations ) ) : ?>
			<div class="stellarwp-migrations-empty">
				<p><?php esc_html_e( 'No migrations to display.', 'stellarwp-migrations' ); ?></p>
			</div>
		<?php else : ?>
			<?php foreach ( $migrations as $migration ) : ?>
				<?php
				$template->template(
					'components/migration-card',
					[
						'migration' => $migration,
					]
				);
				?>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>

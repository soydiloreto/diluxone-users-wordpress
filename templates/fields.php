<?php
/**
 * The person's fields, for editing.
 *
 * Overridable from the theme at:
 *   wp-content/themes/<your-theme>/diluxone-users/fields.php
 *
 * @var int                              $user_id
 * @var array<int, array<string, mixed>> $fields
 * @var string                           $group
 * @var string                           $title
 * @var string                           $state
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

if ( array() === $fields ) {
	return;
}
?>
<div class="diluxone-users diluxone-users-fields">

	<?php if ( '' !== $title ) : ?>
		<h2 class="diluxone-users-fields__title"><?php echo esc_html( $title ); ?></h2>
	<?php endif; ?>

	<?php if ( 'saved' === $state ) : ?>
		<p class="diluxone-users-notice diluxone-users-notice--ok"><?php esc_html_e( 'Saved.', 'diluxone-users' ); ?></p>
	<?php elseif ( 'missing' === $state ) : ?>
		<p class="diluxone-users-notice diluxone-users-notice--error"><?php esc_html_e( 'Some required fields are missing.', 'diluxone-users' ); ?></p>
	<?php endif; ?>

	<form class="diluxone-users-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="diluxone_users_fields_save">
		<input type="hidden" name="diluxone_users_group" value="<?php echo esc_attr( $group ); ?>">
		<?php wp_nonce_field( 'diluxone_users_fields_save' ); ?>

		<?php foreach ( $fields as $diluxone_users_field ) : ?>
			<div class="diluxone-users-field diluxone-users-field--<?php echo esc_attr( $diluxone_users_field['type'] ); ?>">
				<?php if ( 'checkbox' !== $diluxone_users_field['type'] ) : ?>
					<label for="<?php echo esc_attr( $diluxone_users_field['key'] ); ?>">
						<?php echo esc_html( $diluxone_users_field['label'] ); ?>
						<?php if ( $diluxone_users_field['required'] ) : ?>
							<span class="diluxone-users-field__required" aria-hidden="true">*</span>
						<?php endif; ?>
					</label>
				<?php endif; ?>

				<?php diluxone_users_field_input( $diluxone_users_field, diluxone_users_value( $user_id, $diluxone_users_field['key'] ) ); ?>

				<?php if ( '' !== $diluxone_users_field['help'] ) : ?>
					<p class="diluxone-users-field__help"><?php echo esc_html( $diluxone_users_field['help'] ); ?></p>
				<?php endif; ?>

				<?php $diluxone_users_nota = diluxone_users_field_edit_note( $diluxone_users_field, $user_id ); ?>
				<?php if ( '' !== $diluxone_users_nota ) : ?>
					<p class="diluxone-users-field__help diluxone-users-field__limit"><?php echo esc_html( $diluxone_users_nota ); ?></p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<button type="submit" class="diluxone-users-button"><?php esc_html_e( 'Save', 'diluxone-users' ); ?></button>
	</form>
</div>

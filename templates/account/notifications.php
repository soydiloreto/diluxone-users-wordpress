<?php
/**
 * Notificaciones.
 *
 * @var array<string, array<string, string>> $prefs
 * @var WP_User                              $user
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;
?>

<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo elige el mensaje. ?>
<?php if ( isset( $_GET['diluxone-users'] ) && 'saved' === sanitize_key( wp_unslash( $_GET['diluxone-users'] ) ) ) : ?>
	<p class="diluxone-users-notice diluxone-users-notice--ok"><?php esc_html_e( 'Saved.', 'diluxone-users' ); ?></p>
<?php endif; ?>

<?php if ( array() === $prefs ) : ?>
	<p><?php esc_html_e( 'This site does not send any notifications you can turn off.', 'diluxone-users' ); ?></p>
<?php else : ?>
	<?php diluxone_users_panel_open( __( 'What we email you about', 'diluxone-users' ), true ); ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="diluxone-users-form">
		<input type="hidden" name="action" value="diluxone_users_notifications">
		<?php wp_nonce_field( 'diluxone_users_notifications' ); ?>

		<?php
		foreach ( $prefs as $diluxone_users_key => $diluxone_users_pref ) :
			$diluxone_users_saved = get_user_meta( $user->ID, $diluxone_users_key, true );
			$diluxone_users_on    = '' === (string) $diluxone_users_saved ? ! empty( $diluxone_users_pref['default'] ) : (bool) $diluxone_users_saved;
			?>
			<label class="diluxone-users-check">
				<input type="checkbox" name="<?php echo esc_attr( $diluxone_users_key ); ?>" value="1" <?php checked( $diluxone_users_on ); ?>>
				<span>
					<?php echo esc_html( $diluxone_users_pref['label'] ); ?>
					<?php if ( ! empty( $diluxone_users_pref['help'] ) ) : ?>
						<small><?php echo esc_html( $diluxone_users_pref['help'] ); ?></small>
					<?php endif; ?>
				</span>
			</label>
		<?php endforeach; ?>

		<button type="submit" class="diluxone-users-button"><?php esc_html_e( 'Save', 'diluxone-users' ); ?></button>
	</form>
	<?php diluxone_users_panel_close(); ?>
<?php endif; ?>

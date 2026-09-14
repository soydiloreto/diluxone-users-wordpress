<?php
/**
 * The passkeys screen.
 *
 * Its own file, next to auth-passkeys.php, and the pair is the whole feature:
 * the two of them together can be moved somewhere else and nothing in the
 * plugin notices — no screen loses a tab it had written down, because no
 * screen had it written down.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/** Its tab on the security screen. */
function diluxone_users_passkeys_panel(): void {
	diluxone_users_register_panel(
		DILUXONE_USERS_SECURITY,
		'passkeys',
		array(
			'label'    => __( 'Passkeys', 'diluxone-users' ),
			'position' => 20,
			'render'   => 'diluxone_users_screen_login_passkeys',
			'save'     => 'diluxone_users_passkeys_settings_save',
		)
	);
}
add_action( 'diluxone_users_register_panels', 'diluxone_users_passkeys_panel' );

/**
 * Saves the settings.
 *
 * Not `diluxone_users_passkeys_save()`: that name was already taken by
 * auth-passkeys.php, which saves a person's passkey. Two different things,
 * and PHP noticed before anybody else did.
 */
function diluxone_users_passkeys_settings_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	diluxone_users_save_options(
		array(
			'diluxone_users_passkey_enabled' => isset( $_POST['diluxone_users_passkey_enabled'] ) ? 1 : 0,
			'diluxone_users_passkey_where'   => sanitize_key( wp_unslash( $_POST['diluxone_users_passkey_where'] ?? 'any' ) ),
			'diluxone_users_passkey_verify'  => isset( $_POST['diluxone_users_passkey_verify'] ) ? 1 : 0,
		)
	);
	// phpcs:enable
}

/** Passkeys: whether they are offered, which ones are accepted and what is required. */
function diluxone_users_screen_login_passkeys(): void {
	diluxone_users_intro( __( 'A passkey is a private key that lives on the person’s device or keychain and never leaves it. There is nothing on this side worth stealing, nothing to reuse on another site, and it cannot be phished: the browser refuses to sign for a domain that is not the one it was made for.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Offer them', 'diluxone-users' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="diluxone_users_passkey_enabled" value="1" <?php checked( diluxone_users_option( 'diluxone_users_passkey_enabled' ), 1 ); ?>>
					<?php esc_html_e( 'People can add passkeys and sign in with them', 'diluxone-users' ); ?>
				</label>
				<p class="description">
					<?php
					printf(
							/* translators: %s: the domain the passkeys end up tied to */
						esc_html__( 'They get tied to %s. If the site moves to another domain, the passkeys made here stop working and have to be added again — there is no way around that, and it is exactly what makes them unphishable.', 'diluxone-users' ),
						'<code>' . esc_html( diluxone_users_passkey_rp_id() ) . '</code>'
					);
					?>
				</p>
				<?php if ( ! is_ssl() && 'local' !== wp_get_environment_type() ) : ?>
					<p class="description diluxone-users-danger"><?php esc_html_e( 'This site is not on HTTPS. Browsers will refuse passkeys until it is.', 'diluxone-users' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Which ones', 'diluxone-users' ); ?></th>
			<td>
				<?php
				$where = array(
					'any'    => __( 'Any: the device being used, a USB key, or another phone', 'diluxone-users' ),
					'device' => __( 'Only the device being used', 'diluxone-users' ),
				);

				foreach ( $where as $key => $label ) :
					?>
					<label class="diluxone-users-roles__item">
						<input type="radio" name="diluxone_users_passkey_where" value="<?php echo esc_attr( $key ); ?>" <?php checked( diluxone_users_option( 'diluxone_users_passkey_where' ), $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'The first one lets somebody register from their laptop using their phone, and lets a hardware key work. The second keeps everything on the machine in front of them, which some organisations require and everybody else finds annoying.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Ask who they are', 'diluxone-users' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="diluxone_users_passkey_verify" value="1" <?php checked( diluxone_users_option( 'diluxone_users_passkey_verify' ), 1 ); ?>>
					<?php esc_html_e( 'Require the fingerprint, the face or the PIN', 'diluxone-users' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'This is what makes a passkey count as two things at once: something they have and something they are. With it off, whoever is holding an unlocked device gets in, and the passkey is worth one factor.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

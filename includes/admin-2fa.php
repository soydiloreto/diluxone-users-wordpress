<?php
/**
 * The second-factor screen.
 *
 * Deliberately its own file, next to auth.php and auth-totp.php: the second
 * factor is one feature and it lives in one place, so it can be moved, turned
 * off or replaced without editing a screen that belongs to something else.
 *
 * A second factor is NOT the e-mail sign-in link. The link is how somebody
 * gets in with no password at all; the second factor is what is asked *after*
 * they got in, on top of whatever they used. The plugin knows the difference
 * — see diluxone_users_2fa_worth_it_on_link().
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/** Its tab on the security screen. */
function diluxone_users_2fa_panel(): void {
	diluxone_users_register_panel(
		DILUXONE_USERS_SECURITY,
		'2fa',
		array(
			'label'    => __( 'Two-step verification', 'diluxone-users' ),
			'position' => 10,
			'render'   => 'diluxone_users_screen_login_2fa',
			'save'     => 'diluxone_users_2fa_save',
		)
	);
}
add_action( 'diluxone_users_register_panels', 'diluxone_users_2fa_panel' );

/** Saves it. */
function diluxone_users_2fa_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	diluxone_users_save_options(
		array_merge(
			diluxone_users_scope_posted( 'diluxone_users_2fa' ),
			array(
				'diluxone_users_2fa_mode'          => sanitize_key( wp_unslash( $_POST['diluxone_users_2fa_mode'] ?? 'optional' ) ),
				'diluxone_users_2fa_methods'       => array_map( 'sanitize_key', (array) wp_unslash( $_POST['diluxone_users_2fa_methods'] ?? array() ) ),
				'diluxone_users_2fa_link'          => sanitize_key( wp_unslash( $_POST['diluxone_users_2fa_link'] ?? 'auto' ) ),
				'diluxone_users_2fa_remember_days' => absint( wp_unslash( $_POST['diluxone_users_2fa_remember_days'] ?? 30 ) ),
			)
		)
	);
	// phpcs:enable
}

/**
 * The second factor: who is asked for it, with what, and when not.
 *
 * The methods are listed from the registry and not by hand: if an add-on adds
 * one, it shows up here on its own.
 */
function diluxone_users_screen_login_2fa(): void {
	diluxone_users_intro( __( 'One more thing after the password or the link: a code that only that person has. Whoever gets hold of an email still does not get in.', 'diluxone-users' ) );

	// All of them are asked for, not only the ones turned on: the checkbox of a
	// method that is off has to exist in order to turn it on.
	$all     = (array) apply_filters(
		'diluxone_users_2fa_methods',
		array(
			'email' => array( 'label' => __( 'A code by email', 'diluxone-users' ) ),
			'totp'  => array( 'label' => __( 'An authenticator app', 'diluxone-users' ) ),
		)
	);
	$enabled = (array) diluxone_users_option( 'diluxone_users_2fa_methods' );
	$roles   = (array) diluxone_users_option( 'diluxone_users_2fa_roles' );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'When it is asked for', 'diluxone-users' ); ?></th>
			<td>
				<?php
				$modes = array(
					'optional' => __( 'Optional: whoever wants it turns it on from their profile', 'diluxone-users' ),
					'required' => __( 'Required: everybody who can use it has to', 'diluxone-users' ),
					'off'      => __( 'Off: it is not offered at all', 'diluxone-users' ),
				);

				foreach ( $modes as $key => $label ) :
					?>
					<label class="diluxone-users-roles__item">
						<input type="radio" name="diluxone_users_2fa_mode" value="<?php echo esc_attr( $key ); ?>" <?php checked( diluxone_users_option( 'diluxone_users_2fa_mode' ), $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'With what', 'diluxone-users' ); ?></th>
			<td>
				<?php foreach ( $all as $key => $method ) : ?>
					<label class="diluxone-users-roles__item">
						<input type="checkbox" name="diluxone_users_2fa_methods[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $enabled, true ) ); ?>>
						<?php echo esc_html( $method['label'] ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'The app is the stronger one: the code never travels. The email is the one people actually turn on, because there is nothing to install.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'To whom', 'diluxone-users' ); ?></th>
			<td>
				<?php
				diluxone_users_scope_control(
					'diluxone_users_2fa',
					__( 'Asking only the roles that can change things is the usual middle ground: the second step where the friction is worth it.', 'diluxone-users' )
				);
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Coming in by email link', 'diluxone-users' ); ?></th>
			<td>
				<?php
				$link = array(
					'auto'   => __( 'Work it out: ask, unless the second step is another email', 'diluxone-users' ),
					'always' => __( 'Always ask', 'diluxone-users' ),
					'never'  => __( 'Never ask', 'diluxone-users' ),
				);

				foreach ( $link as $key => $label ) :
					?>
					<label class="diluxone-users-roles__item">
						<input type="radio" name="diluxone_users_2fa_link" value="<?php echo esc_attr( $key ); ?>" <?php checked( diluxone_users_option( 'diluxone_users_2fa_link' ), $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'A code sent to the same inbox the person just opened to follow the link does not prove anything the link did not prove already. An authenticator app does. That is the whole rule, and it is why the first option exists: it asks when asking is worth something.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="diluxone_users_2fa_remember_days"><?php esc_html_e( 'Remember the browser', 'diluxone-users' ); ?></label></th>
			<td>
				<input type="number" id="diluxone_users_2fa_remember_days" name="diluxone_users_2fa_remember_days" class="small-text" min="0" value="<?php echo esc_attr( (string) diluxone_users_option( 'diluxone_users_2fa_remember_days' ) ); ?>">
				<?php esc_html_e( 'days — 0 to ask every time', 'diluxone-users' ); ?>
				<p class="description"><?php esc_html_e( 'A signed cookie, no more: it does not let anybody in, it only saves repeating the step on a browser that already passed it.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

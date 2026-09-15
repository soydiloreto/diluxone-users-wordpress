<?php
/**
 * The passkeys screen.
 *
 * Its own file, next to auth-passkeys.php, and the pair is the whole feature:
 * the two of them together can be moved somewhere else and nothing in the
 * plugin notices — no screen loses a tab it had written down, because no
 * screen had it written down.
 *
 * Whether passkeys are offered at all is NOT decided here. That box lives on
 * Access › Ways in, beside the e-mail link and the password, because what it
 * answers is "what can somebody sign in with" and that question is asked once,
 * in one place. What is left here is how they behave once they are offered —
 * the same split as everywhere else: turning a thing on lives with the other
 * things that can be turned on, setting it up lives with the thing itself.
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
 *
 * `diluxone_users_passkey_enabled` is not written here and must not be: it is
 * written by the screen that shows it. Saving an option from a form that does
 * not carry it is how a setting gets turned off by somebody who only came to
 * change something else.
 */
function diluxone_users_passkeys_settings_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	diluxone_users_save_options(
		array(
			'diluxone_users_passkey_where'  => sanitize_key( wp_unslash( $_POST['diluxone_users_passkey_where'] ?? 'any' ) ),
			'diluxone_users_passkey_verify' => isset( $_POST['diluxone_users_passkey_verify'] ) ? 1 : 0,
		)
	);
	// phpcs:enable
}

/** Passkeys: whether they are offered, which ones are accepted and what is required. */
function diluxone_users_screen_login_passkeys(): void {
	$on    = (bool) diluxone_users_option( 'diluxone_users_passkey_enabled' );
	$where = (string) diluxone_users_option( 'diluxone_users_passkey_where' );
	$ways  = diluxone_users_admin_url( 'diluxone-users-login', array( 'tab' => 'ways' ) );

	diluxone_users_intro( __( 'A passkey is a private key that lives on the person’s device or keychain and never leaves it. There is nothing on this side worth stealing, nothing to reuse on another site, and it cannot be phished: the browser refuses to sign for a domain that is not the one it was made for.', 'diluxone-users' ) );

	// The state first, and it is read from somewhere else: what is set below
	// only means something while passkeys are one of the ways in.
	diluxone_users_summary_table(
		array(
			array(
				'label'  => __( 'Offered on this site', 'diluxone-users' ),
				'state'  => $on ? 'active' : 'off',
				'detail' => $on
					? sprintf(
						/* translators: %s: the domain the passkeys end up tied to */
						esc_html__( 'People can add passkeys and sign in with them. They are tied to %s: if the site moves to another domain they stop working and have to be added again, which is exactly what makes them unphishable.', 'diluxone-users' ),
						'<code>' . esc_html( diluxone_users_passkey_rp_id() ) . '</code>'
					)
					: esc_html__( 'Nobody can add one or sign in with one. It is turned on beside the other ways in.', 'diluxone-users' ),
				'url'    => $ways,
				'change' => $on
					? __( 'Access › Ways in →', 'diluxone-users' )
					: __( 'Turn them on → Access › Ways in', 'diluxone-users' ),
			),
		)
	);

	if ( ! is_ssl() && 'local' !== wp_get_environment_type() ) {
		echo '<p class="description diluxone-users-danger">' . esc_html__( 'This site is not on HTTPS. Browsers will refuse passkeys until it is.', 'diluxone-users' ) . '</p>';
	}

	diluxone_users_ui_section( __( 'Which passkeys are accepted', 'diluxone-users' ) );

	// Dimmed rather than hidden or disabled: what is chosen today is what
	// applies the day somebody turns passkeys on, and a control that is not
	// submitted is a control that saves nothing.
	if ( ! $on ) {
		diluxone_users_not_now(
			__( 'Passkeys are not one of the ways in yet, so nothing below is in use. What is chosen here applies the day they are.', 'diluxone-users' ),
			$ways,
			__( 'Turn them on → Access › Ways in', 'diluxone-users' )
		);
	}

	diluxone_users_ui_choices(
		array(
			array(
				'name'    => 'diluxone_users_passkey_where',
				'value'   => 'any',
				'checked' => 'device' !== $where,
				'title'   => __( 'Any: the device being used, a USB key, or another phone', 'diluxone-users' ),
				'help'    => __( 'Somebody at a laptop can register using their phone, and a hardware key works. It is what most people expect.', 'diluxone-users' ),
			),
			array(
				'name'    => 'diluxone_users_passkey_where',
				'value'   => 'device',
				'checked' => 'device' === $where,
				'title'   => __( 'Only the device being used', 'diluxone-users' ),
				'help'    => __( 'Everything stays on the machine in front of them. Some organisations require it; everybody else finds it annoying.', 'diluxone-users' ),
			),
		)
	);

	diluxone_users_ui_section( __( 'Proof that it is them holding the device', 'diluxone-users' ) );

	diluxone_users_ui_choices(
		array(
			array(
				'type'    => 'checkbox',
				'name'    => 'diluxone_users_passkey_verify',
				'value'   => '1',
				'checked' => (bool) diluxone_users_option( 'diluxone_users_passkey_verify' ),
				'title'   => __( 'Require the fingerprint, the face or the PIN', 'diluxone-users' ),
				'help'    => __( 'This is what makes a passkey count as two things at once: something they have and something they are. Unticked, whoever is holding an unlocked device gets in, and the passkey is worth one factor.', 'diluxone-users' ),
			),
		)
	);
}

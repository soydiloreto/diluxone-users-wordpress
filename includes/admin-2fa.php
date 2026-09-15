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
 * What the three settings above add up to for somebody arriving by link.
 *
 * The answer depends on the mode, on the rule for the link and on whether any
 * method that is turned on arrives somewhere other than the inbox — three
 * controls on one screen, and until this said so out loud the only way to
 * know was to work it out. It is the same rule the sign-in runs, read from
 * the saved settings instead of from a person.
 *
 * @return array{state: string, line: string} A state for the pill, and the sentence beside it.
 */
function diluxone_users_2fa_link_today(): array {
	$mode = (string) diluxone_users_option( 'diluxone_users_2fa_mode' );
	$link = (string) diluxone_users_option( 'diluxone_users_2fa_link' );

	if ( 'off' === $mode ) {
		return array(
			'state' => 'off',
			'line'  => __( 'Nothing is asked: the second step is off for the whole site, whatever is chosen here.', 'diluxone-users' ),
		);
	}

	if ( 'always' === $link ) {
		return array(
			'state' => 'active',
			'line'  => __( 'The second step is asked of somebody who came in by link, whichever one they have.', 'diluxone-users' ),
		);
	}

	if ( 'never' === $link ) {
		return array(
			'state' => 'off',
			'line'  => __( 'Nothing is asked of somebody who came in by link: following it is the whole of it.', 'diluxone-users' ),
		);
	}

	foreach ( diluxone_users_2fa_methods() as $method ) {
		if ( 'email' !== ( $method['channel'] ?? 'email' ) ) {
			return array(
				'state' => 'active',
				'line'  => __( 'The second step is asked: a method that is on does not arrive by e-mail, so it proves something the link did not.', 'diluxone-users' ),
			);
		}
	}

	return array(
		'state' => 'off',
		'line'  => __( 'Nothing is asked: every method that is on arrives by e-mail, at the inbox the person just opened.', 'diluxone-users' ),
	);
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
			'email' => array(
				'label' => __( 'A code by email', 'diluxone-users' ),
				'help'  => __( 'Six digits to the address on the account. Nothing to install, which is why it is the one people actually turn on.', 'diluxone-users' ),
			),
			'totp'  => array(
				'label' => __( 'An authenticator app', 'diluxone-users' ),
				'help'  => __( 'Six digits that change every thirty seconds, from Google Authenticator, 1Password or Aegis. The stronger one: the code never travels.', 'diluxone-users' ),
			),
		)
	);
	$enabled = (array) diluxone_users_option( 'diluxone_users_2fa_methods' );
	$mode    = (string) diluxone_users_option( 'diluxone_users_2fa_mode' );
	$today   = diluxone_users_2fa_link_today();

	diluxone_users_ui_section( __( 'When it is asked for', 'diluxone-users' ) );

	diluxone_users_ui_choices(
		array(
			array(
				'name'    => 'diluxone_users_2fa_mode',
				'value'   => 'optional',
				'checked' => 'optional' === $mode,
				'title'   => __( 'Optional: whoever wants it turns it on from their account', 'diluxone-users' ),
				'help'    => __( 'Nobody is made to do anything, and whoever turned it on is asked from then on.', 'diluxone-users' ),
			),
			array(
				'name'    => 'diluxone_users_2fa_mode',
				'value'   => 'required',
				'checked' => 'required' === $mode,
				'title'   => __( 'Required: everybody it applies to has to set it up', 'diluxone-users' ),
				'help'    => __( 'They are walked through it the next time they sign in, and there is no way past.', 'diluxone-users' ),
			),
			array(
				'name'    => 'diluxone_users_2fa_mode',
				'value'   => 'off',
				'checked' => 'off' === $mode,
				'title'   => __( 'Off: it is not offered at all', 'diluxone-users' ),
				'help'    => __( 'Nobody is asked, not even whoever had it set up. What they set up is kept and works again the day this comes back on.', 'diluxone-users' ),
			),
		)
	);

	diluxone_users_ui_field_open( __( 'To whom', 'diluxone-users' ) );
	diluxone_users_scope_control( 'diluxone_users_2fa' );
	diluxone_users_ui_field_close( __( 'Asking only the roles that can change things is the usual middle ground: the second step where the friction is worth it.', 'diluxone-users' ) );

	diluxone_users_ui_section( __( 'With what', 'diluxone-users' ) );

	$cards = array();

	foreach ( $all as $key => $method ) {
		$cards[] = array(
			'type'    => 'checkbox',
			'name'    => 'diluxone_users_2fa_methods[]',
			'value'   => (string) $key,
			'checked' => in_array( (string) $key, $enabled, true ),
			'title'   => (string) $method['label'],
			'help'    => (string) ( $method['help'] ?? '' ),
		);
	}

	diluxone_users_ui_choices( $cards );

	diluxone_users_ui_section( __( 'Coming in by email link', 'diluxone-users' ) );

	diluxone_users_ui_choices(
		array(
			array(
				'name'    => 'diluxone_users_2fa_link',
				'value'   => 'auto',
				'checked' => 'auto' === (string) diluxone_users_option( 'diluxone_users_2fa_link' ),
				'title'   => __( 'Work it out: ask, unless the second step is another email', 'diluxone-users' ),
				'help'    => __( 'A code sent to the inbox the person has just opened to follow the link proves nothing the link did not prove already. An app does, so on an app it asks.', 'diluxone-users' ),
			),
			array(
				'name'    => 'diluxone_users_2fa_link',
				'value'   => 'always',
				'checked' => 'always' === (string) diluxone_users_option( 'diluxone_users_2fa_link' ),
				'title'   => __( 'Always ask', 'diluxone-users' ),
				'help'    => __( 'The second step on every link, including the errand of a code going back to the same inbox.', 'diluxone-users' ),
			),
			array(
				'name'    => 'diluxone_users_2fa_link',
				'value'   => 'never',
				'checked' => 'never' === (string) diluxone_users_option( 'diluxone_users_2fa_link' ),
				'title'   => __( 'Never ask', 'diluxone-users' ),
				'help'    => __( 'Following the link is the whole of it. The quickest way in, and the weakest of the three.', 'diluxone-users' ),
			),
		)
	);

	// What the three settings above come to right now. The dependency is on
	// screen because the alternative is deducing it from three controls.
	diluxone_users_ui_field_open( __( 'With that, today', 'diluxone-users' ) );
	echo '<p>' . diluxone_users_state_pill( $today['state'] ) . ' ' . esc_html( $today['line'] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the pill escapes its own.
	diluxone_users_ui_field_close();

	diluxone_users_ui_section( __( 'Remembering a browser', 'diluxone-users' ) );

	diluxone_users_ui_field_open( __( 'How long a browser is remembered', 'diluxone-users' ), 'diluxone_users_2fa_remember_days' );
	?>
	<input type="number" id="diluxone_users_2fa_remember_days" name="diluxone_users_2fa_remember_days" class="small-text" min="0" value="<?php echo esc_attr( (string) diluxone_users_option( 'diluxone_users_2fa_remember_days' ) ); ?>">
	<?php
	esc_html_e( 'days — 0 to ask every time', 'diluxone-users' );
	diluxone_users_ui_field_close( __( 'A signed cookie, no more: it does not let anybody in, it only saves repeating the step on a browser that already passed it.', 'diluxone-users' ) );
}

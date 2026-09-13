<?php
/**
 * The sign-in screen: how somebody who already has an account gets into it.
 *
 * Who gets an account in the first place is a different question and lives on
 * its own screen, next to this one. They were one screen with a tab each and
 * the tab was read as a step of the same thing — it is not: a site can open
 * registration and change nothing about signing in, and close it and change
 * nothing either.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/** The registration and sign-in screen, with its tabs. */
function diluxone_users_screen_login(): void {
	$tabs = array(
		'link'     => __( 'Getting in', 'diluxone-users' ),
		'email'    => __( 'The email', 'diluxone-users' ),
		'handle'   => __( 'Public name', 'diluxone-users' ),
		'2fa'      => __( 'Two-step verification', 'diluxone-users' ),
		'passkeys' => __( 'Passkeys', 'diluxone-users' ),
		'look'     => __( 'How it looks', 'diluxone-users' ),
	);

	$current = diluxone_users_tab( $tabs );

	if ( isset( $_POST['diluxone_users_options_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['diluxone_users_options_nonce'] ) ), 'diluxone_users_options' ) ) {
		diluxone_users_screen_login_save( $current );
		diluxone_users_notice( __( 'Settings saved.', 'diluxone-users' ) );
	}

	diluxone_users_screen_open( diluxone_users_screens()['diluxone-users-login'], 'diluxone-users-login', $tabs, $current );

	// The preview is a form of its own, and a form inside a form is thrown
	// away by the browser. That tab has nothing to save anyway.
	if ( 'look' === $current ) {
		diluxone_users_screen_login_look();
		diluxone_users_screen_close();

		return;
	}

	echo '<form method="post">';
	wp_nonce_field( 'diluxone_users_options', 'diluxone_users_options_nonce' );

	switch ( $current ) {
		case 'email':
			diluxone_users_screen_login_email();
			break;

		case 'handle':
			diluxone_users_screen_login_handle();
			break;

		case '2fa':
			diluxone_users_screen_login_2fa();
			break;

		case 'passkeys':
			diluxone_users_screen_login_passkeys();
			break;

		default:
			diluxone_users_screen_login_link();
	}

	submit_button();
	echo '</form>';

	diluxone_users_screen_close();
}

/** Saves only what the tab being looked at submits. */
function diluxone_users_screen_login_save( string $tab ): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the caller verifies it.
	if ( 'email' === $tab ) {
		diluxone_users_save_options(
			array(
				'diluxone_users_login_subject' => sanitize_text_field( wp_unslash( $_POST['diluxone_users_login_subject'] ?? '' ) ),
				'diluxone_users_login_body'    => sanitize_textarea_field( wp_unslash( $_POST['diluxone_users_login_body'] ?? '' ) ),
			)
		);

		return;
	}

	if ( 'passkeys' === $tab ) {
		diluxone_users_save_options(
			array(
				'diluxone_users_passkey_enabled' => isset( $_POST['diluxone_users_passkey_enabled'] ) ? 1 : 0,
				'diluxone_users_passkey_where'   => sanitize_key( wp_unslash( $_POST['diluxone_users_passkey_where'] ?? 'any' ) ),
				'diluxone_users_passkey_verify'  => isset( $_POST['diluxone_users_passkey_verify'] ) ? 1 : 0,
			)
		);

		return;
	}

	if ( '2fa' === $tab ) {
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

		return;
	}

	if ( 'handle' === $tab ) {
		diluxone_users_save_options(
			array(
				'diluxone_users_handle_enabled'  => isset( $_POST['diluxone_users_handle_enabled'] ) ? 1 : 0,
				'diluxone_users_handle_min'      => absint( wp_unslash( $_POST['diluxone_users_handle_min'] ?? 3 ) ),
				'diluxone_users_handle_max'      => absint( wp_unslash( $_POST['diluxone_users_handle_max'] ?? 30 ) ),
				'diluxone_users_handle_charset'  => sanitize_key( wp_unslash( $_POST['diluxone_users_handle_charset'] ?? 'strict' ) ),
				'diluxone_users_handle_spaces'   => sanitize_key( wp_unslash( $_POST['diluxone_users_handle_spaces'] ?? 'dash' ) ),
				'diluxone_users_handle_cooldown' => absint( wp_unslash( $_POST['diluxone_users_handle_cooldown'] ?? 30 ) ),
				'diluxone_users_handle_reserved' => sanitize_textarea_field( wp_unslash( $_POST['diluxone_users_handle_reserved'] ?? '' ) ),
			)
		);

		return;
	}

	diluxone_users_save_options(
		array(
			'diluxone_users_login_method'   => sanitize_key( wp_unslash( $_POST['diluxone_users_login_method'] ?? 'both' ) ),
			'diluxone_users_login_page'     => absint( wp_unslash( $_POST['diluxone_users_login_page'] ?? 0 ) ),
			'diluxone_users_login_expiry'   => absint( wp_unslash( $_POST['diluxone_users_login_expiry'] ?? 15 ) ),
			'diluxone_users_login_throttle' => absint( wp_unslash( $_POST['diluxone_users_login_throttle'] ?? 60 ) ),
			// Lives on this tab because it is about what the sign-in box
			// accepts, not about what a public name is.
			'diluxone_users_handle_login'   => isset( $_POST['diluxone_users_handle_login'] ) ? 1 : 0,
			'diluxone_users_sso_login'      => isset( $_POST['diluxone_users_sso_login'] ) ? 1 : 0,
		)
	);
	// phpcs:enable
}

/**
 * Every way into this site, in one place.
 *
 * The settings that open and close these live on four different screens, and
 * each one only ever said what it did on its own. What nobody could see was
 * the result: whether somebody can get in with a password on a site whose
 * accounts were all created by e-mail link, what happens to the person who
 * comes back from Google, whether the "forgot your password" link still leads
 * anywhere. This table answers that, and it reads the same settings the rest
 * of the screens write — there is no second source of truth.
 *
 * @return array<int, array{label: string, state: string, detail: string, url: string}>
 */
function diluxone_users_doors(): array {
	$method    = diluxone_users_login_method();
	$ready     = diluxone_users_sso_available();
	$providers = diluxone_users_sso_providers();
	$register  = (bool) diluxone_users_option( 'diluxone_users_login_register' );

	$doors = array();

	$doors[] = array(
		'label'  => __( 'A link sent by e-mail', 'diluxone-users' ),
		'state'  => diluxone_users_login_has_link() ? 'open' : 'closed',
		'detail' => diluxone_users_login_has_link()
			? __( 'The person types their e-mail and gets a single-use link. Nothing to remember and nothing to steal.', 'diluxone-users' )
			: __( 'Closed. Nobody can ask for a link, so an account with no usable password has no way in.', 'diluxone-users' ),
		'url'    => '',
	);

	$doors[] = array(
		'label'  => __( 'Username and password', 'diluxone-users' ),
		'state'  => diluxone_users_login_has_password() ? 'open' : 'closed',
		'detail' => diluxone_users_login_has_password()
			? __( 'WordPress\'s own form. Accounts created by e-mail link or by a social network carry a random password nobody knows — those people come in by link, or ask for a new password below.', 'diluxone-users' )
			: __( 'Closed: wp-login.php sends people to the sign-in page, and its registration form is switched off with it.', 'diluxone-users' ),
		'url'    => '',
	);

	$doors[] = array(
		'label'  => __( 'A social account', 'diluxone-users' ),
		'state'  => array() !== $ready ? 'open' : ( array() !== array_filter( array_keys( $providers ), 'diluxone_users_sso_configured' ) ? 'partial' : 'closed' ),
		'detail' => array() !== $ready
			? sprintf(
				/* translators: %s: the social networks that are working, separated by commas */
				__( 'Working: %s. Whoever comes back from one of these is signed in on the spot — the password plays no part, and if the site asks for a second step, it is still asked.', 'diluxone-users' ),
				implode( ', ', array_map( static fn( array $p ): string => (string) $p['name'], $ready ) )
			)
			: __( 'No network is working yet. Credentials are not enough: a provider has to pass its live test before it can be turned on.', 'diluxone-users' ),
		'url'    => diluxone_users_admin_url( 'diluxone-users-social' ),
	);

	$doors[] = array(
		'label'  => __( 'A passkey', 'diluxone-users' ),
		'state'  => diluxone_users_passkeys_enabled() ? 'open' : 'closed',
		'detail' => diluxone_users_passkeys_enabled()
			? __( 'For whoever added one. It asks for no second step: a passkey is already two of them in one.', 'diluxone-users' )
			: __( 'Closed. Nobody can add one, and the ones already added stop working.', 'diluxone-users' ),
		'url'    => diluxone_users_admin_url( 'diluxone-users-login', array( 'tab' => 'passkeys' ) ),
	);

	$doors[] = array(
		'label'  => __( 'Typing the public name instead of the e-mail', 'diluxone-users' ),
		'state'  => ( diluxone_users_option( 'diluxone_users_handle_enabled' ) && diluxone_users_option( 'diluxone_users_handle_login' ) ) ? 'open' : 'closed',
		'detail' => __( 'This opens no door of its own: the link always goes to the e-mail on the account, never to what was typed. It only saves people from remembering which address they used.', 'diluxone-users' ),
		'url'    => diluxone_users_admin_url( 'diluxone-users-login', array( 'tab' => 'handle' ) ),
	);

	$doors[] = array(
		'label'  => __( 'Asking for a new password', 'diluxone-users' ),
		'state'  => diluxone_users_login_has_password() ? 'open' : 'closed',
		'detail' => diluxone_users_login_has_password()
			? __( 'WordPress\'s "Lost your password?" works as it always did. It is the way out for somebody whose account was created without a password they know.', 'diluxone-users' )
			: __( 'Closed with the password form, and nothing is lost: on this site a password gets nobody in.', 'diluxone-users' ),
		'url'    => '',
	);

	$doors[] = array(
		'label'  => __( 'Creating an account', 'diluxone-users' ),
		'state'  => $register ? 'open' : 'closed',
		'detail' => $register
			? __( 'Anybody who gets in with an e-mail or a social account the site has never seen gets an account in the same step. There is no separate registration form.', 'diluxone-users' )
			: __( 'Only people who already have an account can get in. Somebody has to create them under Users.', 'diluxone-users' ),
		'url'    => diluxone_users_admin_url( 'diluxone-users-login', array( 'tab' => 'registration' ) ),
	);

	return $doors;
}

/** The doors, drawn. */
function diluxone_users_doors_table(): void {
	$labels = array(
		'open'    => __( 'Open', 'diluxone-users' ),
		'closed'  => __( 'Closed', 'diluxone-users' ),
		'partial' => __( 'Not yet', 'diluxone-users' ),
	);

	echo '<table class="widefat striped diluxone-users-doors"><tbody>';

	foreach ( diluxone_users_doors() as $door ) {
		printf(
			'<tr><td class="diluxone-users-doors__state"><span class="diluxone-users-pill diluxone-users-pill--%1$s">%2$s</span></td><th scope="row">%3$s</th><td>%4$s %5$s</td></tr>',
			esc_attr( 'open' === $door['state'] ? 'on' : ( 'partial' === $door['state'] ? 'pending' : 'off' ) ),
			esc_html( $labels[ $door['state'] ] ),
			esc_html( $door['label'] ),
			esc_html( $door['detail'] ),
			'' === $door['url'] ? '' : sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $door['url'] ),
				esc_html__( 'Change it', 'diluxone-users' )
			)
		);
	}

	echo '</tbody></table>';
}

/** How people get in: the door, the page and the timings. */
function diluxone_users_screen_login_link(): void {
	?>
	<h2><?php esc_html_e( 'Every way in, and what each one means', 'diluxone-users' ); ?></h2>
	<?php
	diluxone_users_intro( __( 'What is open right now, read from the same settings the screens below write. The choices are spread over several tabs; the consequence is not.', 'diluxone-users' ) );
	diluxone_users_doors_table();
	?>
	<h2><?php esc_html_e( 'The way in', 'diluxone-users' ); ?></h2>
	<?php

	diluxone_users_intro( __( 'The person types their email and gets a single-use link. There is no password to choose, to remember or to steal. Put the [diluxone_users_login] shortcode on the page you pick below.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Sign-in page', 'diluxone-users' ); ?></th>
			<td>
				<?php
				/** @var array<string, mixed> $diluxone_users_dropdown */
				$diluxone_users_dropdown = array(
					'name'              => 'diluxone_users_login_page',
					'selected'          => (int) diluxone_users_option( 'diluxone_users_login_page' ),
					'show_option_none'  => __( '— Use wp-login.php —', 'diluxone-users' ),
					'option_none_value' => 0,
				);

					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_dropdown_pages() escapes its own and prints it.
				wp_dropdown_pages( $diluxone_users_dropdown );
				?>
				<p class="description"><?php esc_html_e( 'The page holding the form. Without it, passwordless mode is not applied: it would leave the site with no way in.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'How people get in', 'diluxone-users' ); ?></th>
			<td>
				<?php diluxone_users_forzado_aviso( 'diluxone_users_login_method' ); ?>

				<?php
				$methods = array(
					'both'     => __( 'Both: the link, and username and password underneath', 'diluxone-users' ),
					'link'     => __( 'Only a link sent to their email — no passwords on this site', 'diluxone-users' ),
					'password' => __( 'Only the WordPress username and password', 'diluxone-users' ),
				);

				foreach ( $methods as $key => $label ) :
					?>
					<label class="diluxone-users-roles__item">
						<input type="radio" name="diluxone_users_login_method" value="<?php echo esc_attr( $key ); ?>" <?php checked( diluxone_users_login_method(), $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>

				<p class="description"><?php esc_html_e( 'Only the first option closes anything: with “only a link”, wp-login.php stops showing its form and the native registration is switched off, because otherwise both would still be there and the choice would be a decoration.', 'diluxone-users' ); ?></p>
				<p class="description">
					<?php
					printf(
							/* translators: %s: URL of the escape hatch */
						esc_html__( 'Even then there is an emergency way in for administrators: %s', 'diluxone-users' ),
						'<code>' . esc_html( wp_login_url() ) . '?diluxone-users-admin=1</code>'
					);
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'With a social account', 'diluxone-users' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="diluxone_users_sso_login" value="1" <?php checked( diluxone_users_option( 'diluxone_users_sso_login' ), 1 ); ?>>
					<?php esc_html_e( 'Show the buttons of the enabled providers on the form', 'diluxone-users' ); ?>
				</label>
				<?php diluxone_users_social_state(); ?>
				<p class="description"><?php esc_html_e( 'Turned off, the buttons go from the form and the e-mail link is the only way in shown there. Whoever already linked an account keeps it, and can still unlink it from their own account area.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'What can be typed in the box', 'diluxone-users' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="diluxone_users_handle_login" value="1" <?php checked( diluxone_users_option( 'diluxone_users_handle_login' ), 1 ); ?>>
					<?php esc_html_e( 'Accept the public name as well as the e-mail address', 'diluxone-users' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'It opens no door of its own: the link always goes to the e-mail on the account, never to what was typed. It only saves people from remembering which address they used.', 'diluxone-users' ); ?>
				</p>
				<?php if ( ! diluxone_users_option( 'diluxone_users_handle_enabled' ) ) : ?>
					<p class="description">
						<strong><?php esc_html_e( 'Public names are turned off, so this does nothing yet.', 'diluxone-users' ); ?></strong>
						<a href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-login', array( 'tab' => 'handle' ) ) ); ?>"><?php esc_html_e( 'Turn them on', 'diluxone-users' ); ?></a>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="diluxone_users_login_expiry"><?php esc_html_e( 'The link expires after', 'diluxone-users' ); ?></label></th>
			<td>
				<input type="number" id="diluxone_users_login_expiry" name="diluxone_users_login_expiry" min="1" max="1440" class="small-text" value="<?php echo esc_attr( (string) diluxone_users_option( 'diluxone_users_login_expiry' ) ); ?>">
				<?php esc_html_e( 'minutes', 'diluxone-users' ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="diluxone_users_login_throttle"><?php esc_html_e( 'Wait between requests', 'diluxone-users' ); ?></label></th>
			<td>
				<input type="number" id="diluxone_users_login_throttle" name="diluxone_users_login_throttle" min="0" class="small-text" value="<?php echo esc_attr( (string) diluxone_users_option( 'diluxone_users_login_throttle' ) ); ?>">
				<?php esc_html_e( 'seconds, for the same email address', 'diluxone-users' ); ?>
				<p class="description"><?php esc_html_e( 'Stops the form being used as a machine for emailing third parties.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

/** The text of the e-mail carrying the sign-in link. */
function diluxone_users_screen_login_email(): void {
	diluxone_users_intro( __( 'This is what lands in the inbox. Leave it empty to use the text the plugin ships with.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="diluxone_users_login_subject"><?php esc_html_e( 'Subject', 'diluxone-users' ); ?></label></th>
			<td><input type="text" id="diluxone_users_login_subject" name="diluxone_users_login_subject" class="large-text" value="<?php echo esc_attr( (string) diluxone_users_option( 'diluxone_users_login_subject' ) ); ?>" placeholder="<?php echo esc_attr( diluxone_users_login_subject() ); ?>"></td>
		</tr>
		<tr>
			<th scope="row"><label for="diluxone_users_login_body"><?php esc_html_e( 'Message', 'diluxone-users' ); ?></label></th>
			<td>
				<textarea id="diluxone_users_login_body" name="diluxone_users_login_body" class="large-text code" rows="9" placeholder="<?php echo esc_attr( diluxone_users_login_body( '{link}' ) ); ?>"><?php echo esc_textarea( (string) diluxone_users_option( 'diluxone_users_login_body' ) ); ?></textarea>
				<p class="description">
					<?php
					printf(
							/* translators: 1 and 2: placeholders that get replaced */
						esc_html__( '%1$s and %2$s are replaced. Empty uses the text above.', 'diluxone-users' ),
						'<code>{link}</code>',
						'<code>{minutes}</code>'
					);
					?>
				</p>
			</td>
		</tr>
	</table>
	<?php
}


/**
 * The public name: whether it is offered, under which rules, and whether it
 * also works for requesting the sign-in link.
 */
function diluxone_users_screen_login_handle(): void {
	diluxone_users_intro( __( 'The email is the identity and nobody chooses it. This is the short name people see, the one that goes in the address of their profile.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Offer it', 'diluxone-users' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="diluxone_users_handle_enabled" value="1" <?php checked( diluxone_users_option( 'diluxone_users_handle_enabled' ), 1 ); ?>>
					<?php esc_html_e( 'Let people choose their public name', 'diluxone-users' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Turned off, the name comes from what they wrote as their first and last name, and the profile address is made from that.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Length', 'diluxone-users' ); ?></th>
			<td>
				<input type="number" name="diluxone_users_handle_min" class="small-text" min="1" value="<?php echo esc_attr( (string) diluxone_users_option( 'diluxone_users_handle_min' ) ); ?>">
				<?php esc_html_e( 'to', 'diluxone-users' ); ?>
				<input type="number" name="diluxone_users_handle_max" class="small-text" min="1" value="<?php echo esc_attr( (string) diluxone_users_option( 'diluxone_users_handle_max' ) ); ?>">
				<?php esc_html_e( 'characters', 'diluxone-users' ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Letters', 'diluxone-users' ); ?></th>
			<td>
				<?php
				$sets = array(
					'strict'  => __( 'Plain: a–z, digits, dot, dash and underscore', 'diluxone-users' ),
					'unicode' => __( 'Also accents and ñ', 'diluxone-users' ),
				);

				foreach ( $sets as $key => $label ) :
					?>
					<label class="diluxone-users-roles__item">
						<input type="radio" name="diluxone_users_handle_charset" value="<?php echo esc_attr( $key ); ?>" <?php checked( diluxone_users_option( 'diluxone_users_handle_charset' ), $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Whatever is typed is turned into the same thing WordPress would put in a URL, so what passes here is exactly what ends up in the address. Anything that does not fit —punctuation, symbols, emoji— is dropped, and the person sees what it turned into before saving.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Spaces', 'diluxone-users' ); ?></th>
			<td>
				<?php
				$spaces = array(
					'dash'   => __( 'Turn them into dashes: “Ana Gómez” becomes ana-gomez', 'diluxone-users' ),
					'reject' => __( 'Refuse them and say so', 'diluxone-users' ),
				);

				foreach ( $spaces as $key => $label ) :
					?>
					<label class="diluxone-users-roles__item">
						<input type="radio" name="diluxone_users_handle_spaces" value="<?php echo esc_attr( $key ); ?>" <?php checked( diluxone_users_option( 'diluxone_users_handle_spaces' ), $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'A web address cannot have spaces, so one of the two has to happen. The first is what almost everybody expects; the second is for a site that would rather nobody ends up with a name they did not type.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Taken names', 'diluxone-users' ); ?></th>
			<td>
				<p class="description">
					<?php
					printf(
						/* translators: 1: user_nicename, 2: user_login */
						esc_html__( 'Always checked, and against two things: the public names already in use (%1$s) and the usernames that came with the accounts (%2$s). The second one matters because a site that lets people sign in by public name would otherwise have two people answering to the same text, and the link would go to the wrong account.', 'diluxone-users' ),
						'<code>user_nicename</code>',
						'<code>user_login</code>'
					);
					?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'How often it can change', 'diluxone-users' ); ?></th>
			<td>
				<input type="number" name="diluxone_users_handle_cooldown" class="small-text" min="0" value="<?php echo esc_attr( (string) diluxone_users_option( 'diluxone_users_handle_cooldown' ) ); ?>">
				<?php esc_html_e( 'days between one change and the next', 'diluxone-users' ); ?>
				<p class="description"><?php esc_html_e( '0 means whenever they like. A name that changes every day does not identify anybody, and the old address stops working each time.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="diluxone_users_handle_reserved"><?php esc_html_e( 'Names nobody can take', 'diluxone-users' ); ?></label></th>
			<td>
				<textarea id="diluxone_users_handle_reserved" name="diluxone_users_handle_reserved" rows="3" class="large-text code"><?php echo esc_textarea( (string) diluxone_users_option( 'diluxone_users_handle_reserved' ) ); ?></textarea>
				<p class="description"><?php esc_html_e( 'One per line, or separated by commas. The obvious ones —admin, support, api, login— are already blocked; these are yours to add.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
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

/**
 * What each person can do with their own data, without asking anyone.
 *
 * Both come turned on because that is what is right. Turning them off is not
 * hiding the obligation: it is saying those requests are handled by hand, and
 * in that case the whole section disappears from the front end instead of
 * offering buttons that lead nowhere.
 */
function diluxone_users_screen_login_privacy(): void {
	diluxone_users_intro( __( 'What each person can do with their own data from the site, without asking anybody.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Their data', 'diluxone-users' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="diluxone_users_privacy_export" value="1" <?php checked( diluxone_users_option( 'diluxone_users_privacy_export' ), 1 ); ?>>
					<?php esc_html_e( 'They can ask for a copy of everything and download it', 'diluxone-users' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'It is the export WordPress already knows how to make: it asks for confirmation by email and leaves the file ready.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Their account', 'diluxone-users' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="diluxone_users_privacy_delete" value="1" <?php checked( diluxone_users_option( 'diluxone_users_privacy_delete' ), 1 ); ?>>
					<?php esc_html_e( 'They can ask for their account to be deleted', 'diluxone-users' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Also confirmed by email, and never for an account that administers the site: it would leave the site with nobody in charge.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
	</table>

	<p class="description"><?php esc_html_e( 'With both off, the “Your data” section stops showing: an empty section is worse than no section.', 'diluxone-users' ); ?></p>
	<?php
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

/**
 * The sign-in form as the site serves it.
 *
 * The shortcode answers with nothing to somebody who is already signed in,
 * and in the dashboard everybody is, so the template is rendered the way the
 * shortcode renders it. It is still the template — the theme's copy of it if
 * there is one — and not a drawing of the template, which is the only way a
 * preview is worth looking at.
 */
function diluxone_users_login_preview(): void {
	?>
	<div class="diluxone-users-preview" data-diluxone-users-preview-box>
		<?php
		// Inert and outside any settings form: the sign-in form is a form.
		?>
		<div class="diluxone-users-preview__frame" inert>
			<div data-diluxone-users-preview-skin>
				<?php
				echo diluxone_users_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template escapes its own output.
					'login.php',
					array(
						'state'     => diluxone_users_state(),
						'email'     => '',
						'providers' => diluxone_users_sso_for_login(),
						'minutes'   => diluxone_users_login_expiry(),
						'title'     => false,
					)
				);
				?>
			</div>
		</div>
	</div>
	<?php
}

/** What somebody signing in actually sees. */
function diluxone_users_screen_login_look(): void {
	$page = (int) diluxone_users_option( 'diluxone_users_login_page' );

	diluxone_users_intro( __( 'The form as the site serves it, with everything chosen on the other tabs. It is the real template — your theme’s copy of it, if it has one — and not a drawing of it.', 'diluxone-users' ) );

	diluxone_users_login_preview();

	if ( 0 === $page ) {
		diluxone_users_intro( __( 'There is no sign-in page chosen yet, so this form is not anywhere on the site: people still land on wp-login.php.', 'diluxone-users' ) );
	}
	?>
	<p class="diluxone-users-panel__actions">
		<a class="button" href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-login' ) ); ?>"><?php echo esc_html( diluxone_users_screens()['diluxone-users-login'] ); ?></a>
		<a class="button" href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-social' ) ); ?>"><?php esc_html_e( 'Social login', 'diluxone-users' ); ?></a>
		<a class="button" href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-account', array( 'tab' => 'appearance' ) ) ); ?>"><?php esc_html_e( 'Colours and corners', 'diluxone-users' ); ?></a>
		<?php if ( $page > 0 ) : ?>
			<a class="button" href="<?php echo esc_url( (string) get_permalink( $page ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open the page', 'diluxone-users' ); ?></a>
		<?php endif; ?>
	</p>
	<?php
}

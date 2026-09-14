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

/** The sign-in screen. */
function diluxone_users_screen_login(): void {
	diluxone_users_screen_panels( 'diluxone-users-login', diluxone_users_screens()['diluxone-users-login'] );
}

/**
 * Its tabs.
 *
 * Two-step verification and passkeys used to be here. They are features of
 * their own and they moved to Security, each registering its tab from its own
 * file — which is what this screen no longer needs to know about.
 */
function diluxone_users_login_panels(): void {
	diluxone_users_register_panel(
		'diluxone-users-login',
		'doors',
		array(
			'label'    => __( 'Overview', 'diluxone-users' ),
			'position' => 10,
			'render'   => 'diluxone_users_screen_login_doors',
			// It reads the settings the other tabs write; there is nothing to
			// save and so no form and no button.
			'form'     => false,
		)
	);

	diluxone_users_register_panel(
		'diluxone-users-login',
		'link',
		array(
			'label'    => __( 'Getting in', 'diluxone-users' ),
			'position' => 20,
			'render'   => 'diluxone_users_screen_login_link',
			'save'     => 'diluxone_users_login_link_save',
		)
	);

	diluxone_users_register_panel(
		'diluxone-users-login',
		'handle',
		array(
			'label'    => __( 'Public name', 'diluxone-users' ),
			'position' => 40,
			'render'   => 'diluxone_users_screen_login_handle',
			'save'     => 'diluxone_users_login_handle_save',
		)
	);
}
add_action( 'diluxone_users_register_panels', 'diluxone_users_login_panels' );

/** The doors, the page, and what the box accepts. */
function diluxone_users_login_link_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
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
			'diluxone_users_lost_password'  => 'site' === sanitize_key( wp_unslash( $_POST['diluxone_users_lost_password'] ?? 'wp' ) ) ? 'site' : 'wp',
			'diluxone_users_wp_screens'     => in_array( $_POST['diluxone_users_wp_screens'] ?? '', array( 'auto', 'mine', 'wp' ), true )
				? sanitize_key( wp_unslash( $_POST['diluxone_users_wp_screens'] ) )
				: 'auto',
		)
	);
	// phpcs:enable
}

/** The e-mail that carries the link. */
function diluxone_users_login_email_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	diluxone_users_save_options(
		array(
			'diluxone_users_login_subject' => sanitize_text_field( wp_unslash( $_POST['diluxone_users_login_subject'] ?? '' ) ),
			'diluxone_users_login_body'    => sanitize_textarea_field( wp_unslash( $_POST['diluxone_users_login_body'] ?? '' ) ),
		)
	);
	// phpcs:enable
}

/** The public name and its rules. */
function diluxone_users_login_handle_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
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
		'state'  => diluxone_users_has_passkeys() ? 'open' : 'closed',
		'detail' => diluxone_users_has_passkeys()
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

/** Every door into the site, with the state each one is in. Nothing is edited here. */
function diluxone_users_screen_login_doors(): void {
	diluxone_users_intro( __( 'Every way into this site and the state each one is in, read from the same settings the other tabs write. The choices are spread over several tabs; the consequence is not. Nothing is edited here.', 'diluxone-users' ) );

	diluxone_users_doors_table();
}

/** The way in: which page holds the form, and what it accepts. */
function diluxone_users_screen_login_link(): void {
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
			<th scope="row"><?php esc_html_e( 'WordPress’s own screens', 'diluxone-users' ); ?></th>
			<td>
				<?php
				$screens = array(
					'auto' => __( 'Take them over only when “only a link” is the way in', 'diluxone-users' ),
					'mine' => __( 'Always: this site’s pages are the doors', 'diluxone-users' ),
					'wp'   => __( 'Leave them alone', 'diluxone-users' ),
				);

				$chosen = diluxone_users_wp_screens();

				foreach ( $screens as $key => $label ) :
					?>
					<label class="diluxone-users-roles__item">
						<input type="radio" name="diluxone_users_wp_screens" value="<?php echo esc_attr( $key ); ?>" <?php checked( $chosen, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>

				<p class="description"><?php esc_html_e( 'Taken over, wp-login.php sends people to the page chosen above, and “register” sends them to the registration page when there is one. Whoever can administer the site still has the emergency way in.', 'diluxone-users' ); ?></p>

				<?php if ( 'wp' === $chosen ) : ?>
					<p class="description">
						<strong><?php esc_html_e( 'Left alone, this site has two sign-in screens: the one you designed and the one WordPress brings.', 'diluxone-users' ); ?></strong>
						<?php esc_html_e( 'Anybody who lands on wp-login.php — from an old bookmark, a link in an email, a plugin that sends them there — sees the other one. That is a choice worth making on purpose.', 'diluxone-users' ); ?>
						<a href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-design', array( 'tab' => 'wp' ) ) ); ?>"><?php esc_html_e( 'At least put your face on it', 'diluxone-users' ); ?></a>
					</p>
				<?php endif; ?>

				<?php if ( 0 === (int) diluxone_users_option( 'diluxone_users_login_page' ) && 'wp' !== $chosen ) : ?>
					<p class="description"><strong><?php esc_html_e( 'There is no sign-in page to send anybody to, so nothing is taken over until there is one — which is the only thing keeping this site reachable.', 'diluxone-users' ); ?></strong></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( '“I forgot my password”', 'diluxone-users' ); ?></th>
			<td>
				<?php
				$lost = array(
					'wp'   => __( 'WordPress’s reset screen', 'diluxone-users' ),
					'site' => __( 'The sign-in page — the e-mail link is the way back in', 'diluxone-users' ),
				);

				foreach ( $lost as $key => $label ) :
					?>
					<label class="diluxone-users-roles__item">
						<input type="radio" name="diluxone_users_lost_password" value="<?php echo esc_attr( $key ); ?>" <?php checked( diluxone_users_option( 'diluxone_users_lost_password' ), $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'On a site where a link signs people in, the reset screen asks for the same address the sign-in form asks for, and sends a second e-mail to do what the first one already does.', 'diluxone-users' ); ?></p>
				<?php if ( ! diluxone_users_login_has_link() ) : ?>
					<p class="description"><strong><?php esc_html_e( 'There is no e-mail link on this site, so the second answer does nothing: WordPress’s reset is the only way back in and it stays.', 'diluxone-users' ); ?></strong></p>
				<?php elseif ( 0 === (int) diluxone_users_option( 'diluxone_users_login_page' ) ) : ?>
					<p class="description"><strong><?php esc_html_e( 'No sign-in page is chosen yet, so there is nowhere to point it: WordPress’s reset stays until there is one.', 'diluxone-users' ); ?></strong></p>
				<?php endif; ?>
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
	// The same frame and the same template the front end uses, in the same
	// order: a preview that skips the frame previews a page that does not
	// exist.
	diluxone_users_login_frame_open();

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

	diluxone_users_login_frame_close();
}

/**
 * One field of the wording tab.
 *
 * The placeholder is what the plugin would say, so the box is never a blank
 * asking to be guessed at: it shows the sentence it is about to replace, and
 * emptying the box brings that sentence back.
 */
function diluxone_users_words_field( string $key, string $label, string $fallback, string $help = '' ): void {
	?>
	<tr>
		<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
		<td>
			<input type="text" class="large-text" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
				value="<?php echo esc_attr( (string) diluxone_users_option( $key ) ); ?>"
				placeholder="<?php echo esc_attr( $fallback ); ?>">
			<?php if ( '' !== $help ) : ?>
				<p class="description"><?php echo esc_html( $help ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

/** What the sign-in screen says, in the site's own words. */
function diluxone_users_screen_login_words(): void {
	diluxone_users_intro( __( 'The sentences on the sign-in screen. Leave a box empty and the plugin says its own, which is what the grey text in each box shows — so a site that changes nothing still reads correctly, and changing one is never a step somebody has to remember.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
		<?php
		diluxone_users_words_field(
			'diluxone_users_login_title',
			__( 'The heading', 'diluxone-users' ),
			__( 'Sign in', 'diluxone-users' ),
			__( 'Writing one shows it even when the shortcode was not asked for a heading: naming this screen is meant to be read.', 'diluxone-users' )
		);

		diluxone_users_words_field(
			'diluxone_users_login_intro',
			__( 'The line under it', 'diluxone-users' ),
			'',
			__( 'Nothing by default. This is where a site says something like “if it is your first time, the account is created in the same step”.', 'diluxone-users' )
		);
		?>
		<tr>
			<th scope="row"><label for="diluxone_users_login_legal"><?php esc_html_e( 'The terms line', 'diluxone-users' ); ?></label></th>
			<td>
				<textarea class="large-text code" rows="3" id="diluxone_users_login_legal" name="diluxone_users_login_legal"><?php echo esc_textarea( (string) diluxone_users_option( 'diluxone_users_login_legal' ) ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Goes under the form, small. On a site where signing in also creates the account, this is the only place the rules can be stated — there is no separate registration form to put them on.', 'diluxone-users' ); ?></p>
				<p class="description">
					<?php
					printf(
						/* translators: %s: an example of a link, literal HTML */
						esc_html__( 'Links are allowed, and they are the point: %s', 'diluxone-users' ),
						'<code>&lt;a href="/terms/"&gt;' . esc_html__( 'terms', 'diluxone-users' ) . '&lt;/a&gt;</code>'
					);
					?>
				</p>
			</td>
		</tr>
		<?php
		diluxone_users_words_field(
			'diluxone_users_sent_title',
			__( 'After the link is sent', 'diluxone-users' ),
			__( 'Check your email', 'diluxone-users' ),
			__( 'The screen somebody lands on once the e-mail is on its way.', 'diluxone-users' )
		);

		diluxone_users_words_field(
			'diluxone_users_sent_note',
			__( 'And the note under it', 'diluxone-users' ),
			__( 'Did not arrive? Check your spam or promotions folder.', 'diluxone-users' )
		);
		?>
	</table>
	<?php
}

/**
 * The shapes the sign-in page comes in.
 *
 * Only the first leaves the page alone, and it is the default: a site that
 * designed its own page does not want a plugin taking it over. The other
 * three are for the sites that would otherwise install a second plugin to get
 * a sign-in screen that does not look like a form dropped into a blog post.
 *
 * @return array<string, array{label: string, help: string, picture: bool}>
 */
function diluxone_users_login_templates(): array {
	$templates = array(
		'plain'    => array(
			'label'   => __( 'In the page', 'diluxone-users' ),
			'help'    => __( 'The form where the theme put it, with nothing around it. For a site that already designed this page.', 'diluxone-users' ),
			'picture' => false,
		),
		'card'     => array(
			'label'   => __( 'A centred card', 'diluxone-users' ),
			'help'    => __( 'The form in a box in the middle of the page. The safe answer when there is no design for this screen.', 'diluxone-users' ),
			'picture' => false,
		),
		'split'    => array(
			'label'   => __( 'Split with a picture', 'diluxone-users' ),
			'help'    => __( 'Half the window is a picture and the other half is the form. On a phone the picture goes and the form stays.', 'diluxone-users' ),
			'picture' => true,
		),
		'backdrop' => array(
			'label'   => __( 'On a full background', 'diluxone-users' ),
			'help'    => __( 'The picture behind everything and the form on top of it, darkened enough to stay readable.', 'diluxone-users' ),
			'picture' => true,
		),
	);

	/**
	 * Filters the shapes the sign-in page comes in.
	 *
	 * A shape added here needs its own CSS: the plugin prints the id as a
	 * class on the frame — `diluxone-users-login-frame--<id>` — and stops
	 * there. Set `picture` to true and it gets the picture field.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array{label: string, help: string, picture: bool}> $templates
	 */
	return (array) apply_filters( 'diluxone_users_login_templates', $templates );
}

/** The shape of the sign-in page, and what it is made of. */
function diluxone_users_screen_login_shape(): void {
	$template = diluxone_users_login_template();
	$shapes   = diluxone_users_login_templates();
	?>
	<h2><?php esc_html_e( 'The shape of the page', 'diluxone-users' ); ?></h2>
	<?php
	diluxone_users_intro( __( 'What surrounds the form. Three of the four take over the page, edge to edge; the first leaves it exactly where your theme put it.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Template', 'diluxone-users' ); ?></th>
			<td>
				<div class="diluxone-users-templates">
					<?php foreach ( $shapes as $id => $shape ) : ?>
						<label class="diluxone-users-templates__one <?php echo $id === $template ? 'is-chosen' : ''; ?>">
							<input type="radio" name="diluxone_users_login_template" value="<?php echo esc_attr( $id ); ?>" <?php checked( $id, $template ); ?>>
							<span class="diluxone-users-templates__art diluxone-users-templates__art--login-<?php echo esc_attr( $id ); ?>" aria-hidden="true"></span>
							<span class="diluxone-users-templates__name"><?php echo esc_html( $shape['label'] ); ?></span>
							<span class="diluxone-users-templates__help"><?php echo esc_html( $shape['help'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'The picture', 'diluxone-users' ); ?></th>
			<td>
				<?php
				diluxone_users_image_field(
					'diluxone_users_login_image',
					__( 'Used by the two templates that have one. It is decoration, so it carries no alternative text: nothing that has to be read should live in it.', 'diluxone-users' )
				);
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Which side', 'diluxone-users' ); ?></th>
			<td>
				<?php
				$sides = array(
					'left'  => __( 'Picture on the left, form on the right', 'diluxone-users' ),
					'right' => __( 'Picture on the right, form on the left', 'diluxone-users' ),
				);

				foreach ( $sides as $key => $label ) :
					?>
					<label class="diluxone-users-roles__item">
						<input type="radio" name="diluxone_users_login_side" value="<?php echo esc_attr( $key ); ?>" <?php checked( diluxone_users_option( 'diluxone_users_login_side' ), $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Only the split template uses this.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Your mark', 'diluxone-users' ); ?></th>
			<td>
				<?php
				diluxone_users_image_field(
					'diluxone_users_login_logo',
					__( 'Above the form, in every template. Somebody who arrived from an e-mail link should be able to tell whose site this is before typing their address into it.', 'diluxone-users' )
				);
				?>
			</td>
		</tr>
	</table>
	<?php
}

/** WordPress's own sign-in screen, which somebody still sees. */
function diluxone_users_screen_login_wp(): void {
	?>
	<h2><?php esc_html_e( 'WordPress’s own screen', 'diluxone-users' ); ?></h2>
	<?php
	diluxone_users_intro( __( 'Even with everybody sent to the page above, this one is still shown to somebody: an administrator coming in through the emergency door, and anybody finishing a password reset — WordPress builds that link and it goes here and nowhere else. Left alone it is a grey box with the WordPress logo on it.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Your face on it', 'diluxone-users' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="diluxone_users_wp_login_brand" value="1" <?php checked( diluxone_users_option( 'diluxone_users_wp_login_brand' ), 1 ); ?>>
					<?php esc_html_e( 'Put the site’s name, mark and colour on wp-login.php', 'diluxone-users' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Off by default: a plugin that repaints a screen nobody asked it about is a plugin that gets blamed for the repaint.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'The mark there', 'diluxone-users' ); ?></th>
			<td>
				<?php
				diluxone_users_image_field(
					'diluxone_users_wp_login_logo',
					__( 'Replaces the WordPress logo above the box. Without one, the site’s name is written there instead — which is still better than somebody else’s logo.', 'diluxone-users' )
				);
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="diluxone_users_wp_login_bg"><?php esc_html_e( 'The colour there', 'diluxone-users' ); ?></label></th>
			<td>
				<input type="color" id="diluxone_users_wp_login_bg" name="diluxone_users_wp_login_bg" value="<?php echo esc_attr( diluxone_users_wp_login_bg() ); ?>">
				<p class="description"><?php esc_html_e( 'Left as the accent colour it follows the accent, instead of becoming a second colour that drifts from the first.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

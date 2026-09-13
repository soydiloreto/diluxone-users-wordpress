<?php
/**
 * Who gets an account on this site.
 *
 * It used to be the first tab of the sign-in screen, and being a tab of it
 * said the two were steps of one thing. They are not: a site can open
 * registration without changing anything about how people sign in, and close
 * it without changing that either. Two questions, two screens.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/** The registration screen, with its tabs. */
function diluxone_users_screen_register(): void {
	$tabs = array(
		'who'  => __( 'Who gets an account', 'diluxone-users' ),
		'look' => __( 'How it looks', 'diluxone-users' ),
	);

	$current = diluxone_users_tab( $tabs );

	if ( isset( $_POST['diluxone_users_options_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['diluxone_users_options_nonce'] ) ), 'diluxone_users_options' ) ) {
		diluxone_users_screen_register_save();
		diluxone_users_notice( __( 'Settings saved.', 'diluxone-users' ) );
	}

	diluxone_users_screen_open( diluxone_users_screens()['diluxone-users-register'], 'diluxone-users-register', $tabs, $current );

	if ( 'look' === $current ) {
		diluxone_users_screen_register_look();
		diluxone_users_screen_close();

		return;
	}

	echo '<form method="post">';
	wp_nonce_field( 'diluxone_users_options', 'diluxone_users_options_nonce' );

	diluxone_users_screen_register_who();

	submit_button();
	echo '</form>';

	diluxone_users_screen_close();
}

/** Saves what the screen submits. */
function diluxone_users_screen_register_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the caller verifies it.
	diluxone_users_save_options(
		array(
			'diluxone_users_login_register'  => isset( $_POST['diluxone_users_login_register'] ) ? 1 : 0,
			'diluxone_users_login_role'      => sanitize_key( wp_unslash( $_POST['diluxone_users_login_role'] ?? 'subscriber' ) ),
			'diluxone_users_wp_registration' => sanitize_key( wp_unslash( $_POST['diluxone_users_wp_registration'] ?? 'site' ) ),
			// The same option the social screen writes: it is one decision and
			// it is shown wherever it is thought about, not copied into a
			// second one that would drift from the first.
			'diluxone_users_sso_register'    => isset( $_POST['diluxone_users_sso_register'] ) ? 1 : 0,
		)
	);
	// phpcs:enable
}

/**
 * The line under a social switch: whether there is anything behind it.
 *
 * A tick box that says "allow signing up with a social network" on a site
 * with no provider set up is a switch wired to nothing. It still saves — the
 * day a provider is turned on, the answer is already there — but it says so.
 */
function diluxone_users_social_state(): void {
	$ready = diluxone_users_sso_available();

	if ( array() !== $ready ) {
		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: list of providers, comma separated */
					__( 'Working right now: %s.', 'diluxone-users' ),
					implode( ', ', wp_list_pluck( $ready, 'name' ) )
				)
			)
		);

		return;
	}

	printf(
		'<p class="description">%1$s <a href="%2$s">%3$s</a></p>',
		esc_html__( 'There is no provider enabled yet, so this changes nothing until there is. What is chosen here is kept for the day there is one.', 'diluxone-users' ),
		esc_url( diluxone_users_admin_url( 'diluxone-users-social' ) ),
		esc_html__( 'Set up a provider', 'diluxone-users' )
	);
}

/** Who gets an account, and what that account is. */
function diluxone_users_screen_register_who(): void {
	diluxone_users_intro( __( 'Who ends up with an account on this site, and what that account is when it is created. How they get into it afterwards is the Sign in screen.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'With their email', 'diluxone-users' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="diluxone_users_login_register" value="1" <?php checked( diluxone_users_option( 'diluxone_users_login_register' ), 1 ); ?>>
					<?php esc_html_e( 'If the email does not exist, register it in the same step', 'diluxone-users' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'With this, signing in and signing up are the same thing and no separate registration form is needed.', 'diluxone-users' ); ?></p>
				<p class="description"><?php esc_html_e( 'Turned off, only people who already have an account can get in — and somebody has to create the accounts from Users.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'With a social account', 'diluxone-users' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="diluxone_users_sso_register" value="1" <?php checked( diluxone_users_option( 'diluxone_users_sso_register' ), 1 ); ?>>
					<?php esc_html_e( 'Somebody arriving from a social network with no account here gets one', 'diluxone-users' ); ?>
				</label>
				<?php diluxone_users_social_state(); ?>
				<p class="description"><?php esc_html_e( 'Turned off, a social account only signs in people who are already here — anybody else is turned away at the door.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="diluxone_users_login_role"><?php esc_html_e( 'Role for new accounts', 'diluxone-users' ); ?></label></th>
			<td>
				<select name="diluxone_users_login_role" id="diluxone_users_login_role">
					<?php wp_dropdown_roles( (string) diluxone_users_option( 'diluxone_users_login_role' ) ); ?>
				</select>
				<p class="description"><?php esc_html_e( 'The same one for everybody, whether they come in by email or by a social network: a role per provider is a quiet way of handing out privileges.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'The WordPress registration form', 'diluxone-users' ); ?></th>
			<td>
				<?php
				$registry = array(
					'site' => __( 'Whatever Settings → General says', 'diluxone-users' ),
					'on'   => __( 'Open, whatever that setting says', 'diluxone-users' ),
					'off'  => __( 'Closed, whatever that setting says', 'diluxone-users' ),
				);

				foreach ( $registry as $key => $label ) :
					?>
					<label class="diluxone-users-roles__item">
						<input type="radio" name="diluxone_users_wp_registration" value="<?php echo esc_attr( $key ); ?>" <?php checked( diluxone_users_option( 'diluxone_users_wp_registration' ), $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'This is wp-login.php?action=register and wp-signup.php, the ones WordPress brings. They are decided here because this is where the accounts are administered; leaving that in another screen is how a site ends up with a registration form nobody remembers is open.', 'diluxone-users' ); ?></p>
				<p class="description"><?php esc_html_e( 'With “only a link” as the way in, it stays closed no matter what: it would hand out accounts with a password through a door the site closed.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'How the account ends up', 'diluxone-users' ); ?></th>
			<td>
				<p class="description"><?php esc_html_e( 'The email is the username and it never changes —WordPress does not allow it. There is no password at all, not even one nobody knows. The name shown comes from what the person writes, and the fields they are asked for are the ones on the User fields screen.', 'diluxone-users' ); ?></p>
				<p class="description">
					<a href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-fields' ) ); ?>"><?php esc_html_e( 'What they are asked for', 'diluxone-users' ); ?></a>
				</p>
			</td>
		</tr>
	</table>
	<?php
}

/** What somebody signing up actually sees. */
function diluxone_users_screen_register_look(): void {
	diluxone_users_intro( __( 'With signing in and signing up being the same step, this is the form a new person meets: the same one on the Sign in screen, which is the point — nobody is sent to a different door for being new.', 'diluxone-users' ) );

	diluxone_users_login_preview();

	diluxone_users_intro( __( 'The fields they are asked to fill in afterwards are the required ones on the User fields screen, and the buttons are the ones on Social login.', 'diluxone-users' ) );
	?>
	<p class="diluxone-users-panel__actions">
		<a class="button" href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-login' ) ); ?>"><?php echo esc_html( diluxone_users_screens()['diluxone-users-login'] ); ?></a>
		<a class="button" href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-fields' ) ); ?>"><?php esc_html_e( 'User fields', 'diluxone-users' ); ?></a>
		<a class="button" href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-social' ) ); ?>"><?php esc_html_e( 'Social login', 'diluxone-users' ); ?></a>
	</p>
	<?php
}

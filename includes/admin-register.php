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

/** The registration screen. */
function diluxone_users_screen_register(): void {
	diluxone_users_screen_panels( 'diluxone-users-register', diluxone_users_screens()['diluxone-users-register'] );
}

/** Its tabs. */
function diluxone_users_register_panels(): void {
	diluxone_users_register_panel(
		'diluxone-users-register',
		'who',
		array(
			'label'    => __( 'Who gets an account', 'diluxone-users' ),
			'position' => 10,
			'render'   => 'diluxone_users_screen_register_who',
			'save'     => 'diluxone_users_screen_register_save',
		)
	);

	diluxone_users_register_panel(
		'diluxone-users-register',
		'form',
		array(
			'label'    => __( 'The form', 'diluxone-users' ),
			'position' => 20,
			'render'   => 'diluxone_users_screen_register_form',
			'save'     => 'diluxone_users_screen_register_form_save',
		)
	);
}
add_action( 'diluxone_users_register_panels', 'diluxone_users_register_panels' );

/** Saves who gets an account. */
function diluxone_users_screen_register_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	$mode = sanitize_key( wp_unslash( $_POST['diluxone_users_register_mode'] ?? '' ) );
	$mode = in_array( $mode, array( 'login', 'form', 'closed' ), true ) ? $mode : 'login';

	diluxone_users_save_options(
		array(
			'diluxone_users_register_mode'   => $mode,
			// Kept in step with the mode. Everything that reads the old
			// checkbox — the multisite seeding, the overview, the doors
			// table — keeps getting a straight answer without knowing that
			// the question grew a third answer.
			'diluxone_users_login_register'  => 'login' === $mode ? 1 : 0,
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

/** The page the form lives on, and what it says. */
function diluxone_users_screen_register_form(): void {
	$mode = diluxone_users_register_mode();

	diluxone_users_intro( __( 'A form of its own, for a site that asks for more than an address before letting somebody in — or that wants a moment between “I want an account” and “here is your link”. Put the [diluxone_users_register] shortcode on the page you pick below.', 'diluxone-users' ) );

	if ( 'form' !== $mode ) {
		diluxone_users_intro( __( 'This site does not use it: on the previous tab, accounts are made another way. What is set here waits for the day that changes.', 'diluxone-users' ) );
	}
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="diluxone_users_register_page"><?php esc_html_e( 'The registration page', 'diluxone-users' ); ?></label></th>
			<td>
				<?php
				/** @var array<string, mixed> $diluxone_users_dropdown */
				$diluxone_users_dropdown = array(
					'name'              => 'diluxone_users_register_page',
					'id'                => 'diluxone_users_register_page',
					'selected'          => (int) diluxone_users_option( 'diluxone_users_register_page' ),
					'show_option_none'  => __( '— none —', 'diluxone-users' ),
					'option_none_value' => 0,
				);

					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_dropdown_pages() escapes its own and prints it.
				wp_dropdown_pages( $diluxone_users_dropdown );
				?>
				<?php if ( 'form' === $mode && '' === diluxone_users_register_url() ) : ?>
					<p class="description"><strong><?php esc_html_e( 'The form is how this site registers people and there is no page holding it, so right now nobody can register at all.', 'diluxone-users' ); ?></strong></p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'What it asks for', 'diluxone-users' ); ?></th>
			<td>
				<?php
				$diluxone_users_asked = diluxone_users_register_fields();
				?>

				<?php if ( array() === $diluxone_users_asked ) : ?>
					<p class="description"><?php esc_html_e( 'Only the email address: no field is marked as required. Mark one on User fields and it appears here.', 'diluxone-users' ); ?></p>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'The address, plus the fields marked as required:', 'diluxone-users' ); ?></p>
					<p>
						<?php
						echo esc_html( implode( ' · ', wp_list_pluck( $diluxone_users_asked, 'label' ) ) );
						?>
					</p>
				<?php endif; ?>

				<p class="description">
					<?php esc_html_e( 'Only the required ones, on purpose: a registration form that asks for everything is a registration form nobody finishes. The rest waits in their account.', 'diluxone-users' ); ?>
					<a href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-fields' ) ); ?>"><?php esc_html_e( 'User fields', 'diluxone-users' ); ?></a>
				</p>
			</td>
		</tr>
	</table>
	<?php
}

/** Saves the form's page and its words. */
function diluxone_users_screen_register_form_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	diluxone_users_save_options(
		array(
			'diluxone_users_register_page' => absint( wp_unslash( $_POST['diluxone_users_register_page'] ?? 0 ) ),
		)
	);
	// phpcs:enable

	// The page changed, and with it whether anybody can register at all.
	delete_option( 'diluxone_users_rewrite_version' );
}

/** The form as the site serves it. */
function diluxone_users_register_preview(): void {
	// The same frame the sign-in page wears: it is the same page with other
	// fields in it, and previewing it bare would preview something else.
	diluxone_users_login_frame_open();

	echo diluxone_users_render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template escapes its own output.
		'register.php',
		array(
			'state'     => '',
			'email'     => '',
			'fields'    => diluxone_users_register_fields(),
			'open'      => 'closed' !== diluxone_users_register_mode(),
			'providers' => diluxone_users_sso_for_login(),
		)
	);

	diluxone_users_login_frame_close();
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
			<th scope="row"><?php esc_html_e( 'How somebody gets an account', 'diluxone-users' ); ?></th>
			<td>
				<?php
				/*
				 * One question with three answers, instead of a checkbox that
				 * only had two. The third — a form of its own — is what a site
				 * needs when it asks for more than an address, or when
				 * somebody is looked at before being let in.
				 */
				$modes = array(
					'login'  => __( 'Signing in creates it: one door for everybody', 'diluxone-users' ),
					'form'   => __( 'A registration form of its own, on its own page', 'diluxone-users' ),
					'closed' => __( 'Nobody registers themselves: the accounts are made from Users', 'diluxone-users' ),
				);

				$mode = diluxone_users_register_mode();

				foreach ( $modes as $key => $label ) :
					?>
					<label class="diluxone-users-roles__item">
						<input type="radio" name="diluxone_users_register_mode" value="<?php echo esc_attr( $key ); ?>" <?php checked( $mode, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>

				<p class="description"><?php esc_html_e( 'The first is the plugin at its simplest: somebody types their address, gets a link, and the account appears if it was not there. Nothing to fill in, nothing to confirm.', 'diluxone-users' ); ?></p>
				<p class="description"><?php esc_html_e( 'The second asks for the required fields before creating anything, and is where an add-on would hold an account for approval. The address is still the identity and there is still no password: what arrives is the same link.', 'diluxone-users' ); ?></p>

				<?php if ( 'form' === $mode && '' === diluxone_users_register_url() ) : ?>
					<p class="description">
						<strong><?php esc_html_e( 'There is no page holding the form yet, so nobody can register.', 'diluxone-users' ); ?></strong>
						<a href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-register', array( 'tab' => 'form' ) ) ); ?>"><?php esc_html_e( 'Choose one', 'diluxone-users' ); ?></a>
					</p>
				<?php endif; ?>
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

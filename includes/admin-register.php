<?php
/**
 * Who gets an account, and how. A tab of the Access screen.
 *
 * It was a screen of its own for a while, on the argument that opening
 * registration changes nothing about signing in. True and beside the point:
 * they are asked at the same moment and they share half their settings. So
 * it is the third tab of the door, after where people sign in and with what.
 *
 * The model underneath is a list of doors into an account, each one a tick
 * box and none of them excluding another: the e-mail link can create the
 * account, a social account can, the site's own form can, WordPress's own
 * form can. It used to be one radio with three exclusive answers, and the
 * first question anybody asked was "and if I want two of them?".
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/** Its tab, on the Access screen. */
function diluxone_users_register_panels(): void {
	diluxone_users_register_panel(
		'diluxone-users-login',
		'register',
		array(
			'label'    => __( 'Registration', 'diluxone-users' ),
			'position' => 30,
			'render'   => 'diluxone_users_screen_register',
			'save'     => 'diluxone_users_screen_register_save',
		)
	);
}
add_action( 'diluxone_users_register_panels', 'diluxone_users_register_panels' );

/** Saves the doors, the page and the role. */
function diluxone_users_screen_register_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	diluxone_users_save_options(
		array(
			'diluxone_users_login_register' => isset( $_POST['diluxone_users_login_register'] ) ? 1 : 0,
			'diluxone_users_sso_register'   => isset( $_POST['diluxone_users_sso_register'] ) ? 1 : 0,
			'diluxone_users_register_form'  => isset( $_POST['diluxone_users_register_form'] ) ? 1 : 0,
			'diluxone_users_register_page'  => absint( wp_unslash( $_POST['diluxone_users_register_page'] ?? 0 ) ),
			'diluxone_users_login_role'     => sanitize_key( wp_unslash( $_POST['diluxone_users_login_role'] ?? 'subscriber' ) ),
		)
	);

	/*
	 * WordPress's own form is WordPress's own switch — the same one as
	 * Settings → General → "Anyone can register" — and it is written there,
	 * not copied. While the plugin has it locked (the e-mail link is the only
	 * way in) the box is not posted, and nothing is written: the lock, not
	 * this save, is what keeps it off.
	 */
	if ( ! diluxone_users_wp_registration_locked() ) {
		update_option( 'users_can_register', isset( $_POST['diluxone_users_wp_register'] ) ? 1 : 0 );
	}
	// phpcs:enable

	// The page changed, and with it the /register/ rules.
	delete_option( 'diluxone_users_rewrite_version' );
}

/** Who gets an account, and what that account is. */
function diluxone_users_screen_register(): void {
	$social = diluxone_users_sso_working_names();
	$form   = (bool) diluxone_users_option( 'diluxone_users_register_form' );
	$page   = (int) diluxone_users_option( 'diluxone_users_register_page' );
	$locked = diluxone_users_wp_registration_locked();

	diluxone_users_intro( __( 'Who can create an account on this site, and what that account is once it exists. Every door below is independent: tick the ones this site opens. None ticked, nobody registers themselves.', 'diluxone-users' ) );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Who can create an account', 'diluxone-users' ); ?></th>
			<td>
				<label class="diluxone-users-roles__item">
					<input type="checkbox" name="diluxone_users_login_register" value="1" <?php checked( diluxone_users_option( 'diluxone_users_login_register' ), 1 ); ?>>
					<?php esc_html_e( 'Signing in with the e-mail link creates the account', 'diluxone-users' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'The plugin at its simplest: somebody types their address, gets a link, and the account appears if it was not there. Nothing to fill in.', 'diluxone-users' ); ?></p>

				<label class="diluxone-users-roles__item">
					<input type="checkbox" name="diluxone_users_sso_register" value="1" <?php checked( diluxone_users_option( 'diluxone_users_sso_register' ), 1 ); ?>>
					<?php esc_html_e( 'Signing in with a social account creates the account', 'diluxone-users' ); ?>
					<?php if ( array() === $social ) : ?>
						<span class="description"><?php esc_html_e( '— no provider is working yet, so this does nothing until one is', 'diluxone-users' ); ?></span>
					<?php endif; ?>
				</label>
				<p class="description"><?php esc_html_e( 'Off, a social account only signs in people who already have one here.', 'diluxone-users' ); ?></p>

				<label class="diluxone-users-roles__item">
					<input type="checkbox" name="diluxone_users_register_form" value="1" <?php checked( $form ); ?>>
					<?php esc_html_e( 'The site’s own registration form', 'diluxone-users' ); ?>
					<?php if ( $form && $page <= 0 ) : ?>
						<span class="description"><?php esc_html_e( '— it has no page yet, so nobody can reach it', 'diluxone-users' ); ?></span>
					<?php endif; ?>
				</label>
				<p class="description"><?php esc_html_e( 'For a site that asks for more than an address before letting somebody in. It asks for the fields marked required, then sends the same link.', 'diluxone-users' ); ?></p>

				<label class="diluxone-users-roles__item">
					<input type="checkbox" name="diluxone_users_wp_register" value="1" <?php checked( (bool) get_option( 'users_can_register' ) && ! $locked ); ?> <?php disabled( $locked ); ?>>
					<?php esc_html_e( 'WordPress’s own form (wp-login.php?action=register)', 'diluxone-users' ); ?>
					<?php if ( $locked ) : ?>
						<span class="description"><?php esc_html_e( '— off and locked while the e-mail link is the only way in', 'diluxone-users' ); ?></span>
					<?php endif; ?>
				</label>
				<p class="description"><?php esc_html_e( 'This is the same switch as Settings → General → “Anyone can register”: changing it here or there changes both. It stays off while the e-mail link is the only way in — that form creates accounts with a password, and on this site a password opens nothing.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr class="<?php echo $form ? '' : 'diluxone-users-row--dim'; ?>">
			<th scope="row"><label for="diluxone_users_register_page"><?php esc_html_e( 'The registration page', 'diluxone-users' ); ?></label></th>
			<td>
				<?php if ( ! $form ) : ?>
					<?php diluxone_users_not_now( __( 'The site’s own form is not one of the doors right now. What is chosen here waits for it.', 'diluxone-users' ) ); ?>
				<?php endif; ?>
				<?php
				/** @var array<string, mixed> $diluxone_users_dropdown */
				$diluxone_users_dropdown = array(
					'name'              => 'diluxone_users_register_page',
					'id'                => 'diluxone_users_register_page',
					'selected'          => $page,
					'show_option_none'  => __( '— None —', 'diluxone-users' ),
					'option_none_value' => 0,
				);

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_dropdown_pages() escapes its own and prints it.
				wp_dropdown_pages( $diluxone_users_dropdown );
				?>
				<p class="description"><?php esc_html_e( 'The page with the [diluxone_users_register] shortcode. What the form says is on Design → Registration.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="diluxone_users_login_role"><?php esc_html_e( 'Role of new accounts', 'diluxone-users' ); ?></label></th>
			<td>
				<select name="diluxone_users_login_role" id="diluxone_users_login_role">
					<?php wp_dropdown_roles( (string) diluxone_users_option( 'diluxone_users_login_role' ) ); ?>
				</select>
				<p class="description"><?php esc_html_e( 'The same one whichever door they came through: a role per door is a quiet way of handing out privileges.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'What the form asks for', 'diluxone-users' ); ?></th>
			<td>
				<?php $diluxone_users_asked = diluxone_users_register_fields(); ?>
				<p>
					<?php
					echo array() === $diluxone_users_asked
						? esc_html__( 'Only the e-mail address: no field is marked as required.', 'diluxone-users' )
						: esc_html(
							sprintf(
							/* translators: %s: the required fields, separated by dots */
								__( 'The address, plus: %s', 'diluxone-users' ),
								implode( ' · ', wp_list_pluck( $diluxone_users_asked, 'label' ) )
							)
						);
					?>
					<a href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-fields' ) ); ?>"><?php esc_html_e( 'Change it →', 'diluxone-users' ); ?></a>
				</p>
				<p class="description"><?php esc_html_e( 'Only the required ones, on purpose: a form that asks for everything is a form nobody finishes. The rest waits in their account. The e-mail is the username and never changes; there is no password unless the person sets one.', 'diluxone-users' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
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

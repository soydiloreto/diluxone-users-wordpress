<?php
/**
 * The Access screen: how somebody gets into this site, and how they get an
 * account to get into.
 *
 * One screen for the whole door. Registration was a screen of its own for a
 * while, on the argument that opening it changes nothing about signing in —
 * true, and beside the point: they are two questions asked at the same
 * moment, they share half their settings (whether the link creates the
 * account, whether a social account does, what happens to wp-login.php), and
 * a person setting up a site asks them together. So they are tabs of one
 * screen, under one summary that shows the door whole.
 *
 * Every tab answers one question and its label says which. The first tab
 * edits nothing: it is the state of everything the others decide, read from
 * the same settings they write.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/** The Access screen. */
function diluxone_users_screen_login(): void {
	diluxone_users_screen_panels( 'diluxone-users-login', diluxone_users_screens()['diluxone-users-login'] );
}

/**
 * Its tabs. Registration registers its own from its own file, onto this
 * screen; two-step verification lives on Security and is shown here only as
 * state. Passkeys are switched on here, with the other ways in, and set up on
 * Security: turning a door on is a question about doors.
 */
function diluxone_users_login_panels(): void {
	diluxone_users_register_panel(
		'diluxone-users-login',
		'summary',
		array(
			'label'    => __( 'Summary', 'diluxone-users' ),
			'position' => 0,
			'render'   => 'diluxone_users_screen_login_summary',
			'form'     => false,
		)
	);

	diluxone_users_register_panel(
		'diluxone-users-login',
		'page',
		array(
			'label'    => __( 'The sign-in page', 'diluxone-users' ),
			'position' => 10,
			'render'   => 'diluxone_users_screen_login_page',
			'save'     => 'diluxone_users_login_page_save',
		)
	);

	diluxone_users_register_panel(
		'diluxone-users-login',
		'ways',
		array(
			'label'    => __( 'Ways in', 'diluxone-users' ),
			'position' => 20,
			'render'   => 'diluxone_users_screen_login_ways',
			'save'     => 'diluxone_users_login_ways_save',
		)
	);
}
add_action( 'diluxone_users_register_panels', 'diluxone_users_login_panels' );

/** Where people sign in, and what becomes of wp-login.php. */
function diluxone_users_login_page_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	diluxone_users_save_options(
		array(
			'diluxone_users_login_page'    => absint( wp_unslash( $_POST['diluxone_users_login_page'] ?? 0 ) ),
			'diluxone_users_wp_screens'    => in_array( $_POST['diluxone_users_wp_screens'] ?? '', array( 'auto', 'mine', 'wp' ), true )
				? sanitize_key( wp_unslash( $_POST['diluxone_users_wp_screens'] ) )
				: 'auto',
			'diluxone_users_lost_password' => in_array( $_POST['diluxone_users_lost_password'] ?? '', array( 'wp', 'site', 'link' ), true )
				? sanitize_key( wp_unslash( $_POST['diluxone_users_lost_password'] ) )
				: 'wp',
		)
	);
	// phpcs:enable
}

/** With what somebody who already has an account gets in. */
function diluxone_users_login_ways_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- the panel verifies it.
	$saved = array(
		'diluxone_users_login_method'   => sanitize_key( wp_unslash( $_POST['diluxone_users_login_method'] ?? 'both' ) ),
		'diluxone_users_login_expiry'   => absint( wp_unslash( $_POST['diluxone_users_login_expiry'] ?? 15 ) ),
		'diluxone_users_login_throttle' => absint( wp_unslash( $_POST['diluxone_users_login_throttle'] ?? 60 ) ),
		// It is about what the sign-in box accepts, so it is decided here;
		// what a public name is, is decided on the account screen.
		'diluxone_users_handle_login'   => isset( $_POST['diluxone_users_handle_login'] ) ? 1 : 0,
		'diluxone_users_sso_login'      => isset( $_POST['diluxone_users_sso_login'] ) ? 1 : 0,
	);

	/*
	 * Passkeys are switched on here, with the other ways in, and set up on
	 * Security, where the feature lives. Only when the feature is installed:
	 * an install without it never draws the box, and writing a 0 for a box
	 * nobody drew is how a setting gets turned off by somebody who came to
	 * change something else.
	 */
	if ( function_exists( 'diluxone_users_passkeys_enabled' ) ) {
		$saved['diluxone_users_passkey_enabled'] = isset( $_POST['diluxone_users_passkey_enabled'] ) ? 1 : 0;
	}

	diluxone_users_save_options( $saved );
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

/**
 * The social providers that are working, as one line, or nothing.
 *
 * @return array<int, string>
 */
function diluxone_users_sso_working_names(): array {
	return array_values( array_map( static fn( array $p ): string => (string) $p['name'], diluxone_users_sso_available() ) );
}

/**
 * Every way into this site and the state each one is in.
 *
 * The settings that open and close these are spread over the tabs of this
 * screen and over Security; what nobody could see was the result. This reads
 * the same settings those tabs write — there is no second source of truth —
 * and every row links to the tab that changes it.
 *
 * @return array<int, array<string, string>>
 */
function diluxone_users_doors(): array {
	$page   = (int) diluxone_users_option( 'diluxone_users_login_page' );
	$title  = $page > 0 ? (string) get_the_title( $page ) : '';
	$mode   = diluxone_users_register_mode();
	$social = diluxone_users_sso_working_names();
	$tab    = static fn( string $id ): string => diluxone_users_admin_url( 'diluxone-users-login', array( 'tab' => $id ) );

	$rows = array();

	$rows[] = array(
		'label'  => __( 'The sign-in page', 'diluxone-users' ),
		'state'  => $page > 0 ? 'active' : 'off',
		'detail' => $page > 0
			? sprintf( '<a href="%s">%s</a>', esc_url( (string) get_permalink( $page ) ), esc_html( $title ) )
			: esc_html__( 'None: wp-login.php does the job.', 'diluxone-users' ),
		'url'    => $tab( 'page' ),
	);

	$rows[] = array(
		'label'  => __( 'The e-mail link', 'diluxone-users' ),
		'state'  => diluxone_users_login_has_link() ? 'active' : 'off',
		'detail' => diluxone_users_login_has_link()
			? sprintf(
				/* translators: %d: minutes */
				esc_html__( 'A single-use link, good for %d minutes.', 'diluxone-users' ),
				(int) diluxone_users_login_expiry()
			)
			: esc_html__( 'Nobody can ask for a link.', 'diluxone-users' ),
		'url'    => $tab( 'ways' ),
	);

	$rows[] = array(
		'label'  => __( 'Username and password', 'diluxone-users' ),
		'state'  => diluxone_users_login_has_password() ? 'active' : 'off',
		'detail' => diluxone_users_login_has_password()
			? esc_html__( 'WordPress’s own form, underneath the link.', 'diluxone-users' )
			: esc_html__( 'Closed: on this site a password opens nothing.', 'diluxone-users' ),
		'url'    => $tab( 'ways' ),
	);

	$rows[] = array(
		'label'  => __( 'A social account', 'diluxone-users' ),
		'state'  => ! diluxone_users_option( 'diluxone_users_sso_login' ) ? 'off' : ( array() !== $social ? 'active' : 'pending' ),
		'why'    => diluxone_users_option( 'diluxone_users_sso_login' ) && array() === $social ? __( 'no provider is working yet', 'diluxone-users' ) : '',
		'detail' => array() !== $social
			? esc_html( implode( ', ', $social ) )
			: esc_html__( 'The buttons are switched on but there is nothing to show.', 'diluxone-users' ),
		'url'    => diluxone_users_admin_url( 'diluxone-users-social' ),
	);

	$rows[] = array(
		'label'  => __( 'A passkey', 'diluxone-users' ),
		'state'  => diluxone_users_has_passkeys() ? 'active' : 'off',
		'detail' => diluxone_users_has_passkeys()
			? esc_html__( 'For whoever added one. It counts as both steps at once.', 'diluxone-users' )
			: esc_html__( 'Not offered.', 'diluxone-users' ),
		'url'    => $tab( 'ways' ),
	);

	$made = array();

	if ( diluxone_users_option( 'diluxone_users_login_register' ) ) {
		$made[] = __( 'the e-mail link', 'diluxone-users' );
	}

	if ( diluxone_users_option( 'diluxone_users_sso_register' ) ) {
		$made[] = __( 'a social account', 'diluxone-users' );
	}

	if ( diluxone_users_option( 'diluxone_users_register_form' ) ) {
		$made[] = __( 'the site’s own form', 'diluxone-users' );
	}

	if ( get_option( 'users_can_register' ) ) {
		$made[] = __( 'WordPress’s own form', 'diluxone-users' );
	}

	$rows[] = array(
		'label'  => __( 'Creating an account', 'diluxone-users' ),
		'state'  => array() !== $made ? ( 'form' === $mode && '' === diluxone_users_register_url() ? 'pending' : 'active' ) : 'off',
		'why'    => 'form' === $mode && '' === diluxone_users_register_url() ? __( 'the form has no page yet', 'diluxone-users' ) : '',
		'detail' => array() !== $made
			? esc_html(
				sprintf(
				/* translators: %s: the ways an account gets created, comma separated */
					__( 'Accounts are created by: %s.', 'diluxone-users' ),
					implode( ', ', $made )
				)
			)
			: esc_html__( 'Nobody registers themselves: accounts are made under Users.', 'diluxone-users' ),
		'url'    => $tab( 'register' ),
	);

	$rows[] = array(
		'label'  => __( 'After the door', 'diluxone-users' ),
		'state'  => 'off' !== (string) diluxone_users_option( 'diluxone_users_2fa_mode' ) ? 'active' : 'off',
		'detail' => 'off' !== (string) diluxone_users_option( 'diluxone_users_2fa_mode' )
			? esc_html__( 'A second step is asked, as set on Security.', 'diluxone-users' )
			: esc_html__( 'No second step.', 'diluxone-users' ),
		'url'    => diluxone_users_admin_url( 'diluxone-users-security' ),
		'change' => __( 'Security →', 'diluxone-users' ),
	);

	return $rows;
}

/** The state of the whole door. Nothing is edited here. */
function diluxone_users_screen_login_summary(): void {
	diluxone_users_intro( __( 'Every way into this site and the state each one is in, read from the settings the other tabs write. Nothing is edited here.', 'diluxone-users' ) );

	diluxone_users_summary_table( diluxone_users_doors() );
	?>
	<h2><?php esc_html_e( 'If you get locked out', 'diluxone-users' ); ?></h2>
	<p class="description">
		<?php
		printf(
			/* translators: 1: the emergency URL, 2: the WP-CLI command */
			esc_html__( 'Whoever can administer the site always has a way in through %1$s, whatever is chosen on the other tabs. With a shell, %2$s prints a single-use link and needs no e-mail to work.', 'diluxone-users' ),
			'<code>' . esc_html( wp_login_url() ) . '?diluxone-users-admin=1</code>',
			'<code>wp diluxone-users login ' . esc_html( wp_get_current_user()->user_email ) . '</code>'
		);
		?>
	</p>
	<?php
}

/**
 * Is wp-login.php still a screen somebody lands on?
 *
 * The tab asks it twice — whether it is worth saying how that screen looks,
 * and whether an answer that sends people there means anything — so it is
 * answered once. Kept, it is a screen; taken over, it is not; and 'auto'
 * takes it over only while the e-mail link is the only way in, so a site with
 * a password still has it. With no sign-in page chosen nothing has been
 * decided yet, and a result nobody has asked for is not worth drawing.
 */
function diluxone_users_wp_login_seen(): bool {
	// With no page of its own, wp-login.php is not one of the ways in: it is
	// the only one. That is the moment how it looks matters most, so it is
	// answered first and the rest of the rules do not get a say.
	if ( (int) diluxone_users_option( 'diluxone_users_login_page' ) <= 0 ) {
		return true;
	}

	$chosen = diluxone_users_wp_screens();

	return 'wp' === $chosen || ( 'auto' === $chosen && ! diluxone_users_login_only_link() );
}

/** Where people sign in, and what becomes of wp-login.php. */
function diluxone_users_screen_login_page(): void {
	$page   = (int) diluxone_users_option( 'diluxone_users_login_page' );
	$lost   = (string) diluxone_users_option( 'diluxone_users_lost_password' );
	$chosen = diluxone_users_wp_screens();
	$seen   = diluxone_users_wp_login_seen();
	$taken  = $page > 0 && diluxone_users_wp_screens_taken();
	$link   = diluxone_users_login_has_link();

	diluxone_users_intro( __( 'The page holding the sign-in form, and what becomes of wp-login.php. Put the [diluxone_users_login] shortcode on the page you pick.', 'diluxone-users' ) );

	diluxone_users_ui_field_open( __( 'The sign-in page', 'diluxone-users' ), 'diluxone_users_login_page' );

	/** @var array<string, mixed> $diluxone_users_dropdown */
	$diluxone_users_dropdown = array(
		'name'              => 'diluxone_users_login_page',
		'id'                => 'diluxone_users_login_page',
		'selected'          => $page,
		'show_option_none'  => __( '— None: wp-login.php does the job —', 'diluxone-users' ),
		'option_none_value' => 0,
	);

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_dropdown_pages() escapes its own and prints it.
	wp_dropdown_pages( $diluxone_users_dropdown );

	diluxone_users_ui_field_close( __( 'With none chosen, everybody signs in on wp-login.php and nothing below applies.', 'diluxone-users' ) );

	diluxone_users_ui_section(
		__( 'What happens to wp-login.php', 'diluxone-users' ),
		__( 'wp-login.php is the sign-in screen WordPress brings with it, at /wp-login.php: the grey box with the logo on it that everybody has seen. It keeps working whatever is chosen here — what changes is whether anybody still lands on it.', 'diluxone-users' )
	);

	if ( $page <= 0 ) {
		diluxone_users_not_now( __( 'There is no sign-in page to send anybody to. What is chosen here waits for one.', 'diluxone-users' ) );
	}

	diluxone_users_ui_choices(
		array(
			array(
				'name'    => 'diluxone_users_wp_screens',
				'value'   => 'mine',
				'checked' => 'mine' === $chosen,
				'title'   => __( 'Send everybody to the sign-in page above', 'diluxone-users' ),
				'help'    => __( 'wp-login.php stops being a screen: an old bookmark, a plugin’s link or its “register” link all land on this site’s own pages. Whoever administers the site keeps the emergency way in on the Summary tab.', 'diluxone-users' ),
			),
			array(
				'name'    => 'diluxone_users_wp_screens',
				'value'   => 'wp',
				'checked' => 'wp' === $chosen,
				'title'   => __( 'Keep it as a second sign-in screen', 'diluxone-users' ),
				'help'    => __( 'The site has two screens that sign people in: yours, and the one WordPress brings. Anybody arriving on wp-login.php from a bookmark or a plugin sees the second.', 'diluxone-users' ),
			),
			array(
				'name'    => 'diluxone_users_wp_screens',
				'value'   => 'auto',
				'checked' => 'auto' === $chosen,
				'title'   => __( 'Send people to the page only while the e-mail link is the only way in', 'diluxone-users' ),
				'help'    => __( 'While a password still opens something here, wp-login.php is left alone. The day the link is the only way in, its form could not work anyway.', 'diluxone-users' ),
			),
		)
	);

	/*
	 * How that screen looks is worth a line only while somebody can see it.
	 * Sent to the page, nobody does; with no page chosen, nothing has been
	 * decided. Either way the answer is the same and the row goes.
	 */
	if ( $seen ) {
		diluxone_users_ui_section(
			__( 'How wp-login.php looks', 'diluxone-users' ),
			__( 'People still land on that screen, so it is worth it looking like the rest of the site. It is set on Design, with the rest of what the site looks like.', 'diluxone-users' )
		);
		?>
		<p>
			<?php echo diluxone_users_option( 'diluxone_users_wp_login_brand' ) ? esc_html__( 'With the site’s logo and colour on it.', 'diluxone-users' ) : esc_html__( 'As WordPress ships it.', 'diluxone-users' ); ?>
			<a href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-design', array( 'tab' => 'wp' ) ) ); ?>"><?php esc_html_e( 'Change it →', 'diluxone-users' ); ?></a>
		</p>
		<?php
	}

	diluxone_users_ui_section(
		__( 'If somebody forgets their password', 'diluxone-users' ),
		__( 'Forgetting a password is about changing it, not about getting in. This is where the new one is typed — or whether one is typed at all.', 'diluxone-users' )
	);

	if ( ! diluxone_users_login_has_password() ) {
		diluxone_users_not_now( __( 'There is no password on this site, so there is nothing to forget. What is chosen here waits for one.', 'diluxone-users' ) );
	}

	diluxone_users_ui_choices(
		array(
			array(
				'name'    => 'diluxone_users_lost_password',
				'value'   => 'wp',
				'checked' => 'wp' === $lost,
				'title'   => __( 'WordPress’s screen', 'diluxone-users' ),
				'help'    => __( 'They ask for a reset e-mail on wp-login.php, and the link in it lands on wp-login.php to type the new password. Two screens that look like WordPress, at the moment somebody is worried about their account.', 'diluxone-users' ),
			),
			array(
				'name'    => 'diluxone_users_lost_password',
				'value'   => 'site',
				'checked' => 'site' === $lost,
				'title'   => __( 'This site’s screen', 'diluxone-users' ),
				'help'    => __( 'They still ask on WordPress’s form — it is the only place that sends the e-mail — but the link lands on your sign-in page, and the new password is typed there, in your design.', 'diluxone-users' ),
				'state'   => $page > 0 ? '' : 'off',
				'note'    => $page > 0 ? '' : __( 'there is no sign-in page to land on yet', 'diluxone-users' ),
			),
			array(
				'name'    => 'diluxone_users_lost_password',
				'value'   => 'link',
				'checked' => 'link' === $lost,
				'title'   => __( 'Nowhere: “I forgot my password” leads to the sign-in page', 'diluxone-users' ),
				'help'    => __( 'Nothing is reset and the password is left as it was. On a site where a link already opens the door, a reset asks for the same address to send a second e-mail doing what the first one does.', 'diluxone-users' ),
				'state'   => $link ? '' : 'off',
				'note'    => $link ? '' : __( 'the e-mail link is not one of the ways in, so this sends them nowhere they can use', 'diluxone-users' ),
			),
		)
	);
}

/** With what somebody who already has an account gets in. */
function diluxone_users_screen_login_ways(): void {
	$method   = diluxone_users_login_method();
	$social   = diluxone_users_sso_working_names();
	$handles  = (bool) diluxone_users_option( 'diluxone_users_handle_enabled' );
	$passkeys = function_exists( 'diluxone_users_passkeys_enabled' );

	diluxone_users_intro( __( 'With what somebody who already has an account gets in. Every way in is switched on here; what each one does once it is on is set where that feature lives. Who gets an account is the Registration tab.', 'diluxone-users' ) );

	diluxone_users_ui_section( __( 'The e-mail link and the password', 'diluxone-users' ), __( 'The two the sign-in form draws itself. Only the second answer closes anything.', 'diluxone-users' ) );

	diluxone_users_forzado_aviso( 'diluxone_users_login_method' );

	diluxone_users_ui_choices(
		array(
			array(
				'name'    => 'diluxone_users_login_method',
				'value'   => 'both',
				'checked' => 'both' === $method,
				'title'   => __( 'The e-mail link and the password, both', 'diluxone-users' ),
				'help'    => __( 'The form takes an address and sends a link, and it takes a password from whoever has one. Nothing is closed.', 'diluxone-users' ),
			),
			array(
				'name'    => 'diluxone_users_login_method',
				'value'   => 'link',
				'checked' => 'link' === $method,
				'title'   => __( 'Only the e-mail link — no passwords on this site', 'diluxone-users' ),
				'help'    => __( 'wp-login.php stops showing its password form and WordPress’s own registration is locked shut, because a password would open nothing.', 'diluxone-users' ),
			),
			array(
				'name'    => 'diluxone_users_login_method',
				'value'   => 'password',
				'checked' => 'password' === $method,
				'title'   => __( 'Only username and password', 'diluxone-users' ),
				'help'    => __( 'Nobody can ask for a link: the form asks for a password and nothing else.', 'diluxone-users' ),
			),
		)
	);

	/*
	 * What the link does belongs to the link, and the link is two of the three
	 * answers above — so it cannot hang inside one card, and the same three
	 * fields printed in two cards would post two values for one setting. It is
	 * a group of its own, and the line above it says when the group is moot.
	 */
	diluxone_users_ui_section( __( 'The e-mail link', 'diluxone-users' ), __( 'How long a link lasts, how often one can be asked for, and what the box accepts.', 'diluxone-users' ) );

	if ( ! diluxone_users_login_has_link() ) {
		diluxone_users_not_now( __( 'The link is not one of the ways in right now. What is set here waits for it.', 'diluxone-users' ) );
	}
	?>
	<div class="du-fields">
		<?php diluxone_users_ui_field_open( __( 'The link expires after', 'diluxone-users' ), 'diluxone_users_login_expiry' ); ?>
		<input type="number" id="diluxone_users_login_expiry" name="diluxone_users_login_expiry" min="1" max="1440" class="small-text" value="<?php echo esc_attr( (string) diluxone_users_option( 'diluxone_users_login_expiry' ) ); ?>">
		<?php
		esc_html_e( 'minutes', 'diluxone-users' );
		diluxone_users_ui_field_close();

		diluxone_users_ui_field_open( __( 'Wait between two requests', 'diluxone-users' ), 'diluxone_users_login_throttle' );
		?>
		<input type="number" id="diluxone_users_login_throttle" name="diluxone_users_login_throttle" min="0" class="small-text" value="<?php echo esc_attr( (string) diluxone_users_option( 'diluxone_users_login_throttle' ) ); ?>">
		<?php
		esc_html_e( 'seconds, for the same address', 'diluxone-users' );
		diluxone_users_ui_field_close( __( 'Stops the form being used as a machine for e-mailing third parties.', 'diluxone-users' ) );
		?>
	</div>
	<?php
	if ( ! $handles ) {
		diluxone_users_not_now(
			__( 'Public names are off, so only the e-mail address is accepted.', 'diluxone-users' ),
			diluxone_users_admin_url( 'diluxone-users-account', array( 'tab' => 'handle' ) ),
			__( 'Turn them on →', 'diluxone-users' )
		);
	}

	diluxone_users_ui_choices(
		array(
			array(
				'type'    => 'checkbox',
				'name'    => 'diluxone_users_handle_login',
				'value'   => '1',
				'checked' => (bool) diluxone_users_option( 'diluxone_users_handle_login' ),
				'title'   => __( 'The box takes the public name as well as the e-mail address', 'diluxone-users' ),
				'help'    => __( 'The link still goes to the address on the account, never to what was typed. It only saves people from remembering which address they used.', 'diluxone-users' ),
			),
		)
	);

	/*
	 * Switching a way in on belongs with the other ways in; what it does once
	 * it is on belongs where the feature lives. So both boxes are here and
	 * both cards carry the way to the rest of their settings — the passkeys
	 * box used to be on Security, which is why nobody found it from the one
	 * screen that lists the doors.
	 */
	diluxone_users_ui_section( __( 'Other ways in', 'diluxone-users' ), __( 'Switched on here, one by one. What each one does once it is on is set where that feature lives.', 'diluxone-users' ) );

	$diluxone_users_social_help = __( 'The buttons under the form. Off, they go, and whoever already linked a social account keeps it and can unlink it from their account.', 'diluxone-users' );

	if ( array() !== $social ) {
		$diluxone_users_social_help = sprintf(
			/* translators: %s: providers working, comma separated */
			__( 'Working right now: %s. Off, the buttons go, and whoever already linked a social account keeps it.', 'diluxone-users' ),
			implode( ', ', $social )
		);
	}

	$diluxone_users_ways = array(
		array(
			'type'    => 'checkbox',
			'name'    => 'diluxone_users_sso_login',
			'value'   => '1',
			'checked' => (bool) diluxone_users_option( 'diluxone_users_sso_login' ),
			'title'   => __( 'A social account', 'diluxone-users' ),
			'help'    => esc_html( $diluxone_users_social_help ) . sprintf(
				' <a href="%1$s">%2$s</a>',
				esc_url( diluxone_users_admin_url( 'diluxone-users-social' ) ),
				esc_html__( 'More social options →', 'diluxone-users' )
			),
			'state'   => array() === $social ? 'pending' : '',
			'note'    => array() === $social ? __( 'no provider is working yet', 'diluxone-users' ) : '',
		),
	);

	if ( $passkeys ) {
		$diluxone_users_insecure = ! is_ssl() && 'local' !== wp_get_environment_type();

		$diluxone_users_ways[] = array(
			'type'    => 'checkbox',
			'name'    => 'diluxone_users_passkey_enabled',
			'value'   => '1',
			'checked' => diluxone_users_passkeys_enabled(),
			'title'   => __( 'A passkey', 'diluxone-users' ),
			'help'    => esc_html__( 'A key that stays on the person’s device or keychain: nothing to type, nothing to phish, and it counts as both steps at once.', 'diluxone-users' ) . sprintf(
				' <a href="%1$s">%2$s</a>',
				esc_url( diluxone_users_admin_url( 'diluxone-users-security', array( 'tab' => 'passkeys' ) ) ),
				esc_html__( 'More passkey options →', 'diluxone-users' )
			),
			'state'   => $diluxone_users_insecure ? 'pending' : '',
			'note'    => $diluxone_users_insecure ? __( 'this site is not on HTTPS, and browsers refuse passkeys until it is', 'diluxone-users' ) : '',
		);
	}

	diluxone_users_ui_choices( $diluxone_users_ways );
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
		<tr>
			<th scope="row"><?php esc_html_e( 'The envelope on “check your email”', 'diluxone-users' ); ?></th>
			<td>
				<label class="diluxone-users-roles__item">
					<input type="radio" name="diluxone_users_sent_icon" value="plain" <?php checked( 'circle' !== (string) diluxone_users_option( 'diluxone_users_sent_icon' ) ); ?>>
					<?php esc_html_e( 'On its own', 'diluxone-users' ); ?>
				</label>
				<label class="diluxone-users-roles__item">
					<input type="radio" name="diluxone_users_sent_icon" value="circle" <?php checked( 'circle' === (string) diluxone_users_option( 'diluxone_users_sent_icon' ) ); ?>>
					<?php esc_html_e( 'In a circle of washed colour', 'diluxone-users' ); ?>
				</label>
			</td>
		</tr>
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
			<th scope="row"><?php esc_html_e( 'Logo', 'diluxone-users' ); ?></th>
			<td>
				<p>
					<?php echo (int) diluxone_users_option( 'diluxone_users_login_logo' ) > 0 ? esc_html__( 'The site’s logo, above the form in every template.', 'diluxone-users' ) : esc_html__( 'None yet.', 'diluxone-users' ); ?>
					<a href="<?php echo esc_url( diluxone_users_admin_url( 'diluxone-users-design', array( 'tab' => 'brand' ) ) ); ?>"><?php esc_html_e( 'It is set on Your brand →', 'diluxone-users' ); ?></a>
				</p>
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

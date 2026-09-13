<?php
/**
 * The status screen: what breaks today without saying so.
 *
 * Almost nothing this plugin does fails with a visible error. The account
 * page loses the shortcode and shows the raw text; the mail stops going out
 * and the sign-in links reach nobody; the site drops to HTTP and the passkeys
 * disappear with no explanation; somebody switches the permalinks to "plain"
 * and the account area starts returning 404. All of those are discovered when
 * somebody complains, not when they happen.
 *
 * Here they are all together, on a single screen that touches nothing: it
 * looks and reports. What gets fixed is fixed in Tools.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/**
 * One check, with its verdict.
 *
 * @param string $label  What was looked at.
 * @param string $state  'ok', 'warn' or 'fail'.
 * @param string $detail What was found.
 * @return array{label: string, state: string, detail: string}
 */
function diluxone_users_check( string $label, string $state, string $detail ): array {
	return array(
		'label'  => $label,
		'state'  => $state,
		'detail' => $detail,
	);
}

/**
 * Is the page holding the account area in working order?
 *
 * Three different things can be wrong and look identical from the outside:
 * that none has been chosen, that the chosen one no longer exists or is a
 * draft, or that it exists but is missing the shortcode. Each one is named
 * for what it is.
 *
 * @param string $option    The option storing the page ID.
 * @param string $shortcode The shortcode that page has to contain.
 * @param string $label     What that page is called in here.
 * @return array{label: string, state: string, detail: string}
 */
function diluxone_users_check_page( string $option, string $shortcode, string $label ): array {
	$id = (int) diluxone_users_option( $option );

	if ( $id <= 0 ) {
		return diluxone_users_check( $label, 'warn', __( 'No page chosen yet.', 'diluxone-users' ) );
	}

	$page = get_post( $id );

	if ( ! $page instanceof WP_Post || 'page' !== $page->post_type ) {
		return diluxone_users_check( $label, 'fail', __( 'The chosen page no longer exists.', 'diluxone-users' ) );
	}

	if ( 'publish' !== $page->post_status ) {
		return diluxone_users_check(
			$label,
			'fail',
			sprintf(
				/* translators: %s: title of the page */
				__( '“%s” is not published, so nobody can reach it.', 'diluxone-users' ),
				$page->post_title
			)
		);
	}

	$draws = has_shortcode( (string) $page->post_content, $shortcode );

	/**
	 * Filters whether that page really draws this part of the plugin.
	 *
	 * The shortcode in the content is the ordinary way and the only one that
	 * can be checked from here. A site that draws it from a template — which
	 * is a supported way to use this plugin — answers true here and the check
	 * stops calling a working page broken.
	 *
	 * @param bool    $draws  Whether the shortcode was found in the content.
	 * @param string  $option The option holding the page ID.
	 * @param WP_Post $page   The page itself.
	 */
	$draws = (bool) apply_filters( 'diluxone_users_page_draws', $draws, $option, $page );

	if ( ! $draws ) {
		// A warning and not a failure: what can be said for certain is that
		// the shortcode is not in the content, and that is not the same as
		// the page being broken.
		return diluxone_users_check(
			$label,
			'warn',
			sprintf(
				/* translators: 1: title of the page, 2: the shortcode that was not found */
				__( '“%1$s” does not contain %2$s. If your theme draws it from a template, this is fine; if not, the page renders empty.', 'diluxone-users' ),
				$page->post_title,
				'[' . $shortcode . ']'
			)
		);
	}

	return diluxone_users_check( $label, 'ok', $page->post_title );
}

/**
 * Every check, in order of how much it hurts.
 *
 * @return array<int, array{label: string, state: string, detail: string}>
 */
function diluxone_users_checks(): array {
	$checks = array();

	$checks[] = diluxone_users_check_page( 'diluxone_users_account_page', 'diluxone_users_account', __( 'Account page', 'diluxone-users' ) );
	$checks[] = diluxone_users_check_page( 'diluxone_users_login_page', 'diluxone_users_login', __( 'Sign-in page', 'diluxone-users' ) );

	// Mail. Without it nobody comes in by link and nobody passes a second step.
	$mail = diluxone_users_mail_status();

	if ( 'fail' === $mail['state'] ) {
		$checks[] = diluxone_users_check(
			__( 'Outgoing mail', 'diluxone-users' ),
			'fail',
			'' !== $mail['error']
				? wp_strip_all_tags( $mail['error'] )
				: __( 'The last message could not be sent.', 'diluxone-users' )
		);
	} elseif ( 'ok' === $mail['state'] ) {
		$checks[] = diluxone_users_check(
			__( 'Outgoing mail', 'diluxone-users' ),
			'ok',
			sprintf(
				/* translators: %s: how long ago, e.g. "2 hours" */
				__( 'Last message sent %s ago.', 'diluxone-users' ),
				human_time_diff( $mail['time'] )
			)
		);
	} else {
		$checks[] = diluxone_users_check(
			__( 'Outgoing mail', 'diluxone-users' ),
			'warn',
			__( 'Nothing sent yet. Send a test from Tools.', 'diluxone-users' )
		);
	}

	// HTTPS. Passkeys are WebAuthn, and WebAuthn does not exist outside HTTPS.
	//
	// wp_is_using_https() is what is looked at and not is_ssl(): is_ssl() says
	// whether *this* request arrived over TLS, which is false in WP-CLI and can
	// be false behind a proxy terminating TLS earlier. What decides whether the
	// browser speaks HTTPS — and therefore whether WebAuthn exists — is the
	// scheme of home_url().
	$passkeys = (int) diluxone_users_option( 'diluxone_users_passkey_enabled' );

	if ( wp_is_using_https() ) {
		$checks[] = diluxone_users_check( 'HTTPS', 'ok', __( 'The site is served over HTTPS.', 'diluxone-users' ) );
	} else {
		$checks[] = diluxone_users_check(
			'HTTPS',
			$passkeys ? 'fail' : 'warn',
			$passkeys
				? __( 'Passkeys are on, but WebAuthn does not work outside HTTPS: nobody can register or use one.', 'diluxone-users' )
				: __( 'The site is not served over HTTPS. Passkeys will not work if you turn them on.', 'diluxone-users' )
		);
	}

	// Permalinks. The account area hangs off a rewrite rule.
	$permalinks = (string) get_option( 'permalink_structure' );

	$checks[] = '' === $permalinks
		? diluxone_users_check(
			__( 'Permalinks', 'diluxone-users' ),
			'fail',
			__( 'Set to “Plain”. The account area needs pretty permalinks to route its sections.', 'diluxone-users' )
		)
		: diluxone_users_check( __( 'Permalinks', 'diluxone-users' ), 'ok', $permalinks );

	// The rewrite rules, which are rewritten when the version changes.
	$checks[] = get_option( 'diluxone_users_rewrite_version' ) === DILUXONE_USERS_VERSION
		? diluxone_users_check( __( 'Rewrite rules', 'diluxone-users' ), 'ok', __( 'Up to date.', 'diluxone-users' ) )
		: diluxone_users_check(
			__( 'Rewrite rules', 'diluxone-users' ),
			'warn',
			__( 'Not rewritten for this version yet. Flush them from Tools if a section 404s.', 'diluxone-users' )
		);

	// That there is at least one way in.
	$checks[] = diluxone_users_check_ways_in();

	// Social networks filled in but turned off, and the other way round.
	$social = diluxone_users_check_social();

	if ( null !== $social ) {
		$checks[] = $social;
	}

	return $checks;
}

/**
 * Is any door still open?
 *
 * The password can be turned off, no network configured and the e-mail link
 * left as the only way in. If the mail does not go out on top of that, nobody
 * comes in — not even the administrator — and the site is locked from the
 * inside.
 *
 * @return array{label: string, state: string, detail: string}
 */
function diluxone_users_check_ways_in(): array {
	$ways = array();

	if ( diluxone_users_login_has_password() ) {
		$ways[] = __( 'password', 'diluxone-users' );
	}

	if ( diluxone_users_login_has_link() ) {
		$ways[] = __( 'e-mail link', 'diluxone-users' );
	}

	if ( array() !== diluxone_users_sso_available() ) {
		$ways[] = __( 'social login', 'diluxone-users' );
	}

	if ( array() === $ways ) {
		return diluxone_users_check(
			__( 'Ways in', 'diluxone-users' ),
			'fail',
			__( 'No sign-in method is enabled at all.', 'diluxone-users' )
		);
	}

	$email_only = array( __( 'e-mail link', 'diluxone-users' ) ) === $ways;

	if ( $email_only && ! diluxone_users_mail_works() ) {
		return diluxone_users_check(
			__( 'Ways in', 'diluxone-users' ),
			'fail',
			__( 'The e-mail link is the only way in, and mail is failing. Nobody can sign in.', 'diluxone-users' )
		);
	}

	return diluxone_users_check( __( 'Ways in', 'diluxone-users' ), 'ok', implode( ', ', $ways ) );
}

/**
 * Providers with the credentials filled in but turned off.
 *
 * It is the dullest mistake and the most common: the ID and the secret are
 * filled in, it is saved, and the button does not appear because the provider
 * was never ticked.
 *
 * @return array{label: string, state: string, detail: string}|null
 */
function diluxone_users_check_social(): ?array {
	$ready   = array();
	$dormant = array();

	foreach ( diluxone_users_sso_providers() as $id => $provider ) {
		if ( ! diluxone_users_sso_configured( $id ) ) {
			continue;
		}

		$ready[] = $id;

		if ( ! diluxone_users_sso_ready( $id ) ) {
			$dormant[] = (string) ( $provider['name'] ?? $id );
		}
	}

	if ( array() === $ready ) {
		return null;
	}

	if ( array() === $dormant ) {
		return diluxone_users_check(
			__( 'Social login', 'diluxone-users' ),
			'ok',
			sprintf(
				/* translators: %d: number of providers */
				_n( '%d provider configured and enabled.', '%d providers configured and enabled.', count( $ready ), 'diluxone-users' ),
				count( $ready )
			)
		);
	}

	return diluxone_users_check(
		__( 'Social login', 'diluxone-users' ),
		'warn',
		sprintf(
			/* translators: %s: comma-separated provider names */
			__( 'Credentials are saved but the provider is off, so no button shows: %s.', 'diluxone-users' ),
			implode( ', ', $dormant )
		)
	);
}

/**
 * How many people use each thing.
 *
 * One query per number and against `usermeta`, which is indexed by
 * `meta_key`. On a site with twenty-five thousand accounts this has to cost
 * the same as on one with twenty.
 *
 * @return array<string, int>
 */
function diluxone_users_stats(): array {
	global $wpdb;

	$count_meta = static function ( string $meta ) use ( $wpdb ): int {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this is the status screen: the number has to be the one from now, not the one from the cache.
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != ''", $meta )
		);
	};

	return array(
		'users'    => (int) count_users()['total_users'],
		'totp'     => $count_meta( 'diluxone_users_totp_secret' ),
		'passkeys' => $count_meta( 'diluxone_users_passkeys' ),
		'social'   => $count_meta( 'diluxone_users_sso' ),
		'handles'  => $count_meta( 'diluxone_users_handle' ),
	);
}

/**
 * What to paste into a support ticket.
 *
 * @return array<string, string>
 */
function diluxone_users_environment(): array {
	global $wp_version;

	return array(
		__( 'Plugin version', 'diluxone-users' )    => DILUXONE_USERS_VERSION,
		__( 'WordPress', 'diluxone-users' )         => (string) $wp_version,
		'PHP'                                       => PHP_VERSION,
		__( 'Site language', 'diluxone-users' )     => (string) get_locale(),
		__( 'Multisite', 'diluxone-users' )         => is_multisite() ? __( 'yes', 'diluxone-users' ) : __( 'no', 'diluxone-users' ),
		__( 'Object cache', 'diluxone-users' )      => wp_using_ext_object_cache() ? __( 'external', 'diluxone-users' ) : __( 'none', 'diluxone-users' ),
		__( 'WordPress profile', 'diluxone-users' ) => (string) diluxone_users_option( 'diluxone_users_wp_profile' ),
	);
}

/** Screen status. */
function diluxone_users_screen_status(): void {
	diluxone_users_screen_open( __( 'Status', 'diluxone-users' ) );
	diluxone_users_intro( __( 'Nothing here changes anything: it looks and it counts. What needs fixing gets fixed in Tools.', 'diluxone-users' ) );

	$checks = diluxone_users_checks();
	$bad    = 0;

	foreach ( $checks as $check ) {
		if ( 'fail' === $check['state'] ) {
			++$bad;
		}
	}

	if ( 0 === $bad ) {
		diluxone_users_notice( __( 'Everything checks out.', 'diluxone-users' ) );
	} else {
		diluxone_users_notice(
			sprintf(
				/* translators: %d: number of failing checks */
				_n( '%d thing needs attention.', '%d things need attention.', $bad, 'diluxone-users' ),
				$bad
			),
			'error'
		);
	}

	$icons = array(
		'ok'   => 'dashicons-yes-alt',
		'warn' => 'dashicons-warning',
		'fail' => 'dashicons-dismiss',
	);

	echo '<table class="widefat striped diluxone-users-status">';
	echo '<tbody>';

	foreach ( $checks as $check ) {
		printf(
			'<tr class="diluxone-users-status--%1$s"><td class="diluxone-users-status__icon"><span class="dashicons %2$s"></span></td><th scope="row">%3$s</th><td>%4$s</td></tr>',
			esc_attr( $check['state'] ),
			esc_attr( $icons[ $check['state'] ] ),
			esc_html( $check['label'] ),
			esc_html( $check['detail'] )
		);
	}

	echo '</tbody></table>';

	$stats = diluxone_users_stats();

	printf( '<h2>%s</h2>', esc_html__( 'How much of it is being used', 'diluxone-users' ) );
	echo '<table class="widefat striped"><tbody>';

	$rows = array(
		__( 'Accounts', 'diluxone-users' )           => $stats['users'],
		__( 'With an authenticator app', 'diluxone-users' ) => $stats['totp'],
		__( 'With a passkey', 'diluxone-users' )     => $stats['passkeys'],
		__( 'With a social account linked', 'diluxone-users' ) => $stats['social'],
		__( 'With a public name', 'diluxone-users' ) => $stats['handles'],
	);

	foreach ( $rows as $label => $value ) {
		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s</td></tr>',
			esc_html( (string) $label ),
			esc_html( number_format_i18n( $value ) )
		);
	}

	echo '</tbody></table>';

	printf( '<h2>%s</h2>', esc_html__( 'Environment', 'diluxone-users' ) );
	diluxone_users_intro( __( 'Copy this into a support message and half the back-and-forth disappears.', 'diluxone-users' ) );
	echo '<table class="widefat striped"><tbody>';

	foreach ( diluxone_users_environment() as $label => $value ) {
		printf(
			'<tr><th scope="row">%1$s</th><td><code>%2$s</code></td></tr>',
			esc_html( (string) $label ),
			esc_html( $value )
		);
	}

	echo '</tbody></table>';

	diluxone_users_screen_close();
}

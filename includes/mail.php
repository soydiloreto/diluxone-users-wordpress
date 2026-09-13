<?php
/**
 * Does this site send e-mail?
 *
 * The question looks silly and it is the most important one in the plugin:
 * the sign-in link, the second-factor code and the confirmation for deleting
 * an account are all e-mails. If they do not go out, the site has no way in
 * and nobody finds out until somebody cannot get in.
 *
 * The temptation is to ask `has_filter('phpmailer_init')`: if somebody hooked
 * there, a server is configured. That is a lie — a mail plugin that is active
 * but badly configured hooks all the same — and it is exactly the mistake
 * that made this screen say "ready" while nothing was going out.
 *
 * So nothing is guessed: what really happened the last time WordPress tried
 * to send something is recorded, and a button is offered to test it.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/** Records that a send went through. */
function diluxone_users_mail_ok(): void {
	update_option(
		'diluxone_users_mail_last',
		array(
			'ok'    => 1,
			'time'  => time(),
			'error' => '',
		)
	);
}
add_action( 'wp_mail_succeeded', 'diluxone_users_mail_ok' );

/**
 * Records that a send failed, with the reason.
 *
 * @param WP_Error $error
 */
function diluxone_users_mail_failed( $error ): void {
	update_option(
		'diluxone_users_mail_last',
		array(
			'ok'    => 0,
			'time'  => time(),
			'error' => $error->get_error_message(),
		)
	);
}
add_action( 'wp_mail_failed', 'diluxone_users_mail_failed' );

/**
 * What is known about outgoing mail.
 *
 * @return array{state: string, time: int, error: string}
 *         state: 'ok', 'fail' or 'unknown'.
 */
function diluxone_users_mail_status(): array {
	$last = get_option( 'diluxone_users_mail_last', false );

	if ( ! is_array( $last ) ) {
		return array(
			'state' => 'unknown',
			'time'  => 0,
			'error' => '',
		);
	}

	return array(
		'state' => ! empty( $last['ok'] ) ? 'ok' : 'fail',
		'time'  => (int) ( $last['time'] ?? 0 ),
		'error' => (string) ( $last['error'] ?? '' ),
	);
}

/**
 * Can an e-mail be counted on to arrive?
 *
 * "Not tested yet" counts as yes: there is no reason to frighten anybody with
 * a suspicion, and the first real send will tell the truth.
 */
function diluxone_users_mail_works(): bool {
	return 'fail' !== diluxone_users_mail_status()['state'];
}

/** Sends a test e-mail to whoever asked for it, from the admin. */
function diluxone_users_mail_test(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'diluxone-users' ) );
	}

	check_admin_referer( 'diluxone_users_mail_test' );

	$user = wp_get_current_user();

	$ok = wp_mail(
		$user->user_email,
		sprintf(
				/* translators: %s: site name */
			__( 'Test from %s', 'diluxone-users' ),
			wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES )
		),
		__( 'If this arrived, the site can send the sign-in links, the second-step codes and the data requests. If it did not, none of those work either.', 'diluxone-users' )
	);

	// wp_mail() only reports whether it handed the message to the server; the
	// hook above already recorded what really happened. It is stored anyway in
	// case nothing fired.
	if ( ! $ok && 'fail' !== diluxone_users_mail_status()['state'] ) {
		diluxone_users_mail_failed( new WP_Error( 'diluxone_users_mail', __( 'wp_mail() returned false and said nothing else.', 'diluxone-users' ) ) );
	}

	wp_safe_redirect( diluxone_users_admin_url( 'diluxone-users', array( 'diluxone_users_mail' => $ok ? 'sent' : 'failed' ) ) );
	exit;
}
add_action( 'admin_post_diluxone_users_mail_test', 'diluxone_users_mail_test' );

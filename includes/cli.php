<?php
/**
 * WP-CLI commands.
 *
 * It exists for one concrete case: a site without passwords where the mail
 * does not go out. If somebody is locked out there — the session expired, the
 * social provider fails — there is no door. With access to the server, this
 * command prints the link in the terminal instead of sending it.
 *
 *   wp diluxone-users login pablo@example.com
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Prints a sign-in link for an e-mail address.
 *
 * ## OPTIONS
 *
 * <email>
 * : The account e-mail.
 *
 * [--send]
 * : Besides printing it, send it by e-mail.
 *
 * ## EXAMPLES
 *
 *     wp diluxone-users login pablo@example.com
 *     wp diluxone-users login pablo@example.com --send
 *
 * @param array<int, string>    $args
 * @param array<string, string> $options
 */
function diluxone_users_cli_login( array $args, array $options = array() ): void {
	$email = sanitize_email( $args[0] ?? '' );

	if ( '' === $email || ! is_email( $email ) ) {
		WP_CLI::error( 'Hace falta un correo válido.' );
	}

	$user = get_user_by( 'email', $email );

	if ( ! $user ) {
		WP_CLI::error( sprintf( 'No existe ninguna cuenta con el correo %s.', $email ) );
	}

	$token = diluxone_users_token_create( (int) $user->ID );
	$url   = diluxone_users_login_link( (int) $user->ID, $token );

	if ( ! empty( $options['send'] ) ) {
		$sent = diluxone_users_login_send( (int) $user->ID, $email, $token );
		WP_CLI::log( $sent ? 'Correo enviado.' : 'No se pudo enviar el correo.' );
	}

	WP_CLI::log( $url );
	WP_CLI::log( sprintf( 'Vence en %d minutos y sirve una sola vez.', diluxone_users_login_expiry() ) );
}

WP_CLI::add_command( 'diluxone-users login', 'diluxone_users_cli_login' );

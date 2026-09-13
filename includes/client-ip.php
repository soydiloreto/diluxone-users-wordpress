<?php
/**
 * The IP of whoever is on the other side.
 *
 * `REMOTE_ADDR` is the IP of whoever opened the TCP connection. Behind a proxy
 * — nginx, a load balancer, Cloudflare, the Azure App Service front end —
 * that is the proxy, not the person: on a site like that every session is
 * stored under the same internal IP and the sessions screen is useless.
 *
 * The real IP arrives in a header, and headers are written by the client:
 * always trusting them would mean letting anybody claim to be whoever they
 * like. That is why they are only listened to when `REMOTE_ADDR` is a private
 * or loopback address — that is, when the request arrived through a proxy on
 * our own network. On a server exposed straight to the internet, REMOTE_ADDR
 * rules.
 *
 * Azure App Service also writes the IP with the port stuck on
 * ("190.15.219.128:64110"). That is stripped.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/**
 * The headers the IP can arrive in, in order of trust.
 *
 * @return array<int, string>
 */
function diluxone_users_ip_headers(): array {
	/**
	 * Filters which headers are looked at to work out the client IP.
	 *
	 * @param array<int, string> $headers
	 */
	return (array) apply_filters(
		'diluxone_users_ip_headers',
		array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_TRUE_CLIENT_IP',   // Akamai, Cloudflare Enterprise.
			'HTTP_X_REAL_IP',        // nginx.
			'HTTP_X_FORWARDED_FOR',  // El estándar de hecho.
		)
	);
}

/**
 * Cleans a header value and returns the first valid IP.
 *
 * X-Forwarded-For is a list: "client, proxy1, proxy2". The first one is the
 * one that opened the request. The port Azure sticks on and the brackets of
 * an IPv6 with a port are also stripped.
 */
function diluxone_users_ip_from( string $value ): string {
	foreach ( explode( ',', $value ) as $candidate ) {
		$candidate = trim( $candidate );

			// IPv6 in brackets, with or without a port: [::1]:443
		if ( '' !== $candidate && '[' === $candidate[0] ) {
			$candidate = (string) preg_replace( '/^\[([^\]]+)\](:\d+)?$/', '$1', $candidate );
		} elseif ( 1 === substr_count( $candidate, ':' ) ) {
				// A single ":" is IPv4 with a port; two or more, IPv6 without brackets.
			$candidate = (string) strtok( $candidate, ':' );
		}

		if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
			return $candidate;
		}
	}

	return '';
}

/**
 * Is this IP from the internal network?
 *
 * If REMOTE_ADDR is private or loopback, the request arrived through a proxy
 * of our own infrastructure and its headers are believable.
 */
function diluxone_users_ip_is_internal( string $ip ): bool {
	if ( '' === $ip ) {
		return true;
	}

	return ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
}

/**
 * The client IP, resolved.
 *
 * @param array<string, mixed>|null $server So it can be tested with no server.
 */
function diluxone_users_client_ip( ?array $server = null ): string {
	$server = null === $server ? $_SERVER : $server; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$remote = diluxone_users_ip_from( (string) ( $server['REMOTE_ADDR'] ?? '' ) );

		// Exposed straight to the internet: the headers are not to be trusted.
	if ( ! diluxone_users_ip_is_internal( $remote ) ) {
		return $remote;
	}

	foreach ( diluxone_users_ip_headers() as $header ) {
		$ip = diluxone_users_ip_from( (string) ( $server[ $header ] ?? '' ) );

		if ( '' !== $ip ) {
			return $ip;
		}
	}

	return $remote;
}

/**
 * The resolved IP is stored with the session.
 *
 * WordPress stores `REMOTE_ADDR` as it is, and behind a proxy that is the
 * proxy. This filter is the point WordPress itself leaves for adding data to
 * the session, so ours travels beside its own without treading on it.
 *
 * @param array<string, mixed> $info
 * @return array<string, mixed>
 */
function diluxone_users_session_ip( array $info ): array {
	$ip = diluxone_users_client_ip();

	if ( '' !== $ip ) {
		$info['diluxone_users_ip'] = $ip;
	}

	return $info;
}
add_filter( 'attach_session_information', 'diluxone_users_session_ip' );

/**
 * The IP shown for a stored session.
 *
 * Ours is preferred; if the session is old and does not have it, WordPress's
 * is used with the port Azure sticks on cleaned off.
 *
 * @param array<string, mixed> $session
 */
function diluxone_users_session_ip_of( array $session ): string {
	if ( ! empty( $session['diluxone_users_ip'] ) ) {
		return (string) $session['diluxone_users_ip'];
	}

	return diluxone_users_ip_from( (string) ( $session['ip'] ?? '' ) );
}

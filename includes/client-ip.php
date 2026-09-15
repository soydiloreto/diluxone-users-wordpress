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
 * like. So a header is only believed when the connection came from a proxy
 * the site trusts — the private network it runs on, plus whatever the site
 * lists — and only ONE header is believed, the one that proxy is known to
 * write. Believing "whichever of these four is present" was a hole: a proxy
 * that writes X-Forwarded-For passes a client's own CF-Connecting-IP through
 * untouched, and with it any address at all, and with that address the
 * per-machine limit on creating accounts.
 *
 * Azure App Service also writes the IP with the port stuck on
 * ("190.15.219.128:64110"). That is stripped.
 *
 * @package DiluxOneUsers
 */

defined( 'ABSPATH' ) || exit;

/**
 * The headers a proxy is known to write the client IP in, for the site to
 * choose from. The key is how PHP names the header in $_SERVER.
 *
 * @return array<string, string>
 */
function diluxone_users_ip_headers(): array {
	return array(
		'HTTP_X_FORWARDED_FOR'  => 'X-Forwarded-For',  // The de facto standard: nginx, Traefik, most load balancers, Azure.
		'HTTP_X_REAL_IP'        => 'X-Real-IP',        // nginx, when set up that way.
		'HTTP_CF_CONNECTING_IP' => 'CF-Connecting-IP', // Cloudflare.
		'HTTP_TRUE_CLIENT_IP'   => 'True-Client-IP',   // Akamai, Cloudflare Enterprise.
	);
}

/**
 * The one header the site's proxy writes.
 *
 * One and not a list: the proxy in front of a site writes one of them, and
 * every other one that arrives was written by the client. With nothing
 * chosen it is X-Forwarded-For, which nearly every proxy appends to — and
 * appending is what makes it safe to read from the right.
 */
function diluxone_users_ip_header(): string {
	$header = strtoupper( (string) diluxone_users_option( 'diluxone_users_ip_header', '' ) );
	$header = (string) preg_replace( '/[^A-Z0-9_]/', '', $header );

	/**
	 * Filters which header carries the client IP behind the site's proxy.
	 *
	 * @param string $header The $_SERVER key, e.g. HTTP_X_FORWARDED_FOR.
	 */
	$header = (string) apply_filters( 'diluxone_users_ip_header', $header );

	return '' !== $header && 0 === strpos( $header, 'HTTP_' ) ? $header : 'HTTP_X_FORWARDED_FOR';
}

/**
 * The proxies whose word is believed, beyond the private network.
 *
 * One address or CIDR range per line in the setting: the public addresses of
 * a CDN, a load balancer in another network, a WAF. The private and loopback
 * ranges are always on the list and do not need writing down.
 *
 * @return array<int, string>
 */
function diluxone_users_trusted_proxies(): array {
	$lines = preg_split( '/[\s,]+/', (string) diluxone_users_option( 'diluxone_users_trusted_proxies', '' ) );
	$lines = array_values( array_filter( array_map( 'trim', is_array( $lines ) ? $lines : array() ) ) );

	/**
	 * Filters the proxies whose headers are believed.
	 *
	 * @param array<int, string> $proxies Addresses or CIDR ranges.
	 */
	return array_map( 'strval', (array) apply_filters( 'diluxone_users_trusted_proxies', $lines ) );
}

/**
 * Is this address one of ours — a proxy whose headers are believed?
 *
 * The private and loopback ranges always are: a request that arrived from
 * them came through the site's own network. Everything else has to be listed.
 */
function diluxone_users_ip_trusted( string $ip ): bool {
	if ( diluxone_users_ip_is_internal( $ip ) ) {
		return true;
	}

	foreach ( diluxone_users_trusted_proxies() as $range ) {
		if ( diluxone_users_ip_in( $ip, $range ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Is the address inside the range? A bare address is a range of one.
 *
 * Both families are handled by comparing the packed bytes bit by bit, which
 * is the same arithmetic for four bytes and for sixteen.
 */
function diluxone_users_ip_in( string $ip, string $range ): bool {
	[ $subnet, $bits ] = array_pad( explode( '/', trim( $range ), 2 ), 2, null );

	$ip_bytes     = (string) inet_pton( $ip );
	$subnet_bytes = (string) inet_pton( (string) $subnet );

	if ( '' === $ip_bytes || '' === $subnet_bytes || strlen( $ip_bytes ) !== strlen( $subnet_bytes ) ) {
		return false;
	}

	$size = strlen( $ip_bytes ) * 8;
	$bits = null === $bits ? $size : (int) $bits;

	if ( $bits < 0 || $bits > $size ) {
		return false;
	}

	$whole = intdiv( $bits, 8 );
	$rest  = $bits % 8;

	if ( 0 !== substr_compare( $ip_bytes, $subnet_bytes, 0, $whole ) ) {
		return false;
	}

	if ( 0 === $rest ) {
		return true;
	}

	$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;

	return ( ord( $ip_bytes[ $whole ] ) & $mask ) === ( ord( $subnet_bytes[ $whole ] ) & $mask );
}

/**
 * One candidate out of a header, cleaned.
 *
 * The port Azure sticks on and the brackets of an IPv6 with a port are
 * stripped. Returns '' when what is left is not an address.
 */
function diluxone_users_ip_clean( string $candidate ): string {
	$candidate = trim( $candidate );

	// IPv6 in brackets, with or without a port: [::1]:443
	if ( '' !== $candidate && '[' === $candidate[0] ) {
		$candidate = (string) preg_replace( '/^\[([^\]]+)\](:\d+)?$/', '$1', $candidate );
	} elseif ( 1 === substr_count( $candidate, ':' ) ) {
		// A single ":" is IPv4 with a port; two or more, IPv6 without brackets.
		$candidate = (string) strtok( $candidate, ':' );
	}

	return filter_var( $candidate, FILTER_VALIDATE_IP ) ? $candidate : '';
}

/**
 * Every valid address in a header, in the order they were written.
 *
 * @return array<int, string>
 */
function diluxone_users_ip_candidates( string $value ): array {
	$out = array();

	foreach ( explode( ',', $value ) as $candidate ) {
		$ip = diluxone_users_ip_clean( $candidate );

		if ( '' !== $ip ) {
			$out[] = $ip;
		}
	}

	return $out;
}

/** Cleans a header value and returns the first valid IP. */
function diluxone_users_ip_from( string $value ): string {
	return diluxone_users_ip_candidates( $value )[0] ?? '';
}

/**
 * Is this IP from the internal network?
 *
 * If REMOTE_ADDR is private or loopback, the request arrived through a proxy
 * of our own infrastructure.
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

	// Not one of our proxies — exposed straight to the internet, or behind
	// something the site never said it trusts: the headers are the client's.
	if ( '' === $remote || ! diluxone_users_ip_trusted( $remote ) ) {
		return $remote;
	}

	$header     = diluxone_users_ip_header();
	$candidates = diluxone_users_ip_candidates( (string) ( $server[ $header ] ?? '' ) );

	if ( array() === $candidates ) {
		return $remote;
	}

	// A header the proxy SETS holds one address, the proxy's word.
	if ( 'HTTP_X_FORWARDED_FOR' !== $header ) {
		return $candidates[0];
	}

	// X-Forwarded-For is "client, proxy1, proxy2" and every proxy APPENDS its
	// own view, so the right end is the last proxy's word and the left end
	// whatever the client wrote before the first proxy saw it. Walking from
	// the right past our own proxies, the first address that is not ours is
	// the one that reached the first of them.
	for ( $i = count( $candidates ) - 1; $i >= 0; $i-- ) {
		if ( ! diluxone_users_ip_trusted( $candidates[ $i ] ) ) {
			return $candidates[ $i ];
		}
	}

	// Every hop was ours: a client on the internal network.
	return $candidates[0];
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

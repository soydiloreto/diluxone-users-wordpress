<?php
/**
 * A redirect, caught instead of followed.
 *
 * Every handler in the plugin ends in wp_safe_redirect() and exit. Under
 * PHPUnit the exit would end the run, so the `wp_redirect` filter — which
 * WordPress applies before sending the header — throws this instead, with
 * the address the handler was about to send the person to.
 */

namespace Tests\Integration\Support;

final class RedirectException extends \RuntimeException {

	public string $url;

	public function __construct( string $url ) {
		parent::__construct( 'Redirect to ' . $url );
		$this->url = $url;
	}
}

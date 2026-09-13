<?php
/**
 * The session IP is what gets looked at when somebody says "I do not
 * recognise that sign-in". If behind a proxy it always stores the proxy's, the
 * sessions screen is useless; and if it believes any header, anybody can claim
 * to be whoever they like. These tests pin both things down.
 */

namespace Tests\Unit\DiluxOneUsers;

use Brain\Monkey;
use PHPUnit\Framework\TestCase;

class ClientIpTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		require_once DILUXONE_USERS_DIR . 'includes/client-ip.php';
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function test_with_no_proxy_remote_addr_wins(): void {
		$this->assertSame( '190.15.219.128', diluxone_users_client_ip( array(
			'REMOTE_ADDR' => '190.15.219.128',
		) ) );
	}

	public function test_a_public_ip_does_not_trust_the_headers(): void {
		// The server is exposed straight to the internet: the header was written
		// by the client and could say anything.
		$this->assertSame( '190.15.219.128', diluxone_users_client_ip( array(
			'REMOTE_ADDR'          => '190.15.219.128',
			'HTTP_X_FORWARDED_FOR' => '8.8.8.8',
		) ) );
	}

	public function test_behind_an_internal_proxy_it_uses_the_header(): void {
		$this->assertSame( '181.45.184.48', diluxone_users_client_ip( array(
			'REMOTE_ADDR'          => '172.20.0.1',
			'HTTP_X_FORWARDED_FOR' => '181.45.184.48',
		) ) );
	}

	public function test_from_a_chain_of_proxies_it_keeps_the_client(): void {
		// X-Forwarded-For is "client, proxy1, proxy2": the first is who asked.
		$this->assertSame( '181.45.184.48', diluxone_users_client_ip( array(
			'REMOTE_ADDR'          => '10.0.0.5',
			'HTTP_X_FORWARDED_FOR' => '181.45.184.48, 10.0.0.9, 10.0.0.5',
		) ) );
	}

	public function test_it_strips_the_port_azure_appends(): void {
		// App Service writes "ip:port" and that is not an IP.
		$this->assertSame( '79.159.158.249', diluxone_users_client_ip( array(
			'REMOTE_ADDR'          => '127.0.0.1',
			'HTTP_X_FORWARDED_FOR' => '79.159.158.249:49391',
		) ) );
	}

	public function test_cloudflare_wins_over_x_forwarded_for(): void {
		$this->assertSame( '200.1.2.3', diluxone_users_client_ip( array(
			'REMOTE_ADDR'           => '172.16.0.1',
			'HTTP_CF_CONNECTING_IP' => '200.1.2.3',
			'HTTP_X_FORWARDED_FOR'  => '8.8.8.8',
		) ) );
	}

	public function test_a_junk_header_breaks_nothing(): void {
		$this->assertSame( '10.0.0.5', diluxone_users_client_ip( array(
			'REMOTE_ADDR'          => '10.0.0.5',
			'HTTP_X_FORWARDED_FOR' => 'no-soy-una-ip',
		) ) );
	}

	public function test_ipv6_with_brackets_and_port(): void {
		$this->assertSame( '2803:9800:a::1', diluxone_users_client_ip( array(
			'REMOTE_ADDR'          => '::1',
			'HTTP_X_FORWARDED_FOR' => '[2803:9800:a::1]:51234',
		) ) );
	}

	public function test_an_old_session_without_our_ip_uses_the_wordpress_one(): void {
		$this->assertSame( '79.159.158.249', diluxone_users_session_ip_of( array( 'ip' => '79.159.158.249:49391' ) ) );
	}

	public function test_a_session_carrying_our_ip_is_preferred(): void {
		$this->assertSame( '181.45.184.48', diluxone_users_session_ip_of( array(
			'ip'      => '172.20.0.1',
			'diluxone_users_ip' => '181.45.184.48',
		) ) );
	}
}

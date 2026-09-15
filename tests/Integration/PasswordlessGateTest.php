<?php
/**
 * H-05: taking over WordPress's screens must not take away the password.
 *
 * With "my screens" chosen and the password still a way in, the site's own
 * page draws WordPress's password form — which posts to wp-login.php. That
 * POST used to meet a 403, and nobody could sign in with a password.
 */

namespace Tests\Integration;

class PasswordlessGateTest extends IntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		update_option( 'diluxone_users_wp_screens', 'mine' );
		add_filter( 'diluxone_users_login_url', array( $this, 'own_page' ) );
	}

	protected function tearDown(): void {
		remove_filter( 'diluxone_users_login_url', array( $this, 'own_page' ) );
		parent::tearDown();
	}

	public function own_page(): string {
		return home_url( '/sign-in/' );
	}

	/** @param array<string, string> $get */
	private function request( string $method, array $get = array(), array $post = array() ): void {
		$_SERVER['REQUEST_METHOD'] = $method;
		$_GET                      = $get;
		$_POST                     = $post;
	}

	public function test_the_password_form_can_still_post_to_wp_login(): void {
		update_option( 'diluxone_users_login_method', 'both' );
		$this->request( 'POST', array(), array( 'log' => 'someone', 'pwd' => 'secret' ) );

		diluxone_users_block_wp_login();

		$this->assertTrue( true, 'The POST went through: no 403, no redirect' );
	}

	public function test_with_only_the_link_the_post_meets_the_wall(): void {
		update_option( 'diluxone_users_login_method', 'link' );
		$this->request( 'POST', array(), array( 'log' => 'someone', 'pwd' => 'secret' ) );

		$this->expectException( \WPAjaxDieContinueException::class );

		diluxone_users_block_wp_login();
	}

	public function test_a_post_to_a_closed_form_meets_the_wall_even_with_the_password_on(): void {
		update_option( 'diluxone_users_login_method', 'both' );
		$this->request( 'POST', array( 'action' => 'lostpassword' ), array( 'user_login' => 'someone' ) );

		$this->expectException( \WPAjaxDieContinueException::class );

		diluxone_users_block_wp_login();
	}

	public function test_opening_wp_login_goes_to_the_site_page(): void {
		update_option( 'diluxone_users_login_method', 'both' );
		$this->request( 'GET' );

		$this->assertSame( home_url( '/sign-in/' ), $this->expectRedirect( 'diluxone_users_block_wp_login' ) );
	}

	public function test_the_escape_hatch_still_opens_the_native_form(): void {
		update_option( 'diluxone_users_login_method', 'link' );
		$this->request( 'GET', array( 'diluxone-users-admin' => '1' ) );

		diluxone_users_block_wp_login();

		$this->assertTrue( true );
	}

	public function test_signing_out_is_left_alone(): void {
		$this->request( 'GET', array( 'action' => 'logout' ) );

		diluxone_users_block_wp_login();

		$this->assertTrue( true );
	}

	public function test_with_wordpress_screens_nothing_is_touched(): void {
		update_option( 'diluxone_users_wp_screens', 'wp' );
		update_option( 'diluxone_users_login_method', 'link' );
		$this->request( 'POST' );

		diluxone_users_block_wp_login();

		$this->assertTrue( true );
	}
}
